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

    public function oauthAuthorizationUrl(
        string $state,
        string $callbackRouteName,
        IntegrationProvider $provider,
    ): string {
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
            'scope' => $this->oauthScopes($provider),
            'response_type' => 'code',
            'state' => $state,
        ]);

        return 'https://www.facebook.com/dialog/oauth?'.$query;
    }

    public function oauthScopes(IntegrationProvider $provider): string
    {
        return match ($provider) {
            IntegrationProvider::Facebook => (string) config(
                'services.meta.oauth_scopes_facebook',
                'public_profile,pages_show_list,pages_read_engagement,pages_manage_metadata,pages_messaging',
            ),
            IntegrationProvider::Instagram => (string) config(
                'services.meta.oauth_scopes_instagram',
                config('services.meta.oauth_scopes'),
            ),
            default => (string) config('services.meta.oauth_scopes'),
        };
    }

    /**
     * @return array{
     *     ok: bool,
     *     issues: list<string>,
     *     app: ?array<string, mixed>,
     *     redirect_uris: list<string>
     * }
     */
    public function appDiagnostics(): array
    {
        $issues = [];
        $redirectUris = [
            route('integrations.instagram.callback', absolute: true),
            route('integrations.facebook.callback', absolute: true),
        ];

        $appId = MetaMessagingSupport::normalizeAppId((string) config('services.instagram.app_id'));
        $appSecret = trim((string) config('services.instagram.app_secret'));

        if ($appId === '') {
            $issues[] = __('INSTAGRAM_APP_ID не задан в .env на сервере.');
        }

        if ($appSecret === '') {
            $issues[] = __('INSTAGRAM_APP_SECRET не задан в .env на сервере.');
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if (! str_starts_with($appUrl, 'https://')) {
            $issues[] = __('APP_URL должен быть https://crm.erlanpro.kg (сейчас: :url).', ['url' => $appUrl ?: '—']);
        }

        $issues[] = __('В Meta → Facebook Login добавьте оба Redirect URI (см. ниже на карточках интеграций).');
        $issues[] = __('В Meta → Роли приложения ваш Facebook-аккаунт должен быть Администратор или Разработчик (не только Instagram Tester).');
        $issues[] = __('Входите в OAuth тем же Facebook-аккаунтом, который админ приложения Meta и страницы ErlanPro.');

        if ($appId === '' || $appSecret === '') {
            return [
                'ok' => false,
                'issues' => $issues,
                'app' => null,
                'redirect_uris' => $redirectUris,
            ];
        }

        $app = null;

        try {
            $tokenResponse = Http::acceptJson()
                ->timeout(15)
                ->get(MetaMessagingSupport::graphUrl('oauth/access_token'), [
                    'client_id' => $appId,
                    'client_secret' => $appSecret,
                    'grant_type' => 'client_credentials',
                ]);

            $tokenResponse->throw();
            $appToken = (string) ($tokenResponse->json('access_token') ?? '');

            if ($appToken === '') {
                $issues[] = __('Meta не выдала app access token — проверьте INSTAGRAM_APP_ID и INSTAGRAM_APP_SECRET.');
            } else {
                $appResponse = Http::acceptJson()
                    ->timeout(15)
                    ->get(MetaMessagingSupport::graphUrl($appId), [
                        'fields' => 'id,name,app_domains,website,privacy_policy_url,restrictions',
                        'access_token' => $appToken,
                    ]);

                $appResponse->throw();
                $app = $appResponse->json();

                if (! is_string($app['privacy_policy_url'] ?? null) || trim($app['privacy_policy_url']) === '') {
                    $issues[] = __('В Meta → Настройки → Основное не указан URL политики конфиденциальности — из‑за этого Facebook часто показывает «Этот контент сейчас недоступен».');
                }

                $host = parse_url($appUrl, PHP_URL_HOST);
                $domains = is_array($app['app_domains'] ?? null) ? $app['app_domains'] : [];

                if (is_string($host) && $host !== '' && ! in_array($host, $domains, true)) {
                    $issues[] = __('Домен :host не найден в Meta → Основное → Домены приложений.', ['host' => $host]);
                }

                $restrictions = $app['restrictions'] ?? null;
                if (is_array($restrictions) && $restrictions !== []) {
                    $issues[] = __('Meta ограничила приложение: :details', [
                        'details' => json_encode($restrictions, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $issues[] = __('Не удалось проверить приложение Meta: :msg', ['msg' => $e->getMessage()]);
        }

        $criticalPatterns = [
            'INSTAGRAM_APP_ID',
            'INSTAGRAM_APP_SECRET',
            'APP_URL',
            'политики конфиденциальности',
            'Домен',
            'ограничила',
            'не выдала app access token',
        ];

        $criticalIssues = array_values(array_filter(
            $issues,
            fn (string $issue) => collect($criticalPatterns)->contains(
                fn (string $pattern) => str_contains($issue, $pattern),
            ),
        ));

        return [
            'ok' => $criticalIssues === [],
            'issues' => $issues,
            'app' => is_array($app) ? [
                'id' => $app['id'] ?? null,
                'name' => $app['name'] ?? null,
                'website' => $app['website'] ?? null,
                'privacy_policy_url' => $app['privacy_policy_url'] ?? null,
                'app_domains' => $app['app_domains'] ?? [],
            ] : null,
            'redirect_uris' => $redirectUris,
        ];
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
