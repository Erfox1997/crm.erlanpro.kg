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
        try {
            return $this->renderIndex($request);
        } catch (\Throwable $e) {
            report($e);

            return Inertia::render('Integrations/Index', [
                'integrations' => [],
                'pageTitle' => 'Интеграции',
                'wappiWebhookUrl' => route('webhooks.wappi.handle'),
                'chatGptModels' => array_values(app(ChatGptService::class)->preferredModels()),
                'loadError' => config('app.debug')
                    ? $e->getMessage()
                    : __('Не удалось загрузить интеграции. Обновите страницу или переподключите токены.'),
            ]);
        }
    }

    protected function renderIndex(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $stored = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get()
            ->groupBy('provider');

        $providers = [
            IntegrationProvider::Facebook,
            IntegrationProvider::Instagram,
            IntegrationProvider::Wappi,
            IntegrationProvider::Telegram,
            IntegrationProvider::ChatGpt,
            IntegrationProvider::Shop,
        ];

        $integrations = collect($providers)->map(function (IntegrationProvider $provider) use ($stored) {
            $records = $stored->get($provider->value, collect());
            $record = $records->first();
            $metadata = $record?->safeMetadata() ?? [];
            $isMeta = in_array($provider, [IntegrationProvider::Instagram, IntegrationProvider::Facebook], true);

            $hasToken = match ($provider) {
                IntegrationProvider::Wappi => $record !== null
                    && $record->hasUsableApiToken()
                    && filled($metadata['profile_id'] ?? null),
                IntegrationProvider::Telegram => $record !== null
                    && $record->hasUsableApiToken()
                    && filled($metadata['bot_id'] ?? null),
                IntegrationProvider::Shop => $record !== null
                    && $record->hasUsableApiToken()
                    && filled($metadata['shop_url'] ?? null),
                IntegrationProvider::Instagram, IntegrationProvider::Facebook => $records->contains(
                    fn (CompanyIntegration $item) => $item->hasUsableApiToken(),
                ),
                default => $record !== null && $record->hasUsableApiToken(),
            };

            $item = [
                'provider' => $provider->value,
                'name' => $provider->label(),
                'description' => $provider->description(),
                'has_token' => $hasToken,
            ];

            if ($isMeta) {
                $item['oauth_url'] = route("integrations.{$provider->value}.oauth");
                $item['connections'] = $records
                    ->filter(fn (CompanyIntegration $row) => $row->hasUsableApiToken())
                    ->map(function (CompanyIntegration $row) use ($provider) {
                        $meta = $row->safeMetadata();

                        return [
                            'id' => $row->id,
                            'label' => $provider === IntegrationProvider::Instagram
                                ? ($meta['username'] ? '@'.$meta['username'] : ($meta['page_name'] ?? $meta['name'] ?? '#'.$row->id))
                                : ($meta['page_name'] ?? $meta['page_id'] ?? '#'.$row->id),
                            'page_name' => $meta['page_name'] ?? null,
                            'username' => $meta['username'] ?? null,
                            'connected_via' => $meta['connected_via'] ?? 'manual',
                        ];
                    })
                    ->values()
                    ->all();

                // Backward-compatible single account label for first connection.
                if ($item['connections'] !== []) {
                    $first = $item['connections'][0];
                    $item['account'] = [
                        'username' => $first['username'] ?? null,
                        'name' => $first['label'] ?? null,
                        'page_name' => $first['page_name'] ?? null,
                        'connected_via' => $first['connected_via'] ?? 'manual',
                    ];
                }
            }

            if ($provider === IntegrationProvider::Wappi) {
                $item['profile_id'] = $metadata['profile_id'] ?? null;

                if ($hasToken) {
                    $item['account'] = [
                        'name' => $metadata['profile_name'] ?? null,
                        'profile_id' => $metadata['profile_id'] ?? null,
                    ];
                }
            }

            if ($provider === IntegrationProvider::Telegram && $hasToken) {
                $item['account'] = [
                    'username' => $metadata['bot_username'] ?? null,
                    'name' => $metadata['bot_name'] ?? null,
                ];
                $item['webhook_url'] = filled($metadata['webhook_secret'] ?? null)
                    ? route('webhooks.telegram.handle', ['secret' => $metadata['webhook_secret']])
                    : null;
            }

            if ($provider === IntegrationProvider::ChatGpt) {
                $item['model'] = $metadata['model']
                    ?? config('services.openai.model', 'gpt-4.1-mini');

                if ($hasToken) {
                    $item['account'] = [
                        'name' => $item['model'],
                    ];
                }
            }

            if ($provider === IntegrationProvider::Shop) {
                $item['shop_url'] = $metadata['shop_url'] ?? '';

                if ($hasToken) {
                    $item['account'] = [
                        'name' => $metadata['shop_name'] ?? $item['shop_url'],
                    ];
                }
            }

            return $item;
        })->values();

        return Inertia::render('Integrations/Index', [
            'integrations' => $integrations->values()->all(),
            'pageTitle' => 'Интеграции',
            'wappiWebhookUrl' => route('webhooks.wappi.handle'),
            'chatGptModels' => array_values(app(ChatGptService::class)->preferredModels()),
            'loadError' => null,
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

        $integration = CompanyIntegration::upsertForCompany(
            $companyId,
            $integrationProvider->value,
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
        $integrationId = $request->integer('integration_id') ?: null;

        $query = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', $integrationProvider->value);

        if (
            $integrationId
            && in_array($integrationProvider, [IntegrationProvider::Instagram, IntegrationProvider::Facebook], true)
        ) {
            $query->whereKey($integrationId);
        }

        $integrations = $query->get();

        foreach ($integrations as $integration) {
            if ($integrationProvider === IntegrationProvider::Telegram) {
                app(TelegramMessengerService::class)->disconnectIntegration($integration);
            }

            $integration->delete();
        }

        return back()->with('success', __('Интеграция :name отключена.', [
            'name' => $integrationProvider->label(),
        ]));
    }
}
