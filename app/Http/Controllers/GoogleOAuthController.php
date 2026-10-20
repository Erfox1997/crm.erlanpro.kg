<?php

namespace App\Http\Controllers;

use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    public function __construct(
        private GoogleOAuthService $oauth,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([
                    'google' => __('Укажите GOOGLE_CLIENT_ID и GOOGLE_CLIENT_SECRET в .env на сервере.'),
                ]);
        }

        try {
            $state = Str::random(40);
            $oauthUrl = $this->oauth->authorizationUrl($state);
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['google' => $e->getMessage()]);
        }

        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_oauth_company_id', (int) $request->user()->company_id);

        return redirect()->away($oauthUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('integrations.index')
                ->withErrors([
                    'google' => (string) ($request->query('error_description') ?: $request->query('error')),
                ]);
        }

        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        $companyId = (int) $request->session()->pull('google_oauth_company_id', 0);
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['google' => __('Сессия OAuth истекла. Попробуйте подключить снова.')]);
        }

        if ($companyId !== (int) $request->user()->company_id) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['google' => __('Компания не совпадает. Войдите в нужный аккаунт CRM и повторите.')]);
        }

        if ($code === '') {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['google' => __('Google не вернула код авторизации.')]);
        }

        try {
            $token = $this->oauth->exchangeCode($code);
            $user = $this->oauth->fetchUserInfo($token['access_token']);
            $this->oauth->persistIntegration($companyId, $token, $user);
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['google' => $e->getMessage()]);
        }

        return redirect()
            ->route('integrations.index')
            ->with('success', __('Google Contacts подключён. Имя и телефон клиентов будут сохраняться в контакты этого аккаунта.'));
    }
}
