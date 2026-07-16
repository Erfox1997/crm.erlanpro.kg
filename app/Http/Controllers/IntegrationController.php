<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Services\ChatGpt\ChatGptService;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use App\Services\Meta\MetaMessagingSupport;
use App\Services\Shop\ShopIntegrationService;
use App\Services\Telegram\TelegramMessengerService;
use App\Services\Wappi\WappiMessengerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            $hasToken = match ($provider) {
                IntegrationProvider::Wappi => $record !== null
                    && filled($record->api_token)
                    && filled($record->metadata['profile_id'] ?? null),
                IntegrationProvider::Telegram => $record !== null
                    && filled($record->api_token)
                    && filled($record->metadata['bot_id'] ?? null),
                IntegrationProvider::Shop => $record !== null
                    && filled($record->api_token)
                    && filled($record->metadata['shop_url'] ?? null),
                default => $record !== null && filled($record->api_token),
            };

            $item = [
                'provider' => $provider->value,
                'name' => $provider->label(),
                'description' => $provider->description(),
                'has_token' => $hasToken,
            ];

            if (in_array($provider, [IntegrationProvider::Instagram, IntegrationProvider::Facebook], true)) {
                $item['oauth_url'] = route("integrations.{$provider->value}.oauth");
            }

            if ($provider === IntegrationProvider::Instagram && $hasToken) {
                $item['account'] = [
                    'username' => $record->metadata['username'] ?? null,
                    'name' => $record->metadata['name'] ?? null,
                    'page_name' => $record->metadata['page_name'] ?? null,
                    'connected_via' => $record->metadata['connected_via'] ?? 'manual',
                ];
            }

            if ($provider === IntegrationProvider::Facebook && $hasToken) {
                $item['account'] = [
                    'page_name' => $record->metadata['page_name'] ?? null,
                    'page_id' => $record->metadata['page_id'] ?? null,
                    'connected_via' => $record->metadata['connected_via'] ?? 'manual',
                ];
            }

            if ($provider === IntegrationProvider::Wappi) {
                $item['profile_id'] = $record?->metadata['profile_id'] ?? null;

                if ($hasToken) {
                    $item['account'] = [
                        'name' => $record->metadata['profile_name'] ?? null,
                        'profile_id' => $record->metadata['profile_id'] ?? null,
                    ];
                }
            }

            if ($provider === IntegrationProvider::Telegram && $hasToken) {
                $item['account'] = [
                    'username' => $record->metadata['bot_username'] ?? null,
                    'name' => $record->metadata['bot_name'] ?? null,
                ];
                $item['webhook_url'] = filled($record->metadata['webhook_secret'] ?? null)
                    ? route('webhooks.telegram.handle', ['secret' => $record->metadata['webhook_secret']])
                    : null;
            }

            if ($provider === IntegrationProvider::ChatGpt) {
                $item['model'] = $record?->metadata['model']
                    ?? config('services.openai.model', 'gpt-4.1-mini');

                if ($hasToken) {
                    $item['account'] = [
                        'name' => $item['model'],
                    ];
                }
            }

            if ($provider === IntegrationProvider::Shop) {
                $item['shop_url'] = $record?->metadata['shop_url'] ?? '';

                if ($hasToken) {
                    $item['account'] = [
                        'name' => $record->metadata['shop_name'] ?? $item['shop_url'],
                    ];
                }
            }

            return $item;
        })->values();

        return Inertia::render('Integrations/Index', [
            'integrations' => $integrations,
            'pageTitle' => 'Интеграции',
            'wappiWebhookUrl' => route('webhooks.wappi.handle'),
            'chatGptModels' => app(ChatGptService::class)->preferredModels(),
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $integrationProvider = IntegrationProvider::tryFromSlug($provider);
        abort_unless($integrationProvider !== null, 404);

        $companyId = (int) $request->user()->company_id;

        $rules = [
            'api_token' => 'required|string|max:2000',
        ];

        if ($integrationProvider === IntegrationProvider::ChatGpt) {
            $rules = [
                'api_token' => 'nullable|string|max:2000',
                'model' => 'nullable|string|max:100',
            ];
        }

        if ($integrationProvider === IntegrationProvider::Wappi) {
            $rules['profile_id'] = 'required|string|max:255';
        }

        if ($integrationProvider === IntegrationProvider::Shop) {
            $rules = [
                'api_token' => 'required|string|max:2000',
                'shop_url' => 'required|string|max:500',
            ];
        }

        $validated = $request->validate($rules);

        $apiToken = (string) ($validated['api_token'] ?? '');
        $attributes = ['api_token' => $apiToken];

        if ($integrationProvider === IntegrationProvider::Wappi) {
            $existing = CompanyIntegration::query()
                ->where('company_id', $companyId)
                ->where('provider', $integrationProvider->value)
                ->first();

            $metadata = $existing?->metadata ?? [];
            $metadata['profile_id'] = trim($validated['profile_id']);

            $attributes['metadata'] = $metadata;
        }

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

        if ($integrationProvider === IntegrationProvider::Facebook) {
            $apiToken = MetaMessagingSupport::normalizeAccessToken($apiToken);

            try {
                $connection = app(FacebookMessengerService::class)->connectAccountFromManualToken($apiToken);
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'api_token' => __('Facebook API отклонил маркер: :msg', [
                        'msg' => $e->getMessage(),
                    ]),
                ]);
            }

            $attributes = [
                'api_token' => $connection['api_token'],
                'metadata' => $connection['metadata'],
            ];
        }

        if ($integrationProvider === IntegrationProvider::Telegram) {
            $existing = CompanyIntegration::query()
                ->where('company_id', $companyId)
                ->where('provider', $integrationProvider->value)
                ->first();

            try {
                $connection = app(TelegramMessengerService::class)->connectFromToken(
                    $apiToken,
                    $existing?->metadata,
                );
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'api_token' => __('Telegram API отклонил токен: :msg', [
                        'msg' => $e->getMessage(),
                    ]),
                ]);
            }

            $attributes = [
                'api_token' => $connection['api_token'],
                'metadata' => $connection['metadata'],
            ];
        }

        if ($integrationProvider === IntegrationProvider::ChatGpt) {
            $existing = CompanyIntegration::query()
                ->where('company_id', $companyId)
                ->where('provider', $integrationProvider->value)
                ->first();

            $apiToken = trim((string) ($validated['api_token'] ?? ''));
            if ($apiToken === '') {
                $apiToken = (string) ($existing?->api_token ?? '');
            }

            if ($apiToken === '') {
                return back()->withErrors([
                    'api_token' => __('Укажите API-ключ OpenAI.'),
                ]);
            }

            try {
                $connection = app(ChatGptService::class)->connectFromToken(
                    $apiToken,
                    $existing?->metadata,
                    $validated['model'] ?? null,
                );
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'api_token' => __('OpenAI отклонил ключ: :msg', [
                        'msg' => $e->getMessage(),
                    ]),
                ]);
            }

            $attributes = [
                'api_token' => $connection['api_token'],
                'metadata' => $connection['metadata'],
            ];
        }

        if ($integrationProvider === IntegrationProvider::Shop) {
            try {
                $connection = app(ShopIntegrationService::class)->connectFromCredentials(
                    (string) $validated['shop_url'],
                    (string) $validated['api_token'],
                );
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors());
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

        if ($integrationProvider === IntegrationProvider::Wappi) {
            try {
                app(WappiMessengerService::class)->connectIntegration($integration);
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'api_token' => __('Wappi: :msg', ['msg' => $e->getMessage()]),
                ]);
            }
        }

        if ($integrationProvider === IntegrationProvider::Telegram) {
            try {
                app(TelegramMessengerService::class)->connectIntegration($integration);
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'api_token' => __('Telegram: :msg', ['msg' => $e->getMessage()]),
                ]);
            }
        }

        $message = match ($integrationProvider) {
            IntegrationProvider::Wappi => __('Интеграция :name сохранена. Webhook настроен автоматически.', ['name' => $integrationProvider->label()]),
            IntegrationProvider::Telegram => __('Интеграция :name сохранена. Webhook настроен автоматически.', ['name' => $integrationProvider->label()]),
            default => __('Токен :name сохранён.', ['name' => $integrationProvider->label()]),
        };

        return back()->with('success', $message);
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $integrationProvider = IntegrationProvider::tryFromSlug($provider);
        abort_unless($integrationProvider !== null, 404);

        $companyId = (int) $request->user()->company_id;

        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', $integrationProvider->value)
            ->first();

        if ($integration && $integrationProvider === IntegrationProvider::Telegram) {
            app(TelegramMessengerService::class)->disconnectIntegration($integration);
        }

        CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', $integrationProvider->value)
            ->delete();

        return back()->with('success', __('Интеграция :name отключена.', [
            'name' => $integrationProvider->label(),
        ]));
    }
}
