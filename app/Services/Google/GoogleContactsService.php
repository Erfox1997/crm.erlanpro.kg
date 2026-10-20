<?php

namespace App\Services\Google;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleContactsService
{
    public function __construct(
        private GoogleOAuthService $oauth,
    ) {}

    /**
     * Sync name + phone to Google Contacts. Safe to call often — never throws to callers.
     */
    public function syncClientQuietly(Client $client): void
    {
        $this->syncClientWithResult($client);
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string}
     */
    public function syncClientWithResult(Client $client): array
    {
        try {
            return $this->syncClient($client);
        } catch (\Throwable $e) {
            Log::warning('Google Contacts sync failed', [
                'client_id' => $client->id,
                'company_id' => $client->company_id,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'skipped' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string}
     */
    public function syncClient(Client $client): array
    {
        $name = $this->resolveName($client);
        $phone = $this->normalizePhone($this->resolvePhone($client));

        if ($name === '' || $name === 'Клиент') {
            return [
                'ok' => false,
                'skipped' => true,
                'message' => __('Нет имени для Google Contacts.'),
            ];
        }

        if ($phone === null) {
            return [
                'ok' => false,
                'skipped' => true,
                'message' => __('Нет телефона для Google Contacts. Укажите номер и сохраните снова.'),
            ];
        }

        $integration = $this->oauth->integrationForCompany((int) $client->company_id);
        if (! $integration) {
            return [
                'ok' => false,
                'skipped' => true,
                'message' => __('Google Contacts не подключён в Интеграциях.'),
            ];
        }

        $accessToken = $this->oauth->validAccessToken($integration);
        $resourceName = trim((string) ($client->google_resource_name ?? ''));

        if ($resourceName !== '') {
            try {
                $this->updateContact($accessToken, $resourceName, $name, $phone);
            } catch (\Throwable $e) {
                // Stale resource — create a new contact instead.
                Log::info('Google contact update failed, recreating', [
                    'client_id' => $client->id,
                    'resource' => $resourceName,
                    'message' => $e->getMessage(),
                ]);
                $resourceName = '';
            }
        }

        if ($resourceName === '') {
            $existing = $this->findContactResourceByPhone($accessToken, $phone);
            if ($existing !== null) {
                $this->updateContact($accessToken, $existing, $name, $phone);
                $resourceName = $existing;
            } else {
                $resourceName = $this->createContact($accessToken, $name, $phone);
            }
        }

        // Make sure the contact is visible in contacts.google.com (My Contacts).
        $this->ensureMyContactsMembership($accessToken, $resourceName);

        $client->forceFill([
            'google_resource_name' => $resourceName,
            // Keep CRM phone normalized so later syncs keep working.
            'phone' => $phone,
            'name' => $name,
        ])->saveQuietly();

        return [
            'ok' => true,
            'skipped' => false,
            'message' => __('Сохранено в Google Contacts.'),
        ];
    }

    protected function resolveName(Client $client): string
    {
        $name = trim((string) $client->name);
        if ($name !== '' && $name !== 'Клиент') {
            return $name;
        }

        $custom = is_array($client->custom_fields) ? $client->custom_fields : [];
        foreach (['name', 'imya', 'fio', 'full_name', 'fullname', 'username', 'nick'] as $key) {
            $value = trim((string) ($custom[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $name;
    }

    protected function resolvePhone(Client $client): ?string
    {
        $phone = trim((string) ($client->phone ?? ''));
        if ($phone !== '') {
            return $phone;
        }

        $custom = is_array($client->custom_fields) ? $client->custom_fields : [];
        foreach (['phone', 'telefon', 'nomer', 'number', 'tel'] as $key) {
            $value = trim((string) ($custom[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function createContact(string $accessToken, string $name, string $phone): string
    {
        $response = Http::withToken($accessToken)
            ->timeout((int) config('services.google.timeout', 20))
            ->acceptJson()
            ->asJson()
            ->post('https://people.googleapis.com/v1/people:createContact', [
                'names' => [
                    [
                        'givenName' => $name,
                        'unstructuredName' => $name,
                    ],
                ],
                'phoneNumbers' => [
                    ['value' => $phone, 'type' => 'mobile'],
                ],
                // Without myContacts membership the person exists via API but often
                // does not show up in contacts.google.com UI.
                'memberships' => [
                    [
                        'contactGroupMembership' => [
                            'contactGroupResourceName' => 'contactGroups/myContacts',
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Google createContact failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException($this->apiError($response->json(), __('Не удалось создать контакт в Google.')));
        }

        $resourceName = (string) ($response->json('resourceName') ?? '');
        if ($resourceName === '') {
            throw new RuntimeException(__('Google не вернул resourceName контакта.'));
        }

        $this->ensureMyContactsMembership($accessToken, $resourceName);

        return $resourceName;
    }

    protected function ensureMyContactsMembership(string $accessToken, string $resourceName): void
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout((int) config('services.google.timeout', 20))
                ->acceptJson()
                ->asJson()
                ->post('https://people.googleapis.com/v1/contactGroups/myContacts/members:modify', [
                    'resourceNamesToAdd' => [$resourceName],
                ]);

            if (! $response->successful()) {
                Log::warning('Google myContacts membership failed', [
                    'resource' => $resourceName,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Google myContacts membership exception', [
                'resource' => $resourceName,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function updateContact(string $accessToken, string $resourceName, string $name, string $phone): void
    {
        $person = $this->getContact($accessToken, $resourceName);
        $etag = (string) ($person['etag'] ?? '');

        if ($etag === '') {
            throw new RuntimeException(__('Не удалось получить etag контакта Google.'));
        }

        $response = Http::withToken($accessToken)
            ->timeout((int) config('services.google.timeout', 20))
            ->acceptJson()
            ->asJson()
            ->withQueryParameters(['updatePersonFields' => 'names,phoneNumbers'])
            ->patch(
                'https://people.googleapis.com/v1/'.ltrim($resourceName, '/').':updateContact',
                [
                    'etag' => $etag,
                    'names' => [
                        [
                            'givenName' => $name,
                            'unstructuredName' => $name,
                        ],
                    ],
                    'phoneNumbers' => [
                        ['value' => $phone, 'type' => 'mobile'],
                    ],
                ],
            );

        if (! $response->successful()) {
            Log::warning('Google updateContact failed', [
                'status' => $response->status(),
                'resource' => $resourceName,
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException($this->apiError($response->json(), __('Не удалось обновить контакт в Google.')));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getContact(string $accessToken, string $resourceName): array
    {
        $response = Http::withToken($accessToken)
            ->timeout((int) config('services.google.timeout', 20))
            ->acceptJson()
            ->get('https://people.googleapis.com/v1/'.ltrim($resourceName, '/'), [
                'personFields' => 'names,phoneNumbers',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->apiError($response->json(), __('Не удалось прочитать контакт Google.')));
        }

        return $response->json() ?? [];
    }

    protected function findContactResourceByPhone(string $accessToken, string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;

        try {
            $response = Http::withToken($accessToken)
                ->timeout((int) config('services.google.timeout', 20))
                ->acceptJson()
                ->get('https://people.googleapis.com/v1/people:searchContacts', [
                    'query' => $digits,
                    'readMask' => 'names,phoneNumbers',
                    'pageSize' => 10,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $results = $response->json('results') ?? [];
        if (! is_array($results)) {
            return null;
        }

        foreach ($results as $row) {
            $person = is_array($row['person'] ?? null) ? $row['person'] : null;
            if (! $person) {
                continue;
            }

            $resourceName = (string) ($person['resourceName'] ?? '');
            $phones = is_array($person['phoneNumbers'] ?? null) ? $person['phoneNumbers'] : [];

            foreach ($phones as $entry) {
                $value = (string) ($entry['value'] ?? '');
                if ($this->phonesMatch($phone, $value)) {
                    return $resourceName !== '' ? $resourceName : null;
                }
            }
        }

        return null;
    }

    protected function phonesMatch(string $a, string $b): bool
    {
        $da = preg_replace('/\D+/', '', $a) ?: '';
        $db = preg_replace('/\D+/', '', $b) ?: '';

        if ($da === '' || $db === '') {
            return false;
        }

        if ($da === $db) {
            return true;
        }

        $min = min(strlen($da), strlen($db));

        return $min >= 9 && (str_ends_with($da, substr($db, -9)) || str_ends_with($db, substr($da, -9)));
    }

    protected function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // UI may show @996..., strip it before validation.
        $trimmed = ltrim(trim($value), '@');
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?: '';
        if (strlen($digits) < 8) {
            return null;
        }

        // E.164-style helps Google Contacts + WhatsApp match the number.
        return '+'.$digits;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function apiError(?array $payload, string $fallback): string
    {
        $message = $payload['error']['message'] ?? $payload['error_description'] ?? null;

        if (is_string($message) && trim($message) !== '') {
            $status = $payload['error']['status'] ?? null;
            if (is_string($status) && $status !== '') {
                return trim($message).' ('.$status.')';
            }

            return trim($message);
        }

        return $fallback;
    }
}
