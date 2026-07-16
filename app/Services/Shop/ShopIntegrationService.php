<?php

namespace App\Services\Shop;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ShopIntegrationService
{
    public function integrationForCompany(int $companyId): ?CompanyIntegration
    {
        return CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', IntegrationProvider::Shop->value)
            ->first();
    }

    public function isConnected(int $companyId): bool
    {
        $integration = $this->integrationForCompany($companyId);

        return $integration !== null
            && filled($integration->api_token)
            && filled($integration->metadata['shop_url'] ?? null);
    }

    /**
     * @return array{api_token: string, metadata: array<string, mixed>}
     */
    public function connectFromCredentials(string $shopUrl, string $apiToken): array
    {
        $shopUrl = $this->normalizeShopUrl($shopUrl);
        $apiToken = trim($apiToken);

        if ($apiToken === '') {
            throw ValidationException::withMessages([
                'api_token' => __('Укажите API-ключ магазина.'),
            ]);
        }

        try {
            $response = $this->client($shopUrl, $apiToken)
                ->get('/api/crm/v1/ping')
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw ValidationException::withMessages([
                'api_token' => __('Магазин отклонил ключ: :msg', [
                    'msg' => $e->response?->json('message') ?: $e->getMessage(),
                ]),
            ]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'shop_url' => __('Не удалось подключиться к магазину: :msg', [
                    'msg' => $e->getMessage(),
                ]),
            ]);
        }

        return [
            'api_token' => $apiToken,
            'metadata' => [
                'shop_url' => $shopUrl,
                'shop_name' => $response['shop_name'] ?? null,
                'currency' => $response['currency'] ?? 'KGS',
                'tenant_id' => $response['tenant_id'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchCatalog(CompanyIntegration $integration): array
    {
        return $this->request($integration, 'get', '/api/crm/v1/catalog');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSale(CompanyIntegration $integration, array $payload): array
    {
        return $this->request($integration, 'post', '/api/crm/v1/sales', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateSale(CompanyIntegration $integration, int $shopDocumentId, array $payload): array
    {
        return $this->request($integration, 'put', '/api/crm/v1/sales/'.$shopDocumentId, $payload);
    }

    public function deleteSale(CompanyIntegration $integration, int $shopDocumentId): void
    {
        $this->request($integration, 'delete', '/api/crm/v1/sales/'.$shopDocumentId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function request(
        CompanyIntegration $integration,
        string $method,
        string $path,
        ?array $payload = null,
    ): array {
        $shopUrl = $this->normalizeShopUrl((string) ($integration->metadata['shop_url'] ?? ''));
        $token = (string) $integration->api_token;

        if ($shopUrl === '' || $token === '') {
            throw ValidationException::withMessages([
                'shop' => __('Интеграция с магазином не настроена.'),
            ]);
        }

        try {
            $pending = $this->client($shopUrl, $token);

            $response = match (strtolower($method)) {
                'get' => $pending->get($path),
                'post' => $pending->post($path, $payload ?? []),
                'put' => $pending->put($path, $payload ?? []),
                'delete' => $pending->delete($path),
                default => throw new \InvalidArgumentException('Unsupported HTTP method'),
            };

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException $e) {
            $message = $e->response?->json('message')
                ?? collect($e->response?->json('errors') ?? [])->flatten()->first()
                ?? $e->getMessage();

            throw ValidationException::withMessages([
                'shop' => __('Ошибка магазина: :msg', ['msg' => $message]),
            ]);
        }
    }

    protected function client(string $shopUrl, string $apiToken): PendingRequest
    {
        return Http::baseUrl($shopUrl)
            ->acceptJson()
            ->withToken($apiToken)
            ->timeout(30);
    }

    public function normalizeShopUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }
}
