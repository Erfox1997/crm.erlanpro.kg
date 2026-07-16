<?php

namespace App\Http\Controllers;

use App\Actions\CreateDefaultPipelineForCompany;
use App\Enums\IntegrationProvider;
use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerQuickReply;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Services\ChatGpt\ChatGptService;
use App\Services\Client\ClientFieldService;
use App\Services\Deal\DealStageService;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use App\Services\Messenger\ChatDistributionService;
use App\Services\Messenger\MessengerFunnelService;
use App\Services\Messenger\MessengerSyncService;
use App\Services\Messenger\MessengerUnreadService;
use App\Services\Meta\MetaAttachmentService;
use App\Services\Meta\MetaMessagingSupport;
use App\Services\Shop\ShopIntegrationService;
use App\Services\Telegram\TelegramMessengerService;
use App\Services\Wappi\WappiMessengerService;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessengerController extends Controller
{
    public function __construct(
        private InstagramMessengerService $instagram,
        private FacebookMessengerService $facebook,
        private WappiMessengerService $wappi,
        private TelegramMessengerService $telegram,
        private ChatGptService $chatGpt,
        private MetaAttachmentService $metaAttachments,
        private MessengerSyncService $messengerSync,
        private MessengerUnreadService $unread,
        private ClientFieldService $clientFields,
        private MessengerFunnelService $messengerFunnel,
        private DealStageService $dealStages,
        private ChatDistributionService $chatDistribution,
        private ShopIntegrationService $shop,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        CreateDefaultPipelineForCompany::ensure(Company::query()->findOrFail($companyId));

        $instagramIntegration = $this->instagram->integrationForCompany($companyId);
        $facebookIntegration = $this->facebook->integrationForCompany($companyId);
        $wappiIntegration = $this->wappi->integrationForCompany($companyId);
        $telegramIntegration = $this->telegram->integrationForCompany($companyId);
        $chatGptIntegration = $this->chatGpt->integrationForCompany($companyId);

        $channels = array_values(array_filter([
            $instagramIntegration ? IntegrationProvider::Instagram->value : null,
            $facebookIntegration ? IntegrationProvider::Facebook->value : null,
            $wappiIntegration ? IntegrationProvider::Wappi->value : null,
            $telegramIntegration ? IntegrationProvider::Telegram->value : null,
        ]));

        $conversations = MessengerConversation::query()
            ->where('company_id', $companyId)
            ->when($channels !== [], fn ($q) => $q->whereIn('channel', $channels))
            ->tap(fn ($q) => $this->chatDistribution->scopeVisibleTo($q, $user))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->with([
                'assignee:id,name',
                'client.deals' => fn ($q) => $q
                    ->with(['pipeline', 'stage'])
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->get();

        $messengerField = $this->clientFields->messengerFieldDefinition($companyId);

        $conversations = $conversations->map(
            fn (MessengerConversation $c) => $this->serializeConversation($c, $messengerField),
        );

        $filterPipelines = Pipeline::query()
            ->where('company_id', $companyId)
            ->with(['stages' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Pipeline $pipeline) => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'is_default' => $pipeline->is_default,
                'stages' => $pipeline->stages->map(fn (Stage $stage) => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                ])->values(),
            ]);

        $quickReplies = MessengerQuickReply::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (MessengerQuickReply $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->type,
                'body' => $item->body,
                'attachment_url' => $item->attachment_path
                    ? route('messenger.quick-replies.attachment', $item)
                    : null,
            ]);

        $selectedId = $request->query('conversation');
        $selectedConversation = null;
        $messages = [];
        $linkedClient = null;
        $funnelDeal = null;

        $fieldDefinitions = $this->clientFields->definitionsForCompany($companyId)
            ->map(fn ($field) => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'options' => $field->options ?? [],
                'is_required' => $field->is_required,
                'show_in_messenger' => $field->show_in_messenger,
            ])
            ->values()
            ->all();

        if ($selectedId) {
            $conversation = MessengerConversation::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $selectedId)
                ->with(['client', 'assignee:id,name'])
                ->first();

            if ($conversation && ! $this->chatDistribution->userCanViewConversation($user, $conversation)) {
                $conversation = null;
            }

            if ($conversation) {
                $this->unread->markConversationRead($conversation);

                $selectedConversation = [
                    'id' => $conversation->id,
                    'channel' => $conversation->channel,
                    'channel_label' => IntegrationProvider::tryFrom($conversation->channel)?->label() ?? $conversation->channel,
                    'participant_name' => $conversation->participant_name,
                    'participant_username' => $conversation->participant_username,
                    'participant_id' => $conversation->participant_id,
                    'client_id' => $conversation->client_id,
                    'display_name' => $this->clientFields->resolveMessengerDisplayName(
                        $conversation,
                        $conversation->client,
                        $messengerField,
                    ),
                    'assigned_user_id' => $conversation->assigned_user_id,
                    'assigned_user_name' => $conversation->assignee?->name,
                ];

                $funnelDeal = $this->messengerFunnel->dealPayloadForConversation($conversation);

                $conversation->refresh()->load('client');

                if ($conversation->client) {
                    $linkedClient = [
                        'id' => $conversation->client->id,
                        'name' => $conversation->client->name,
                        'phone' => $conversation->client->phone,
                        'custom_fields' => $conversation->client->custom_fields ?? [],
                    ];
                }

                $messages = $conversation->messages()
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (MessengerMessage $m) => $this->serializeMessage($m, $conversation->channel));
            }
        }

        return Inertia::render('Messenger/Index', [
            'instagramConnected' => $instagramIntegration !== null,
            'facebookConnected' => $facebookIntegration !== null,
            'wappiConnected' => $wappiIntegration !== null,
            'telegramConnected' => $telegramIntegration !== null,
            'chatGptConnected' => $chatGptIntegration !== null,
            'instagramAccount' => $instagramIntegration ? [
                'username' => $instagramIntegration->metadata['username'] ?? null,
                'name' => $instagramIntegration->metadata['name'] ?? null,
                'page_name' => $instagramIntegration->metadata['page_name'] ?? null,
            ] : null,
            'facebookAccount' => $facebookIntegration ? [
                'page_name' => $facebookIntegration->metadata['page_name'] ?? null,
                'page_id' => $facebookIntegration->metadata['page_id'] ?? null,
            ] : null,
            'wappiAccount' => $wappiIntegration ? [
                'profile_name' => $wappiIntegration->metadata['profile_name'] ?? null,
                'profile_phone' => $wappiIntegration->metadata['profile_phone'] ?? null,
                'profile_id' => $wappiIntegration->metadata['profile_id'] ?? null,
            ] : null,
            'telegramAccount' => $telegramIntegration ? [
                'bot_name' => $telegramIntegration->metadata['bot_name'] ?? null,
                'bot_username' => $telegramIntegration->metadata['bot_username'] ?? null,
            ] : null,
            'conversations' => $conversations,
            'filterPipelines' => $filterPipelines,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'quickReplies' => $quickReplies,
            'clientFieldDefinitions' => $fieldDefinitions,
            'messengerFieldKey' => $messengerField?->key,
            'linkedClient' => $linkedClient,
            'funnelDeal' => $funnelDeal,
            'shopConnected' => $this->shop->isConnected($companyId),
            'webhookUrl' => url('/webhooks/meta'),
            'wappiWebhookUrl' => route('webhooks.wappi.handle'),
        ]);
    }

    public function updates(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        $validated = $request->validate([
            'since' => ['required', 'date'],
            'conversation_id' => ['nullable', 'integer'],
            'after_message_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $since = Carbon::parse($validated['since']);
        $serverTime = now()->toIso8601String();
        $messengerField = $this->clientFields->messengerFieldDefinition($companyId);

        $instagramIntegration = $this->instagram->integrationForCompany($companyId);
        $facebookIntegration = $this->facebook->integrationForCompany($companyId);
        $wappiIntegration = $this->wappi->integrationForCompany($companyId);
        $telegramIntegration = $this->telegram->integrationForCompany($companyId);

        $channels = array_values(array_filter([
            $instagramIntegration ? IntegrationProvider::Instagram->value : null,
            $facebookIntegration ? IntegrationProvider::Facebook->value : null,
            $wappiIntegration ? IntegrationProvider::Wappi->value : null,
            $telegramIntegration ? IntegrationProvider::Telegram->value : null,
        ]));

        $conversationRows = MessengerConversation::query()
            ->where('company_id', $companyId)
            ->when($channels !== [], fn ($q) => $q->whereIn('channel', $channels))
            ->tap(fn ($q) => $this->chatDistribution->scopeVisibleTo($q, $user))
            ->where(function ($query) use ($since) {
                $query->where('last_message_at', '>', $since)
                    ->orWhere('updated_at', '>', $since)
                    ->orWhere('created_at', '>', $since);
            })
            ->with([
                'assignee:id,name',
                'client.deals' => fn ($q) => $q
                    ->with(['pipeline', 'stage'])
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MessengerConversation $c) => $this->serializeConversation($c, $messengerField))
            ->values();

        $messages = [];
        $conversationId = isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null;

        if ($conversationId) {
            $conversation = MessengerConversation::query()
                ->where('company_id', $companyId)
                ->whereKey($conversationId)
                ->first();

            if ($conversation && $this->chatDistribution->userCanViewConversation($user, $conversation)) {
                $afterMessageId = (int) ($validated['after_message_id'] ?? 0);

                $messageModels = $conversation->messages()
                    ->where(function ($query) use ($afterMessageId, $since) {
                        $query->where('id', '>', $afterMessageId)
                            ->orWhere('updated_at', '>', $since);
                    })
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->get();

                $messages = $messageModels
                    ->map(fn (MessengerMessage $m) => $this->serializeMessage($m, $conversation->channel))
                    ->values()
                    ->all();

                $hasNewInbound = $messageModels->contains(
                    fn (MessengerMessage $m) => $m->direction === 'inbound' && $m->id > $afterMessageId,
                );

                if ($hasNewInbound) {
                    $this->unread->markConversationRead($conversation);
                }
            }
        }

        return response()->json([
            'server_time' => $serverTime,
            'conversations' => $conversationRows,
            'messages' => $messages,
        ]);
    }

    public function improveWithAi(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $integration = $this->chatGpt->integrationForCompany($companyId);
        if (! $integration) {
            return response()->json([
                'message' => __('Подключите ChatGPT в разделе «Интеграции».'),
            ], 422);
        }

        try {
            $improved = $this->chatGpt->improveMessage($integration, $validated['body']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'body' => $improved,
        ]);
    }

    public function attachment(Request $request, MessengerMessage $message, int $index): BinaryFileResponse|StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($message->company_id === $companyId, 403);

        $conversation = $message->conversation()->firstOrFail();

        if ($conversation->channel === IntegrationProvider::Wappi->value) {
            $attachment = $message->normalizedAttachments()[$index] ?? null;

            if (! is_array($attachment)) {
                abort(404);
            }

            $storagePath = (string) ($attachment['storage_path'] ?? '');
            if ($storagePath !== '') {
                $localPath = $this->metaAttachments->resolveLocalStoragePath($storagePath);

                if ($localPath) {
                    return response()->file($localPath, [
                        'Content-Type' => $attachment['mime_type']
                            ?? $this->metaAttachments->mimeTypeForPath($localPath, 'audio/ogg'),
                        'Cache-Control' => 'private, max-age=3600',
                        'Accept-Ranges' => 'bytes',
                    ]);
                }
            }

            $remoteUrl = (string) ($attachment['url'] ?? '');

            if ($remoteUrl !== '' && str_starts_with($remoteUrl, 'http')) {
                return $this->metaAttachments->streamRemoteUrl($remoteUrl, []);
            }

            abort(404);
        }

        if ($conversation->channel === IntegrationProvider::Telegram->value) {
            $attachment = $message->normalizedAttachments()[$index] ?? null;

            if (! is_array($attachment)) {
                abort(404);
            }

            $storagePath = (string) ($attachment['storage_path'] ?? '');
            if ($storagePath !== '') {
                $localPath = $this->metaAttachments->resolveLocalStoragePath($storagePath);

                if ($localPath) {
                    return response()->file($localPath, [
                        'Content-Type' => $attachment['mime_type']
                            ?? $this->metaAttachments->mimeTypeForPath($localPath),
                        'Cache-Control' => 'private, max-age=3600',
                        'Accept-Ranges' => 'bytes',
                    ]);
                }
            }

            abort(404);
        }

        $integration = $this->integrationForConversation($companyId, $conversation);

        $source = $this->instagram->resolveAttachmentPlayback($integration, $message, $index);

        if ($source['type'] === 'local') {
            return response()->file($source['path'], [
                'Content-Type' => $source['mime_type'] ?? 'audio/mp4',
                'Cache-Control' => 'private, max-age=3600',
                'Accept-Ranges' => 'bytes',
            ]);
        }

        return $this->metaAttachments->streamRemoteUrl(
            (string) $source['url'],
            $source['tokens'] ?? $this->instagram->mediaFetchTokens($integration),
        );
    }

    public function sync(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $instagramIntegration = $this->instagram->integrationForCompany($companyId);
        $facebookIntegration = $this->facebook->integrationForCompany($companyId);
        $wappiIntegration = $this->wappi->integrationForCompany($companyId);
        $telegramIntegration = $this->telegram->integrationForCompany($companyId);

        if (! $instagramIntegration && ! $facebookIntegration && ! $wappiIntegration && ! $telegramIntegration) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['sync' => __('Подключите Instagram, Facebook, WhatsApp или Telegram в разделе «Интеграции».')]);
        }

        set_time_limit(120);

        try {
            $result = $this->messengerSync->syncQuickForCompany($companyId);

            if ($result['errors'] !== []) {
                return back()->withErrors([
                    'sync' => implode(' ', $result['errors']),
                ]);
            }

            return back()->with(
                'success',
                __('Обновлено: :count диалогов', ['count' => $result['synced']]),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['sync' => $e->getMessage()]);
        }
    }

    public function saveClient(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($conversation->company_id === $companyId, 403);

        $definitions = $this->clientFields->definitionsForCompany($companyId);

        if ($definitions->isEmpty()) {
            return back()->withErrors([
                'client' => __('Сначала добавьте поля в разделе «Данные клиента».'),
            ]);
        }

        $validated = $request->validate([
            'fields' => 'required|array',
            ...$this->clientFields->validationRulesForCompany($companyId),
        ]);

        $normalized = $this->clientFields->normalizeSubmittedFields(
            $companyId,
            $validated['fields'] ?? [],
        );

        $this->clientFields->upsertClientFromConversation($conversation, $normalized);
        $this->messengerFunnel->ensureClientAndDeal($conversation->fresh());

        return redirect()
            ->route('messenger.index', ['conversation' => $conversation->id])
            ->with('success', __('Данные клиента сохранены.'));
    }

    public function updateDealStage(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($conversation->company_id === $companyId, 403);

        $validated = $request->validate([
            'stage_id' => 'required|exists:stages,id',
        ]);

        $deal = $this->messengerFunnel->resolveDeal($conversation)
            ?? $this->messengerFunnel->ensureClientAndDeal($conversation);

        if (! $deal) {
            return back()->withErrors([
                'stage' => __('Не удалось найти сделку для этого чата.'),
            ]);
        }

        abort_unless($deal->company_id === $companyId, 403);

        $stage = Stage::query()->findOrFail($validated['stage_id']);
        abort_unless($stage->company_id === $companyId, 403);
        abort_unless($stage->pipeline_id === $deal->pipeline_id, 422);

        $this->dealStages->moveToStage($deal, $stage);

        return redirect()
            ->route('messenger.index', ['conversation' => $conversation->id])
            ->with('success', __('Этап воронки обновлён.'));
    }

    public function send(Request $request, MessengerConversation $conversation): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        abort_unless($conversation->company_id === $companyId, 403);
        abort_unless($this->chatDistribution->userCanViewConversation($user, $conversation), 403);

        $validated = $request->validate([
            'body' => 'nullable|string|max:2000',
            'audio' => 'nullable|file|max:16384',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif|max:16384',
        ]);

        if (! $request->hasFile('audio')
            && ! $request->hasFile('image')
            && trim((string) ($validated['body'] ?? '')) === '') {
            return $this->messengerSendError(
                $request,
                __('Введите текст, прикрепите изображение или запишите голосовое сообщение.'),
            );
        }

        try {
            if ($conversation->channel === IntegrationProvider::Wappi->value) {
                $integration = $this->wappi->integrationForCompany($companyId);
                if (! $integration) {
                    return $this->messengerSendError($request, __('WhatsApp (Wappi) не подключён.'));
                }

                if ($request->hasFile('image')) {
                    $this->sendImage($this->wappi, $integration, $conversation, $request->file('image'), (string) ($validated['body'] ?? ''));
                } elseif ($request->hasFile('audio')) {
                    $audio = $request->file('audio');
                    $path = $audio->getRealPath();

                    if (! is_string($path) || $path === '') {
                        return $this->messengerSendError($request, __('Не удалось прочитать аудиофайл.'));
                    }

                    $this->wappi->sendAudioMessage(
                        $integration,
                        $conversation,
                        $path,
                        $audio->getClientOriginalName() ?: 'voice.webm',
                        $audio->getMimeType(),
                    );
                } else {
                    $this->wappi->sendMessage($integration, $conversation, (string) $validated['body']);
                }
            } elseif ($conversation->channel === IntegrationProvider::Facebook->value) {
                $integration = $this->facebook->integrationForCompany($companyId);
                if (! $integration) {
                    return $this->messengerSendError($request, __('Facebook не подключён.'));
                }

                if ($request->hasFile('image')) {
                    $this->sendImage($this->facebook, $integration, $conversation, $request->file('image'), (string) ($validated['body'] ?? ''));
                } elseif ($request->hasFile('audio')) {
                    $this->sendAudio($this->facebook, $integration, $conversation, $request->file('audio'));
                } else {
                    $this->facebook->sendMessage($integration, $conversation, (string) $validated['body']);
                }
            } elseif ($conversation->channel === IntegrationProvider::Instagram->value) {
                $integration = $this->instagram->integrationForCompany($companyId);
                if (! $integration) {
                    return $this->messengerSendError($request, __('Instagram не подключён.'));
                }

                if ($request->hasFile('image')) {
                    $this->sendImage($this->instagram, $integration, $conversation, $request->file('image'), (string) ($validated['body'] ?? ''));
                } elseif ($request->hasFile('audio')) {
                    $this->sendAudio($this->instagram, $integration, $conversation, $request->file('audio'));
                } else {
                    $this->instagram->sendMessage($integration, $conversation, (string) $validated['body']);
                }
            } elseif ($conversation->channel === IntegrationProvider::Telegram->value) {
                $integration = $this->telegram->integrationForCompany($companyId);
                if (! $integration) {
                    return $this->messengerSendError($request, __('Telegram не подключён.'));
                }

                if ($request->hasFile('image')) {
                    $this->sendImage($this->telegram, $integration, $conversation, $request->file('image'), (string) ($validated['body'] ?? ''));
                } elseif ($request->hasFile('audio')) {
                    $this->sendTelegramAudio($integration, $conversation, $request->file('audio'));
                } else {
                    $this->telegram->sendMessage($integration, $conversation, (string) $validated['body']);
                }
            } else {
                return $this->messengerSendError($request, __('Канал не поддерживается.'));
            }

            $this->chatDistribution->claimIfNeeded($conversation, $user);
            $conversation->update(['last_message_at' => now()]);

            return $this->messengerSendSuccess($request, $conversation);
        } catch (RequestException $e) {
            $error = match ($conversation->channel) {
                IntegrationProvider::Wappi->value => $this->formatWappiRequestError($e),
                IntegrationProvider::Telegram->value => $this->formatTelegramRequestError($e),
                default => MetaMessagingSupport::formatGraphError($e->response?->json(), $e->getMessage()),
            };

            return $this->messengerSendError($request, $error);
        } catch (\Throwable $e) {
            return $this->messengerSendError($request, $e->getMessage());
        }
    }

    public function sendQuickReply(
        Request $request,
        MessengerConversation $conversation,
        MessengerQuickReply $quickReply,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        abort_unless($conversation->company_id === $companyId, 403);
        abort_unless($quickReply->company_id === $companyId, 403);
        abort_unless($this->chatDistribution->userCanViewConversation($user, $conversation), 403);

        try {
            if ($conversation->channel === IntegrationProvider::Wappi->value) {
                $integration = $this->wappi->integrationForCompany($companyId);
                if (! $integration) {
                    return $this->messengerSendError($request, __('WhatsApp (Wappi) не подключён.'));
                }

                if ($quickReply->type === 'text') {
                    $this->wappi->sendMessage($integration, $conversation, (string) $quickReply->body);
                } elseif (in_array($quickReply->type, ['audio', 'image'], true)) {
                    $this->dispatchWappiQuickReply($integration, $conversation, $quickReply);
                } else {
                    return $this->messengerSendError($request, __('Медиа-шаблоны для WhatsApp пока не поддерживаются.'));
                }
            } elseif ($conversation->channel === IntegrationProvider::Facebook->value) {
                $integration = $this->facebook->integrationForCompany($companyId);
                if (! $integration) {
                    return $this->messengerSendError($request, __('Facebook не подключён.'));
                }

                $this->dispatchQuickReply($this->facebook, $integration, $conversation, $quickReply);
            } elseif ($conversation->channel === IntegrationProvider::Instagram->value) {
                $integration = $this->instagram->integrationForCompany($companyId);
                if (! $integration) {
                    return $this->messengerSendError($request, __('Instagram не подключён.'));
                }

                $this->dispatchQuickReply($this->instagram, $integration, $conversation, $quickReply);
            } elseif ($conversation->channel === IntegrationProvider::Telegram->value) {
                $integration = $this->telegram->integrationForCompany($companyId);
                if (! $integration) {
                    return $this->messengerSendError($request, __('Telegram не подключён.'));
                }

                if ($quickReply->type === 'text') {
                    $this->telegram->sendMessage($integration, $conversation, (string) $quickReply->body);
                } elseif (in_array($quickReply->type, ['audio', 'image'], true)) {
                    $this->dispatchTelegramQuickReply($integration, $conversation, $quickReply);
                } else {
                    return $this->messengerSendError($request, __('Медиа-шаблоны для Telegram пока не поддерживаются.'));
                }
            } else {
                return $this->messengerSendError($request, __('Канал не поддерживается.'));
            }

            $this->chatDistribution->claimIfNeeded($conversation, $user);
            $conversation->update(['last_message_at' => now()]);

            return $this->messengerSendSuccess($request, $conversation);
        } catch (RequestException $e) {
            $error = match ($conversation->channel) {
                IntegrationProvider::Wappi->value => $this->formatWappiRequestError($e),
                IntegrationProvider::Telegram->value => $this->formatTelegramRequestError($e),
                default => MetaMessagingSupport::formatGraphError($e->response?->json(), $e->getMessage()),
            };

            return $this->messengerSendError($request, $error);
        } catch (\Throwable $e) {
            return $this->messengerSendError($request, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeConversation(MessengerConversation $conversation, mixed $messengerField = null): array
    {
        $deal = $conversation->client?->deals?->first();

        return [
            'id' => $conversation->id,
            'channel' => $conversation->channel,
            'channel_label' => IntegrationProvider::tryFrom($conversation->channel)?->label() ?? $conversation->channel,
            'participant_id' => $conversation->participant_id,
            'participant_name' => $conversation->participant_name,
            'participant_username' => $conversation->participant_username,
            'display_name' => $this->clientFields->resolveMessengerDisplayName(
                $conversation,
                $conversation->client,
                $messengerField,
            ),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'pipeline_name' => $deal?->pipeline?->name,
            'pipeline_id' => $deal?->pipeline_id,
            'stage_name' => $deal?->stage?->name,
            'stage_id' => $deal?->stage_id,
            'stage_color' => $deal?->stage?->color,
            'unread_count' => $this->unread->unreadCountForConversation($conversation),
            'assigned_user_id' => $conversation->assigned_user_id,
            'assigned_user_name' => $conversation->assignee?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMessage(MessengerMessage $message, ?string $channel = null): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'body' => $message->body,
            'attachments' => $this->mapAttachmentsForFrontend($message, $channel),
            'status' => $message->status,
            'sent_at' => $message->sent_at?->toIso8601String(),
        ];
    }

    protected function messengerSendSuccess(Request $request, MessengerConversation $conversation): RedirectResponse|JsonResponse
    {
        $conversation->refresh();

        $message = $conversation->messages()
            ->orderByDesc('id')
            ->first();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message
                    ? $this->serializeMessage($message, $conversation->channel)
                    : null,
                'conversation' => [
                    'id' => $conversation->id,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'unread_count' => 0,
                ],
            ]);
        }

        return redirect()
            ->route('messenger.index', ['conversation' => $conversation->id])
            ->with('success', __('Сообщение отправлено.'));
    }

    protected function messengerSendError(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => ['body' => [$message]],
            ], 422);
        }

        return back()->withErrors(['body' => $message]);
    }

    /**
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string}>
     */
    protected function mapAttachmentsForFrontend(MessengerMessage $message, ?string $channel = null): array
    {
        $attachments = collect($message->normalizedAttachments())->values();

        if ($attachments->isEmpty() && $message->external_id && trim((string) $message->body) === '') {
            $attachments = collect([[
                'type' => 'audio',
                'url' => '',
                'name' => null,
                'mime_type' => 'audio/mp4',
            ]]);
        }

        $channel ??= (string) $message->conversation()->value('channel');

        return $attachments
            ->map(function (array $attachment, int $index) use ($message, $channel) {
                $hasRemoteUrl = ($attachment['url'] ?? '') !== '';
                $hasLocalFile = ($attachment['storage_path'] ?? '') !== '';
                $canLazyLoad = ! $hasRemoteUrl
                    && ! $hasLocalFile
                    && ($attachment['type'] ?? '') === 'audio'
                    && $message->external_id;

                if ($channel === IntegrationProvider::Wappi->value && $hasRemoteUrl && str_starts_with((string) $attachment['url'], 'http')) {
                    return [
                        'type' => $attachment['type'] ?? 'file',
                        'url' => $attachment['url'],
                        'name' => $attachment['name'] ?? null,
                        'mime_type' => $attachment['mime_type'] ?? null,
                    ];
                }

                return [
                    'type' => $attachment['type'] ?? 'file',
                    'url' => ($hasRemoteUrl || $hasLocalFile || $canLazyLoad)
                        ? route('messenger.attachment', ['message' => $message->id, 'index' => $index])
                        : '',
                    'name' => $attachment['name'] ?? null,
                    'mime_type' => $attachment['mime_type'] ?? null,
                ];
            })
            ->all();
    }

    protected function integrationForConversation(int $companyId, MessengerConversation $conversation): CompanyIntegration
    {
        if ($conversation->channel === IntegrationProvider::Wappi->value) {
            $integration = $this->wappi->integrationForCompany($companyId);
            if (! $integration) {
                throw new \RuntimeException(__('WhatsApp (Wappi) не подключён.'));
            }

            return $integration;
        }

        if ($conversation->channel === IntegrationProvider::Facebook->value) {
            $integration = $this->facebook->integrationForCompany($companyId);
            if (! $integration) {
                throw new \RuntimeException(__('Facebook не подключён.'));
            }

            return $integration;
        }

        if ($conversation->channel === IntegrationProvider::Instagram->value) {
            $integration = $this->instagram->integrationForCompany($companyId);
            if (! $integration) {
                throw new \RuntimeException(__('Instagram не подключён.'));
            }

            return $integration;
        }

        if ($conversation->channel === IntegrationProvider::Telegram->value) {
            $integration = $this->telegram->integrationForCompany($companyId);
            if (! $integration) {
                throw new \RuntimeException(__('Telegram не подключён.'));
            }

            return $integration;
        }

        throw new \RuntimeException(__('Канал не поддерживается.'));
    }

    protected function dispatchTelegramQuickReply(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        MessengerQuickReply $quickReply,
    ): void {
        if (! $quickReply->attachment_path) {
            throw new \RuntimeException(__('Файл шаблона не найден.'));
        }

        $path = Storage::disk('local')->path($quickReply->attachment_path);
        if (! is_file($path)) {
            throw new \RuntimeException(__('Файл шаблона не найден.'));
        }

        if ($quickReply->type === 'audio') {
            $this->telegram->sendAudioMessage(
                $integration,
                $conversation,
                $path,
                $quickReply->attachment_name ?: 'voice.m4a',
                $quickReply->attachment_mime,
            );

            return;
        }

        if ($quickReply->type === 'image') {
            $this->telegram->sendImageMessage(
                $integration,
                $conversation,
                $path,
                $quickReply->attachment_name ?: 'image.jpg',
                $quickReply->attachment_mime,
                $quickReply->body,
            );

            return;
        }

        throw new \RuntimeException(__('Медиа-шаблоны для Telegram пока не поддерживаются.'));
    }

    protected function dispatchWappiQuickReply(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        MessengerQuickReply $quickReply,
    ): void {
        if ($quickReply->type === 'text') {
            $this->wappi->sendMessage($integration, $conversation, (string) $quickReply->body);

            return;
        }

        if (! $quickReply->attachment_path) {
            throw new \RuntimeException(__('Файл шаблона не найден.'));
        }

        $path = Storage::disk('local')->path($quickReply->attachment_path);
        if (! is_file($path)) {
            throw new \RuntimeException(__('Файл шаблона не найден.'));
        }

        if ($quickReply->type === 'audio') {
            $this->wappi->sendAudioMessage(
                $integration,
                $conversation,
                $path,
                $quickReply->attachment_name ?: 'voice.m4a',
                $quickReply->attachment_mime,
            );

            return;
        }

        if ($quickReply->type === 'image') {
            $this->wappi->sendImageMessage(
                $integration,
                $conversation,
                $path,
                $quickReply->attachment_name ?: 'image.jpg',
                $quickReply->attachment_mime,
                $quickReply->body,
            );

            return;
        }

        throw new \RuntimeException(__('Медиа-шаблоны для WhatsApp пока не поддерживаются.'));
    }

    protected function formatTelegramRequestError(RequestException $exception): string
    {
        $response = $exception->response;
        $message = trim((string) ($response?->json('description') ?? $response?->json('error') ?? ''));

        if ($message !== '') {
            return $message;
        }

        return $exception->getMessage();
    }

    protected function sendTelegramAudio(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        UploadedFile $audio,
    ): void {
        $path = $audio->getRealPath();
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException(__('Не удалось прочитать аудиофайл.'));
        }

        $this->telegram->sendAudioMessage(
            $integration,
            $conversation,
            $path,
            $audio->getClientOriginalName() ?: 'voice.webm',
            $audio->getMimeType(),
        );
    }

    protected function formatWappiRequestError(RequestException $exception): string
    {
        $response = $exception->response;
        $message = trim((string) ($response?->json('message') ?? $response?->json('detail') ?? $response?->json('error') ?? ''));

        if ($message !== '') {
            return $message;
        }

        return $exception->getMessage();
    }

    protected function sendAudio(
        FacebookMessengerService|InstagramMessengerService $service,
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        UploadedFile $audio,
    ): void {
        $path = $audio->getRealPath();
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException(__('Не удалось прочитать аудиофайл.'));
        }

        $service->sendAudioMessage(
            $integration,
            $conversation,
            $path,
            $audio->getClientOriginalName() ?: 'voice.webm',
            $audio->getMimeType(),
        );
    }

    protected function sendImage(
        FacebookMessengerService|InstagramMessengerService|TelegramMessengerService|WappiMessengerService $service,
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        UploadedFile $image,
        ?string $caption = null,
    ): void {
        $path = $image->getRealPath();
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException(__('Не удалось прочитать изображение.'));
        }

        $service->sendImageMessage(
            $integration,
            $conversation,
            $path,
            $image->getClientOriginalName() ?: 'image.jpg',
            $image->getMimeType(),
            $caption !== null && trim($caption) !== '' ? trim($caption) : null,
        );
    }

    protected function dispatchQuickReply(
        FacebookMessengerService|InstagramMessengerService $service,
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        MessengerQuickReply $quickReply,
    ): void {
        if ($quickReply->type === 'text') {
            $service->sendMessage($integration, $conversation, (string) $quickReply->body);

            return;
        }

        if (! $quickReply->attachment_path) {
            throw new \RuntimeException(__('Файл шаблона не найден.'));
        }

        $path = Storage::disk('local')->path($quickReply->attachment_path);
        if (! is_file($path)) {
            throw new \RuntimeException(__('Файл шаблона не найден.'));
        }

        if ($quickReply->type === 'audio') {
            $service->sendAudioMessage(
                $integration,
                $conversation,
                $path,
                $quickReply->attachment_name ?: 'voice.m4a',
                $quickReply->attachment_mime,
            );

            return;
        }

        $service->sendImageMessage(
            $integration,
            $conversation,
            $path,
            $quickReply->attachment_name ?: 'image.jpg',
            $quickReply->attachment_mime,
            $quickReply->body,
        );
    }
}
