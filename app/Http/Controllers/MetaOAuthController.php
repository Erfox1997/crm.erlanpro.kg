<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Services\Meta\MetaOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MetaOAuthController extends Controller
{
    public function __construct(
        private MetaOAuthService $oauth,
    ) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $integrationProvider = $this->resolveProvider($provider);

        if (! config('services.instagram.app_id') || ! config('services.instagram.app_secret')) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([
                    $provider => __('Укажите INSTAGRAM_APP_ID и INSTAGRAM_APP_SECRET в .env на сервере.'),
                ]);
        }

        try {
            $oauthUrl = $this->oauth->oauthAuthorizationUrl(
                $state = Str::random(40),
                $this->callbackRoute($integrationProvider),
                $integrationProvider,
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider => $e->getMessage()]);
        }

        $request->session()->put('meta_oauth_state', $state);
        $request->session()->put('meta_oauth_company_id', (int) $request->user()->company_id);
        $request->session()->put('meta_oauth_provider', $integrationProvider->value);

        return redirect()->away($oauthUrl);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $integrationProvider = $this->resolveProvider($provider);

        if ($request->filled('error')) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([
                    $provider => (string) ($request->query('error_description') ?: $request->query('error')),
                ]);
        }

        $expectedState = (string) $request->session()->get('meta_oauth_state', '');
        $companyId = (int) $request->session()->get('meta_oauth_company_id', 0);
        $sessionProvider = (string) $request->session()->get('meta_oauth_provider', '');
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider => __('Сессия OAuth истекла. Попробуйте подключить снова.')]);
        }

        if ($companyId !== (int) $request->user()->company_id) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider => __('Компания не совпадает. Войдите в нужный аккаунт CRM и повторите.')]);
        }

        if ($sessionProvider !== $integrationProvider->value) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider => __('Неверный провайдер OAuth.')]);
        }

        if ($code === '') {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider => __('Meta не вернула код авторизации.')]);
        }

        if ($this->oauth->usesInstagramLogin($integrationProvider)) {
            try {
                $token = $this->oauth->exchangeInstagramCodeForLongLivedUserToken(
                    $code,
                    $this->callbackRoute($integrationProvider),
                );
                $account = $this->oauth->fetchInstagramLoginAccount($token['access_token'], $token['user_id']);
                $connection = $this->oauth->buildIntegrationFromInstagramLogin($account, $token['access_token']);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('integrations.index')
                    ->withErrors([$provider => $e->getMessage()]);
            }

            $request->session()->forget('meta_oauth_state');
            $this->oauth->subscribeInstagramWebhooks($account['instagram_user_id'], $token['access_token']);

            return $this->persistIntegration($request, $integrationProvider, $connection);
        }

        try {
            $userToken = $this->oauth->exchangeFacebookCodeForLongLivedUserToken(
                $code,
                $this->callbackRoute($integrationProvider),
            );
            $pages = $this->oauth->listConnectablePages($userToken, $integrationProvider);
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider => $e->getMessage()]);
        }

        if ($pages === []) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([
                    $provider => $integrationProvider === IntegrationProvider::Instagram
                        ? __('Не найдена Facebook-страница с привязанным Instagram.')
                        : __('Не найдены Facebook-страницы для подключения.'),
                ]);
        }

        $request->session()->forget('meta_oauth_state');
        $request->session()->put('meta_oauth_pages', $pages);

        if (count($pages) === 1) {
            return $this->saveIntegration($request, $integrationProvider, $pages[0]['page_id']);
        }

        return redirect()->route('integrations.meta.oauth.select-page', [
            'provider' => $integrationProvider->value,
        ]);
    }

    public function selectPage(Request $request, string $provider): Response|RedirectResponse
    {
        $integrationProvider = $this->resolveProvider($provider);
        $pages = $request->session()->get('meta_oauth_pages', []);

        if (! is_array($pages) || $pages === []) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider => __('Сессия выбора страницы истекла. Подключите снова через OAuth.')]);
        }

        return Inertia::render('Integrations/OAuthSelectPage', [
            'provider' => $integrationProvider->value,
            'providerLabel' => $integrationProvider->label(),
            'pages' => collect($pages)->map(fn (array $page) => [
                'page_id' => $page['page_id'],
                'page_name' => $page['page_name'],
                'instagram_username' => $page['username'] ?? null,
            ])->values(),
        ]);
    }

    public function storeSelectedPage(Request $request, string $provider): RedirectResponse
    {
        $integrationProvider = $this->resolveProvider($provider);

        $validated = $request->validate([
            'page_ids' => 'required|array|min:1',
            'page_ids.*' => 'required|string',
        ]);

        return $this->saveIntegrations($request, $integrationProvider, $validated['page_ids']);
    }

    /**
     * @param  list<string>  $pageIds
     */
    protected function saveIntegrations(Request $request, IntegrationProvider $provider, array $pageIds): RedirectResponse
    {
        $pages = $request->session()->pull('meta_oauth_pages', []);

        if (! is_array($pages)) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider->value => __('Сессия OAuth истекла.')]);
        }

        $saved = 0;
        $errors = [];

        foreach ($pageIds as $pageId) {
            $selected = collect($pages)->firstWhere('page_id', $pageId);

            if (! is_array($selected)) {
                $errors[] = __('Страница :id не найдена.', ['id' => $pageId]);

                continue;
            }

            try {
                $this->oauth->subscribePageWebhooks(
                    (string) $selected['page_id'],
                    (string) $selected['access_token'],
                );
                $connection = $this->oauth->buildIntegrationFromPage($selected, $provider);
                $connection['metadata']['webhook_subscribed_fields'] = ['messages'];
                $this->persistIntegrationRecord($request, $provider, $connection);
                $saved++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $request->session()->forget(['meta_oauth_company_id', 'meta_oauth_provider']);

        if ($saved === 0) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([
                    $provider->value => $errors[0] ?? __('Не удалось подключить страницы.'),
                ]);
        }

        $label = $provider === IntegrationProvider::Instagram
            ? __('Instagram: подключено страниц — :count.', ['count' => $saved])
            : __('Facebook: подключено страниц — :count.', ['count' => $saved]);

        return redirect()
            ->route('integrations.index')
            ->with('success', $label)
            ->withErrors($errors === [] ? [] : [$provider->value => implode(' ', $errors)]);
    }

    protected function saveIntegration(Request $request, IntegrationProvider $provider, string $pageId): RedirectResponse
    {
        return $this->saveIntegrations($request, $provider, [$pageId]);
    }

    /**
     * @param  array{api_token: string, metadata: array<string, mixed>}  $connection
     */
    protected function persistIntegration(
        Request $request,
        IntegrationProvider $provider,
        array $connection,
    ): RedirectResponse {
        try {
            $this->persistIntegrationRecord($request, $provider, $connection);
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([$provider->value => $e->getMessage()]);
        }

        $request->session()->forget(['meta_oauth_company_id', 'meta_oauth_provider']);

        $label = $provider === IntegrationProvider::Instagram
            ? __('Instagram подключён через Meta OAuth.')
            : __('Facebook подключён через Meta OAuth.');

        return redirect()
            ->route('integrations.index')
            ->with('success', $label);
    }

    /**
     * @param  array{api_token: string, metadata: array<string, mixed>}  $connection
     */
    protected function persistIntegrationRecord(
        Request $request,
        IntegrationProvider $provider,
        array $connection,
    ): CompanyIntegration {
        $companyId = (int) $request->session()->get(
            'meta_oauth_company_id',
            (int) $request->user()->company_id,
        );

        return CompanyIntegration::upsertForCompany(
            $companyId,
            $provider->value,
            $connection,
        );
    }

    protected function resolveProvider(string $provider): IntegrationProvider
    {
        $integrationProvider = IntegrationProvider::tryFromSlug($provider);

        if ($integrationProvider === null || ! in_array($integrationProvider, [IntegrationProvider::Instagram, IntegrationProvider::Facebook], true)) {
            abort(404);
        }

        return $integrationProvider;
    }

    protected function callbackRoute(IntegrationProvider $provider): string
    {
        return match ($provider) {
            IntegrationProvider::Instagram => 'integrations.instagram.callback',
            IntegrationProvider::Facebook => 'integrations.facebook.callback',
            default => 'integrations.instagram.callback',
        };
    }
}
