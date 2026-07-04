<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Services\Instagram\InstagramMessengerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstagramOAuthController extends Controller
{
    public function __construct(
        private InstagramMessengerService $instagram,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! config('services.instagram.app_id') || ! config('services.instagram.app_secret')) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([
                    'instagram' => __('Укажите INSTAGRAM_APP_ID и INSTAGRAM_APP_SECRET в .env на сервере.'),
                ]);
        }

        try {
            $oauthUrl = $this->instagram->oauthAuthorizationUrl($state = Str::random(40));
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['instagram' => $e->getMessage()]);
        }

        $request->session()->put('instagram_oauth_state', $state);
        $request->session()->put('instagram_oauth_company_id', (int) $request->user()->company_id);

        return redirect()->away($oauthUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([
                    'instagram' => (string) ($request->query('error_description') ?: $request->query('error')),
                ]);
        }

        $expectedState = (string) $request->session()->pull('instagram_oauth_state', '');
        $companyId = (int) $request->session()->pull('instagram_oauth_company_id', 0);
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['instagram' => __('Сессия OAuth истекла. Попробуйте подключить снова.')]);
        }

        if ($companyId !== (int) $request->user()->company_id) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['instagram' => __('Компания не совпадает. Войдите в нужный аккаунт CRM и повторите.')]);
        }

        if ($code === '') {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['instagram' => __('Meta не вернула код авторизации.')]);
        }

        try {
            $connection = $this->instagram->connectAccountFromOAuth(
                $this->instagram->exchangeCodeForLongLivedUserToken($code),
            );

            CompanyIntegration::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'provider' => IntegrationProvider::Instagram->value,
                ],
                [
                    'api_token' => $connection['api_token'],
                    'metadata' => $connection['metadata'],
                ],
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['instagram' => $e->getMessage()]);
        }

        return redirect()
            ->route('integrations.index')
            ->with('success', __('Instagram подключён через Meta OAuth.'));
    }
}
