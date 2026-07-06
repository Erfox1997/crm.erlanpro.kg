<?php

namespace App\Services\Client;

use App\Models\Client;
use App\Models\ClientFieldDefinition;
use App\Models\MessengerConversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClientFieldService
{
    /**
     * @return Collection<int, ClientFieldDefinition>
     */
    public function definitionsForCompany(int $companyId): Collection
    {
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
            'name' => $this->resolveCoreValue($customFields, ['name', 'imya', 'fio', 'full_name'], 'Клиент'),
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
     * @return array<string, string>
     */
    public function prefillForConversation(MessengerConversation $conversation, Collection $definitions): array
    {
        $prefill = [];

        foreach ($definitions as $definition) {
            $prefill[$definition->key] = $this->prefillValueForKey($definition->key, $conversation);
        }

        return $prefill;
    }

    public function prefillValueForKey(string $key, MessengerConversation $conversation): string
    {
        $normalizedKey = Str::lower($key);

        if (in_array($normalizedKey, ['name', 'imya', 'fio', 'full_name', 'fullname'], true)) {
            return (string) ($conversation->participant_name ?? '');
        }

        if (in_array($normalizedKey, ['phone', 'telefon', 'nomer', 'number', 'tel'], true)) {
            $username = (string) ($conversation->participant_username ?? '');

            return ltrim($username, '@');
        }

        if (in_array($normalizedKey, ['username', 'login', 'nick'], true)) {
            return ltrim((string) ($conversation->participant_username ?? ''), '@');
        }

        return '';
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
}
