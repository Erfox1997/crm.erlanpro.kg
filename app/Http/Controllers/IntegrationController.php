<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Services\Instagram\InstagramMessengerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $stored = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('provider');

        $integrations = collect(IntegrationProvider::cases())->map(function (IntegrationProvider $provider) use ($stored) {
            $record = $stored->get($provider->value);
            $hasToken = $record !== null && $record->api_token !== null && $record->api_token !== '';

            $item = [
                'provider' => $provider->value,
                'name' => $provider->label(),
                'description' => $provider->description(),
                'has_token' => $hasToken,
            ];

            if ($provider === IntegrationProvider::Instagram) {
                $appId = InstagramMessengerService::normalizeAppId((string) config('services.instagram.app_id'));
                $item['oauth_url'] = route('integrations.instagram.oauth');
                $item['oauth_callback_url'] = app(InstagramMessengerService::class)->oauthRedirectUri();
                $item['webhook_url'] = url('/webhooks/meta');
                $item['meta_app_id'] = $appId !== '' ? $appId : null;
                $item['account'] = $hasToken ? [
                    'username' => $record->metadata['username'] ?? null,
                    'name' => $record->metadata['name'] ?? null,
                    'connected_via' => $record->metadata['connected_via'] ?? 'manual',
                ] : null;
            }

            return $item;
        })->values();

        return Inertia::render('Integrations/Index', [
            'integrations' => $integrations,
            'pageTitle' => 'Интеграции',
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $integrationProvider = IntegrationProvider::tryFromSlug($provider);
        abort_unless($integrationProvider !== null, 404);

        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'api_token' => 'required|string|max:2000',
        ]);

        $apiToken = $validated['api_token'];
        $attributes = ['api_token' => $apiToken];

        if ($integrationProvider === IntegrationProvider::Instagram) {
            $apiToken = InstagramMessengerService::normalizeAccessToken($apiToken);

            try {
                $connection = app(InstagramMessengerService::class)->connectAccountFromManualToken($apiToken);
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'api_token' => __('Instagram API отклонил маркер: :msg', [
                        'msg' => $e->getMessage(),
                    ]),
                ]);
            }

            $attributes = [
                'api_token' => $connection['api_token'],
                'metadata' => $connection['metadata'],
            ];
        }

        $integration = CompanyIntegration::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'provider' => $integrationProvider->value,
            ],
            $attributes,
        );

        return back()->with('success', __('Токен :name сохранён.', [
            'name' => $integrationProvider->label(),
        ]));
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $integrationProvider = IntegrationProvider::tryFromSlug($provider);
        abort_unless($integrationProvider !== null, 404);

        $companyId = (int) $request->user()->company_id;

        CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', $integrationProvider->value)
            ->delete();

        return back()->with('success', __('Интеграция :name отключена.', [
            'name' => $integrationProvider->label(),
        ]));
    }
}
