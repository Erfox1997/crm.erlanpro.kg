<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MetaMessagingSupport
{
    public static function graphVersion(): string
    {
        return (string) config('services.meta.graph_version', 'v21.0');
    }

    public static function normalizeAppId(string $appId): string
    {
        return trim($appId, " \t\n\r\0\x0B\"'");
    }

    public static function normalizeAccessToken(string $token): string
    {
        $token = trim($token);
        $token = preg_replace('/\s+/', '', $token) ?? $token;

        if (str_starts_with(strtolower($token), 'bearer')) {
            $token = trim(substr($token, 6));
        }

        return trim($token, " \t\n\r\0\x0B\"'");
    }

    public static function client(string $accessToken): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->withToken(self::normalizeAccessToken($accessToken));
    }

    public static function graphUrl(string $path, ?string $platform = null): string
    {
        if ($path === 'oauth/access_token') {
            return 'https://graph.facebook.com/'.self::graphVersion().'/oauth/access_token';
        }

        $url = 'https://graph.facebook.com/'.self::graphVersion().'/'.ltrim($path, '/');

        if ($platform === 'instagram') {
            $url .= '?platform=instagram';
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function formatGraphError(?array $body, string $fallback = ''): string
    {
        $error = $body['error'] ?? null;
        if (! is_array($error)) {
            return $fallback !== '' ? $fallback : __('Ошибка Meta API.');
        }

        $message = (string) ($error['message'] ?? $fallback);
        $code = (int) ($error['code'] ?? 0);
        $subcode = (int) ($error['error_subcode'] ?? 0);

        if ($code === 10 && $subcode === 2534022) {
            return __('Сообщение вне 24-часового окна Instagram. Клиент должен написать вам первым, затем ответьте в течение суток.');
        }

        if ($code === 10 && $subcode === 2534048) {
            return __('Нет прав instagram_manage_messages (Advanced Access) или получатель не добавлен как тестировщик приложения Meta.');
        }

        if ($code === 200 && $subcode === 2534048) {
            return __('Приложение Meta не одобрено для instagram_manage_messages или у получателя нет роли в приложении.');
        }

        if ($code === 10) {
            return $message.' '.__('Проверьте права приложения Meta и что клиент писал в Direct недавно.');
        }

        return $message !== '' ? $message : ($fallback !== '' ? $fallback : __('Ошибка Meta API.'));
    }
}
