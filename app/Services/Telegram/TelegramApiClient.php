<?php

namespace App\Services\Telegram;

use App\Models\CompanyIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelegramApiClient
{
    public function get(CompanyIntegration $integration, string $method, array $query = []): Response
    {
        return $this->request($integration)
            ->get($this->url($integration, $method), $query);
    }

    public function postJson(CompanyIntegration $integration, string $method, array $body = []): Response
    {
        return $this->request($integration)
            ->post($this->url($integration, $method), $body);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function postWithFile(
        CompanyIntegration $integration,
        string $method,
        array $fields,
        string $fileField,
        string $contents,
        string $filename,
    ): Response {
        return $this->request($integration)
            ->attach($fileField, $contents, $filename)
            ->post($this->url($integration, $method), $fields);
    }

    protected function request(CompanyIntegration $integration): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('services.telegram.timeout', 60));
    }

    protected function url(CompanyIntegration $integration, string $method): string
    {
        $token = self::normalizeBotToken((string) $integration->api_token);

        return 'https://api.telegram.org/bot'.$token.'/'.ltrim($method, '/');
    }

    public static function normalizeBotToken(string $token): string
    {
        $token = trim($token);

        if (str_starts_with(strtolower($token), 'bot')) {
            $token = substr($token, 3);
        }

        return trim($token);
    }
}
