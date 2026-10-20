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
        try {
            $this->syncClient($client);
        } catch (\Throwable $e) {
            Log::warning('Google Contacts sync failed', [
                'client_id' => $client->id,
                'company_id' => $client->company_id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function syncClient(Client $client): void
    {
        $phone = $this->normalizePhone($client->phone);
        $name = trim((string) $client->name);

        if ($phone === null || $name === '' || $name === 'Клиент') {
            return;
        }

        $integration = $this->oauth->integrationForCompany((int) $client->company_id);
        if (! $integration) {
            return;
        }

        $accessToken = $this->oauth->validAccessToken($integration);
        $resourceName = trim((string) ($client->google_resource_name ?? ''));

        if ($resourceName !== '') {
            $this->updateContact($accessToken, $resourceName, $name, $phone);
            $client->forceFill(['google_resource_name' => $resourceName])->saveQuietly();

            return;
        }

        $existing = $this->findContactResourceByPhone($accessToken, $phone);
        if ($existing !== null) {
            $this->updateContact($accessToken, $existing, $name, $phone);
            $client->forceFill(['google_resource_name' => $existing])->saveQuietly();

            return;
        }

        $created = $this->createContact($accessToken, $name, $phone);
        $client->forceFill(['google_resource_name' => $created])->saveQuietly();
    }

    protected function createContact(string $accessToken, string $name, string $phone): string
    {
        $response = Http::withToken($accessToken)
            ->timeout((int) config('services.google.timeout', 20))
            ->acceptJson()
            ->post('https://people.googleapis.com/v1/people:createContact', [
                'names' => [
                    ['givenName' => $name],
                ],
                'phoneNumbers' => [
                    ['value' => $phone, 'type' => 'mobile'],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->apiError($response->json(), __('Не удалось создать контакт в Google.')));
        }

        $resourceName = (string) ($response->json('resourceName') ?? '');
        if ($resourceName === '') {
            throw new RuntimeException(__('Google не вернул resourceName контакта.'));
        }

        return $resourceName;
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
            ->withQueryParameters(['updatePersonFields' => 'names,phoneNumbers'])
            ->patch(
                'https://people.googleapis.com/v1/'.ltrim($resourceName, '/').':updateContact',
                [
                    'etag' => $etag,
                    'names' => [
                        ['givenName' => $name],
                    ],
                    'phoneNumbers' => [
                        ['value' => $phone, 'type' => 'mobile'],
                    ],
                ],
            );

        if (! $response->successful()) {
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

        $response = Http::withToken($accessToken)
            ->timeout((int) config('services.google.timeout', 20))
            ->acceptJson()
            ->get('https://people.googleapis.com/v1/people:searchContacts', [
                'query' => $digits,
                'readMask' => 'names,phoneNumbers',
                'pageSize' => 10,
            ]);

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
                $entryDigits = preg_replace('/\D+/', '', $value) ?: '';

                if ($entryDigits !== '' && (
                    str_ends_with($digits, $entryDigits) || str_ends_with($entryDigits, $digits)
                )) {
                    return $resourceName !== '' ? $resourceName : null;
                }

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

        $trimmed = trim($value);
        if ($trimmed === '' || ! preg_match('/^\+?\d[\d\s()-]{6,}$/', $trimmed)) {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', $trimmed);

        return is_string($normalized) && $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function apiError(?array $payload, string $fallback): string
    {
        $message = $payload['error']['message'] ?? $payload['error_description'] ?? null;

        return is_string($message) && trim($message) !== '' ? trim($message) : $fallback;
    }
}
