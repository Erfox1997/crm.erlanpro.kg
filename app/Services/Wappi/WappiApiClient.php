<?php

namespace App\Services\Wappi;

use App\Models\CompanyIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WappiApiClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.wappi.base_url', 'https://wappi.pro'), '/');
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public function get(CompanyIntegration $integration, string $path, array $query = []): Response
    {
        return $this->request($integration)
            ->withQueryParameters($this->query($integration, $query))
            ->get($this->url($path));
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, mixed>  $body
     */
    public function postJson(CompanyIntegration $integration, string $path, array $body = [], array $query = []): Response
    {
        return $this->request($integration)
            ->withQueryParameters($this->query($integration, $query))
            ->post($this->url($path), $body);
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public function post(CompanyIntegration $integration, string $path, array $query = []): Response
    {
        return $this->request($integration)
            ->withQueryParameters($this->query($integration, $query))
            ->post($this->url($path));
    }

    protected function request(CompanyIntegration $integration): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('services.wappi.timeout', 60))
            ->withHeaders([
                'Authorization' => trim((string) $integration->api_token),
            ]);
    }

    protected function url(string $path): string
    {
        return $this->baseUrl().'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, scalar|null>
     */
    protected function query(CompanyIntegration $integration, array $query = []): array
    {
        $profileId = trim((string) ($integration->metadata['profile_id'] ?? ''));

        return array_filter([
            'profile_id' => $profileId !== '' ? $profileId : null,
            ...$query,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
