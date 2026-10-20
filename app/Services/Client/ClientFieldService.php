<?php

namespace App\Services\Client;

use App\Enums\IntegrationProvider;
use App\Models\Client;
use App\Models\ClientFieldDefinition;
use App\Models\MessengerConversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClientFieldService
{
    public const DEFAULT_NAME_KEY = 'name';

    public const DEFAULT_PHONE_KEY = 'phone';

    /**
     * @return list<string>
     */
    public static function defaultFieldKeys(): array
    {
        return [self::DEFAULT_NAME_KEY, self::DEFAULT_PHONE_KEY];
    }

    public function isDefaultFieldKey(string $key): bool
    {
        return in_array($key, self::defaultFieldKeys(), true);
    }

    /**
     * Ensure every company has "Имя / ник" and "Телефон" fields.
     */
    public function ensureDefaultFields(int $companyId): void
    {
        $existingKeys = ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->pluck('key')
            ->all();

        $defaults = [
            [
                'key' => self::DEFAULT_NAME_KEY,
                'label' => 'Имя / ник',
                'type' => 'text',
                'sort_order' => 0,
                'show_in_messenger' => true,
            ],
            [
                'key' => self::DEFAULT_PHONE_KEY,
                'label' => 'Телефон',
                'type' => 'phone',
                'sort_order' => 1,
                'show_in_messenger' => false,
            ],
        ];

        $missing = array_values(array_filter(
            $defaults,
            fn (array $field) => ! in_array($field['key'], $existingKeys, true),
        ));

        if ($missing === []) {
            return;
        }

        ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->increment('sort_order', count($missing));

        $hasMessengerField = ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->where('show_in_messenger', true)
            ->exists();

        foreach ($missing as $index => $field) {
            $showInMessenger = $field['show_in_messenger'] && ! $hasMessengerField;

            if ($showInMessenger) {
                $hasMessengerField = true;
            }

            ClientFieldDefinition::query()->create([
                'company_id' => $companyId,
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => null,
                'is_required' => false,
                'show_in_messenger' => $showInMessenger,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return Collection<int, ClientFieldDefinition>
     */
    public function definitionsForCompany(int $companyId): Collection
    {
        $this->ensureDefaultFields($companyId);

        return ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function validationRulesForCompany(int $companyId): array
    {
        $rules = [];

        foreach ($this->definitionsForCompany($companyId) as $definition) {
            $field = 'fields.'.$definition->key;
            $rule = match ($definition->type) {
                'email' => 'nullable|email|max:255',
                'number' => 'nullable|numeric',
                'date' => 'nullable|date',
                'textarea' => 'nullable|string|max:65535',
                'phone' => 'nullable|string|max:64',
                'select' => 'nullable|string|max:255',
                default => 'nullable|string|max:2000',
            };

            if ($definition->is_required) {
                $rule = str_replace('nullable|', 'required|', $rule);
                if (! str_contains($rule, 'required|')) {
                    $rule = 'required|'.$rule;
                }
            }

            $rules[$field] = $rule;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{custom_fields: array<string, mixed>, name: string, phone: ?string, email: ?string}
     */
    public function normalizeSubmittedFields(int $companyId, array $fields): array
    {
        $definitions = $this->definitionsForCompany($companyId)->keyBy('key');
        $customFields = [];

        foreach ($definitions as $key => $definition) {
            $value = $fields[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $customFields[$key] = $value;
        }

        return [
            'custom_fields' => $customFields,
            'name' => $this->resolveCoreValue($customFields, ['name', 'imya', 'fio', 'full_name', 'username', 'nick'], 'Клиент'),
            'phone' => $this->resolveCoreValue($customFields, ['phone', 'telefon', 'nomer', 'number']),
            'email' => $this->resolveCoreValue($customFields, ['email', 'pochta', 'mail']),
        ];
    }

    /**
     * @param  array<string, mixed>  $customFields
     * @param  list<string>  $keys
     */
    public function resolveCoreValue(array $customFields, array $keys, ?string $fallback = null): ?string
    {
        foreach ($keys as $key) {
            $value = $customFields[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    /**
     * Extract name/nick and phone from a messenger conversation by channel.
     *
     * @return array{name: string, phone: ?string, username: ?string}
     */
    public function profileFromConversation(MessengerConversation $conversation): array
    {
        $displayName = trim((string) ($conversation->participant_name ?? ''));
        $rawUsername = trim((string) ($conversation->participant_username ?? ''));
        $username = ltrim($rawUsername, '@');
        $channel = (string) $conversation->channel;

        $phone = null;
        $nick = null;

        if ($channel === IntegrationProvider::Wappi->value) {
            $phoneCandidate = $username !== '' ? $username : trim((string) ($conversation->participant_id ?? ''));
            $phone = $this->normalizePhone($phoneCandidate);
        } elseif ($channel === IntegrationProvider::Telegram->value) {
            if ($username !== '' && $this->looksLikePhone($username)) {
                $phone = $this->normalizePhone($username);
            } else {
                $nick = $username !== '' ? $username : null;
            }
        } else {
            // Instagram / Facebook — username is a handle, not a phone.
            $nick = $username !== '' ? $username : null;
        }

        $name = $displayName;

        if ($name === '' && $nick !== null) {
            $name = $nick;
        }

        if ($name === '' && $phone !== null) {
            $name = $phone;
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'username' => $nick,
        ];
    }

    /**
     * Create or update a client from conversation profile data.
     * Fills empty fields only — does not overwrite values already set by the user.
     */
    public function syncClientFromConversation(
        MessengerConversation $conversation,
        ?string $sharedPhone = null,
    ): ?Client {
        $companyId = (int) $conversation->company_id;
        $this->ensureDefaultFields($companyId);

        $profile = $this->profileFromConversation($conversation);

        if ($sharedPhone !== null) {
            $normalizedShared = $this->normalizePhone($sharedPhone);
            if ($normalizedShared !== null) {
                $profile['phone'] = $normalizedShared;
            }
        }

        if ($profile['name'] === '' && $profile['phone'] === null && $profile['username'] === null) {
            return $conversation->client_id
                ? Client::query()
                    ->where('company_id', $companyId)
                    ->whereKey($conversation->client_id)
                    ->first()
                : null;
        }

        $customFields = $this->customFieldsFromProfile($companyId, $profile);

        if ($conversation->client_id) {
            $client = Client::query()
                ->where('company_id', $companyId)
                ->whereKey($conversation->client_id)
                ->first();

            if (! $client) {
                return null;
            }

            $updates = [];
            $mergedCustom = is_array($client->custom_fields) ? $client->custom_fields : [];

            foreach ($customFields as $key => $value) {
                if ($this->isBlank($mergedCustom[$key] ?? null)) {
                    $mergedCustom[$key] = $value;
                }
            }

            if ($mergedCustom !== ($client->custom_fields ?? [])) {
                $updates['custom_fields'] = $mergedCustom;
            }

            if ($this->isBlankName($client->name) && $profile['name'] !== '') {
                $updates['name'] = $profile['name'];
            }

            if ($this->isBlank($client->phone) && $profile['phone'] !== null) {
                $updates['phone'] = $profile['phone'];
            }

            if ($updates !== []) {
                $client->update($updates);
            }

            return $client->refresh();
        }

        $name = $profile['name'] !== '' ? $profile['name'] : 'Клиент';

        $client = Client::query()->create([
            'company_id' => $companyId,
            'name' => $name,
            'phone' => $profile['phone'],
            'email' => null,
            'custom_fields' => $customFields,
        ]);

        $conversation->update(['client_id' => $client->id]);

        return $client;
    }

    /**
     * @param  array{name: string, phone: ?string, username: ?string}  $profile
     * @return array<string, string>
     */
    public function customFieldsFromProfile(int $companyId, array $profile): array
    {
        $custom = [];

        foreach ($this->definitionsForCompany($companyId) as $definition) {
            $value = $this->profileValueForKey($definition->key, $profile);

            if ($value !== '') {
                $custom[$definition->key] = $value;
            }
        }

        return $custom;
    }

    /**
     * @param  array{name: string, phone: ?string, username: ?string}  $profile
     */
    public function profileValueForKey(string $key, array $profile): string
    {
        $normalizedKey = Str::lower($key);

        if (in_array($normalizedKey, ['name', 'imya', 'fio', 'full_name', 'fullname'], true)) {
            return (string) ($profile['name'] ?? '');
        }

        if (in_array($normalizedKey, ['phone', 'telefon', 'nomer', 'number', 'tel'], true)) {
            return (string) ($profile['phone'] ?? '');
        }

        if (in_array($normalizedKey, ['username', 'login', 'nick'], true)) {
            $username = (string) ($profile['username'] ?? '');

            return $username !== '' ? $username : (string) ($profile['name'] ?? '');
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    public function prefillForConversation(MessengerConversation $conversation, Collection $definitions): array
    {
        $profile = $this->profileFromConversation($conversation);
        $prefill = [];

        foreach ($definitions as $definition) {
            $prefill[$definition->key] = $this->profileValueForKey($definition->key, $profile);
        }

        return $prefill;
    }

    public function prefillValueForKey(string $key, MessengerConversation $conversation): string
    {
        return $this->profileValueForKey($key, $this->profileFromConversation($conversation));
    }

    public function upsertClientFromConversation(
        MessengerConversation $conversation,
        array $normalized,
    ): Client {
        $payload = [
            'name' => $normalized['name'],
            'phone' => $normalized['phone'],
            'email' => $normalized['email'],
            'custom_fields' => $normalized['custom_fields'],
        ];

        if ($conversation->client_id) {
            $client = Client::query()
                ->where('company_id', $conversation->company_id)
                ->whereKey($conversation->client_id)
                ->firstOrFail();

            $client->update($payload);

            return $client->refresh();
        }

        $client = Client::query()->create([
            'company_id' => $conversation->company_id,
            ...$payload,
        ]);

        $conversation->update(['client_id' => $client->id]);

        return $client;
    }

    public static function normalizeKey(string $label, ?string $key = null): string
    {
        $key = trim((string) $key);

        if ($key !== '') {
            $key = Str::ascii($key);

            return Str::snake(preg_replace('/[^a-zA-Z0-9_]+/', '_', $key) ?: 'field');
        }

        $transliterated = Str::ascii($label);
        $slug = Str::snake(preg_replace('/[^a-zA-Z0-9_]+/', '_', $transliterated) ?: 'field');

        return $slug !== '' ? $slug : 'field';
    }

    public function messengerFieldDefinition(int $companyId): ?ClientFieldDefinition
    {
        $this->ensureDefaultFields($companyId);

        return ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->where('show_in_messenger', true)
            ->first();
    }

    public function ensureSingleMessengerField(int $companyId, ?int $activeFieldId = null): void
    {
        ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->when($activeFieldId !== null, fn ($query) => $query->whereKeyNot($activeFieldId))
            ->update(['show_in_messenger' => false]);
    }

    public function resolveMessengerDisplayName(
        MessengerConversation $conversation,
        ?Client $client,
        ?ClientFieldDefinition $messengerField,
    ): string {
        if ($client && $messengerField) {
            $value = $client->custom_fields[$messengerField->key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $profile = $this->profileFromConversation($conversation);

        return (string) (
            $profile['name']
            ?: $profile['username']
            ?: $conversation->participant_id
            ?: ''
        );
    }

    public function looksLikePhone(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = trim($value);

        return $trimmed !== '' && (bool) preg_match('/^\+?\d[\d\s()-]{6,}$/', $trimmed);
    }

    public function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! $this->looksLikePhone($trimmed)) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $trimmed);

        return is_string($digits) && $digits !== '' ? $digits : null;
    }

    protected function isBlank(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }

    protected function isBlankName(?string $name): bool
    {
        $trimmed = trim((string) $name);

        return $trimmed === '' || $trimmed === 'Клиент';
    }
}
