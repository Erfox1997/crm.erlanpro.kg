<?php

namespace App\Services\Google;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleOAuthService
{
    public function redirectUri(): string
    {
        $configured = trim((string) config('services.google.redirect_uri'));

        if ($configured !== '') {
            return $configured;
        }

        return route('integrations.google.callback');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(__('Укажите GOOGLE_CLIENT_ID и GOOGLE_CLIENT_SECRET в .env.'));
        }

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => trim((string) config('services.google.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scope: ?string, token_type: ?string}
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->timeout((int) config('services.google.timeout', 20))
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), __('Не удалось обменять код Google OAuth.')));
        }

        $data = $response->json();
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException(__('Google не вернул access_token.'));
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
            'scope' => isset($data['scope']) ? (string) $data['scope'] : null,
            'token_type' => isset($data['token_type']) ? (string) $data['token_type'] : null,
        ];
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()
            ->timeout((int) config('services.google.timeout', 20))
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), __('Не удалось обновить токен Google.')));
        }

        $data = $response->json();
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException(__('Google не вернул access_token при обновлении.'));
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    /**
     * @return array{id: string, email: ?string, name: ?string}
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->timeout((int) config('services.google.timeout', 20))
            ->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), __('Не удалось получить профиль Google.')));
        }

        $data = $response->json();
        $id = (string) ($data['id'] ?? '');

        if ($id === '') {
            throw new RuntimeException(__('Google не вернул ID пользователя.'));
        }

        return [
            'id' => $id,
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'name' => isset($data['name']) ? (string) $data['name'] : null,
        ];
    }

    /**
     * @param  array{access_token: string, refresh_token: ?string, expires_in: int}  $token
     * @param  array{id: string, email: ?string, name: ?string}  $user
     */
    public function persistIntegration(int $companyId, array $token, array $user): CompanyIntegration
    {
        $existing = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', IntegrationProvider::Google->value)
            ->first();

        $metadata = $existing?->safeMetadata() ?? [];
        $refreshToken = $token['refresh_token']
            ?? ($metadata['refresh_token'] ?? null);

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException(__('Google не вернул refresh_token. Отключите доступ в аккаунте Google и подключите снова.'));
        }

        return CompanyIntegration::upsertForCompany($companyId, IntegrationProvider::Google->value, [
            'api_token' => $token['access_token'],
            'metadata' => [
                'google_user_id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds(max(60, (int) $token['expires_in'] - 60))->toIso8601String(),
                'connected_via' => 'oauth',
            ],
        ]);
    }

    public function integrationForCompany(int $companyId): ?CompanyIntegration
    {
        return CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', IntegrationProvider::Google->value)
            ->get()
            ->first(fn (CompanyIntegration $integration) => $integration->hasUsableApiToken()
                && filled($integration->safeMetadata()['refresh_token'] ?? null));
    }

    /**
     * Returns a valid access token, refreshing when needed.
     */
    public function validAccessToken(CompanyIntegration $integration): string
    {
        $metadata = $integration->safeMetadata();
        $expiresAt = (string) ($metadata['token_expires_at'] ?? '');
        $accessToken = '';

        try {
            $accessToken = (string) ($integration->api_token ?? '');
        } catch (\Throwable) {
            $accessToken = '';
        }

        $stillValid = $expiresAt !== ''
            && now()->lt(Carbon::parse($expiresAt))
            && $accessToken !== '';

        if ($stillValid) {
            return $accessToken;
        }

        $refreshToken = (string) ($metadata['refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new RuntimeException(__('Нет refresh_token Google. Переподключите интеграцию.'));
        }

        $refreshed = $this->refreshAccessToken($refreshToken);

        $metadata['token_expires_at'] = now()
            ->addSeconds(max(60, $refreshed['expires_in'] - 60))
            ->toIso8601String();

        $integration->update([
            'api_token' => $refreshed['access_token'],
            'metadata' => $metadata,
        ]);

        return $refreshed['access_token'];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function errorMessage(?array $payload, string $fallback): string
    {
        $error = $payload['error_description']
            ?? $payload['error']['message']
            ?? $payload['error']
            ?? null;

        if (is_string($error) && trim($error) !== '') {
            return trim($error);
        }

        return $fallback;
    }
}
