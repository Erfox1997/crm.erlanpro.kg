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

    public static function graphUrl(string $path): string
    {
        if ($path === 'oauth/access_token') {
            return 'https://graph.facebook.com/'.self::graphVersion().'/oauth/access_token';
        }

        return 'https://graph.facebook.com/'.self::graphVersion().'/'.ltrim($path, '/');
    }
}
