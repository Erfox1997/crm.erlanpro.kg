<?php

namespace App\Services\Meta;

use App\Enums\IntegrationProvider;
use Illuminate\Support\Facades\Http;

class MetaOAuthService
{
    public function graphVersion(): string
    {
        return (string) config('services.meta.graph_version', 'v21.0');
    }

    public function oauthRedirectUri(string $callbackRouteName): string
    {
        $configured = config('services.meta.oauth_redirect_uri');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return route($callbackRouteName, absolute: true);
    }

    public function oauthAuthorizationUrl(string $state, string $callbackRouteName): string
    {
        $appId = MetaMessagingSupport::normalizeAppId((string) config('services.instagram.app_id'));
        if ($appId === '') {
            throw new \RuntimeException(__('INSTAGRAM_APP_ID не задан в .env'));
        }

        if (! preg_match('/^\d{10,20}$/', $appId)) {
            throw new \RuntimeException(__('INSTAGRAM_APP_ID должен быть числовым ID приложения из Meta → Настройки → Основное → ID приложения.'));
        }

        $query = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $this->oauthRedirectUri($callbackRouteName),
            'scope' => (string) config('services.meta.oauth_scopes'),
            'response_type' => 'code',
            'state' => $state,
        ]);

        return 'https://www.facebook.com/dialog/oauth?'.$query;
    }

    public function exchangeFacebookCodeForLongLivedUserToken(string $code, string $callbackRouteName): string
    {
        $shortLivedToken = $this->requestFacebookAccessToken([
            'client_id' => config('services.instagram.app_id'),
            'client_secret' => config('services.instagram.app_secret'),
            'redirect_uri' => $this->oauthRedirectUri($callbackRouteName),
            'code' => $code,
        ]);

        return $this->requestFacebookAccessToken([
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.instagram.app_id'),
            'client_secret' => config('services.instagram.app_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);
    }

    /**
     * @return list<array{
     *     page_id: string,
     *     page_name: ?string,
     *     access_token: string,
     *     instagram_user_id: ?string,
     *     username: ?string,
     *     name: ?string
     * }>
     */
    public function listConnectablePages(string $userAccessToken, IntegrationProvider $provider): array
    {
        $response = MetaMessagingSupport::client($userAccessToken)->get(
            MetaMessagingSupport::graphUrl('me/accounts'),
            ['fields' => 'id,name,access_token,instagram_business_account{id,username,name}'],
        );

        $response->throw();

        $pages = [];

        foreach ($response->json('data', []) as $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageToken = (string) ($page['access_token'] ?? '');
            $pageId = (string) ($page['id'] ?? '');

            if ($pageId === '' || $pageToken === '') {
                continue;
            }

            $igAccount = $page['instagram_business_account'] ?? null;
            $igId = is_array($igAccount) ? (string) ($igAccount['id'] ?? '') : '';

            if ($provider === IntegrationProvider::Instagram && $igId === '') {
                continue;
            }

            $pages[] = [
                'page_id' => $pageId,
                'page_name' => $page['name'] ?? null,
                'access_token' => $pageToken,
                'instagram_user_id' => $igId !== '' ? $igId : null,
                'username' => is_array($igAccount) ? ($igAccount['username'] ?? null) : null,
                'name' => is_array($igAccount) ? ($igAccount['name'] ?? null) : null,
            ];
        }

        return $pages;
    }

    /**
     * @param  array{
     *     page_id: string,
     *     page_name: ?string,
     *     access_token: string,
     *     instagram_user_id: ?string,
     *     username: ?string,
     *     name: ?string
     * }  $page
     * @return array{api_token: string, metadata: array<string, mixed>}
     */
    public function buildIntegrationFromPage(array $page, IntegrationProvider $provider, string $connectedVia = 'oauth'): array
    {
        if ($provider === IntegrationProvider::Instagram) {
            if (empty($page['instagram_user_id'])) {
                throw new \RuntimeException(__('У выбранной страницы нет привязанного Instagram.'));
            }

            return [
                'api_token' => $page['access_token'],
                'metadata' => [
                    'instagram_user_id' => (string) $page['instagram_user_id'],
                    'username' => $page['username'] ?? null,
                    'name' => $page['name'] ?? null,
                    'page_id' => (string) $page['page_id'],
                    'page_name' => $page['page_name'] ?? null,
                    'auth_mode' => 'facebook_login',
                    'connected_via' => $connectedVia,
                ],
            ];
        }

        return [
            'api_token' => $page['access_token'],
            'metadata' => [
                'page_id' => (string) $page['page_id'],
                'page_name' => $page['page_name'] ?? null,
                'auth_mode' => 'facebook_login',
                'connected_via' => $connectedVia,
            ],
        ];
    }

    /**
     * @param  array<string, string|null>  $params
     */
    protected function requestFacebookAccessToken(array $params): string
    {
        $response = Http::acceptJson()
            ->timeout(30)
            ->get(MetaMessagingSupport::graphUrl('oauth/access_token'), array_filter($params));

        $response->throw();

        $token = (string) ($response->json('access_token') ?? '');
        if ($token === '') {
            throw new \RuntimeException(__('Meta не вернула access token.'));
        }

        return $token;
    }
}
