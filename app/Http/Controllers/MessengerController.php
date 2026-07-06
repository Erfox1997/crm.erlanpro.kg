<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerQuickReply;
use App\Models\Stage;
use App\Services\Client\ClientFieldService;
use App\Services\Deal\DealStageService;
use App\Services\Messenger\MessengerFunnelService;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use App\Services\Messenger\MessengerSyncService;
use App\Services\Messenger\MessengerUnreadService;
use App\Services\Meta\MetaAttachmentService;
use App\Services\Meta\MetaMessagingSupport;
use App\Services\Telegram\TelegramMessengerService;
use App\Services\Wappi\WappiMessengerService;
use Illuminate\Http\Client\RequestException;
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
        private MetaAttachmentService $metaAttachments,
        private MessengerSyncService $messengerSync,
        private MessengerUnreadService $unread,
        private ClientFieldService $clientFields,
        private MessengerFunnelService $messengerFunnel,
        private DealStageService $dealStages,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

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

        $conversations = MessengerConversation::query()
            ->where('company_id', $companyId)
            ->when($channels !== [], fn ($q) => $q->whereIn('channel', $channels))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->with([
                'client.deals' => fn ($q) => $q
                    ->with('pipeline')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->get();

        $messengerField = $this->clientFields->messengerFieldDefinition($companyId);

        $conversations = $conversations->map(function (MessengerConversation $c) use ($messengerField) {
                $deal = $c->client?->deals->first();

                return [
                    'id' => $c->id,
                    'channel' => $c->channel,
                    'channel_label' => IntegrationProvider::tryFrom($c->channel)?->label() ?? $c->channel,
                    'participant_id' => $c->participant_id,
                    'participant_name' => $c->participant_name,
                    'participant_username' => $c->participant_username,
                    'display_name' => $this->clientFields->resolveMessengerDisplayName($c, $c->client, $messengerField),
                    'last_message_at' => $c->last_message_at?->toIso8601String(),
                    'pipeline_name' => $deal?->pipeline?->name,
                    'unread_count' => $this->unread->unreadCountForConversation($c),
                ];
            });

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
                ->with('client')
                ->first();

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
                ];

                $funnelDeal = $this->messengerFunnel->dealPayloadForConversation($conversation);

                $conversation->refresh()->load('client');

                if ($conversation->client) {
                    $linkedClient = [
                        'id' => $conversation->client->id,
                        'name' => $conversation->client->name,
                        'custom_fields' => $conversation->client->custom_fields ?? [],
                    ];
                }

                $messages = $conversation->messages()
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (MessengerMessage $m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'body' => $m->body,
                        'attachments' => $this->mapAttachmentsForFrontend($m, $conversation->channel),
                        'status' => $m->status,
                        'sent_at' => $m->sent_at?->toIso8601String(),
                    ]);
            }
        }

        return Inertia::render('Messenger/Index', [
            'instagramConnected' => $instagramIntegration !== null,
            'facebookConnected' => $facebookIntegration !== null,
            'wappiConnected' => $wappiIntegration !== null,
            'telegramConnected' => $telegramIntegration !== null,
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
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'quickReplies' => $quickReplies,
            'clientFieldDefinitions' => $fieldDefinitions,
            'messengerFieldKey' => $messengerField?->key,
            'linkedClient' => $linkedClient,
            'funnelDeal' => $funnelDeal,
            'webhookUrl' => url('/webhooks/meta'),
            'wappiWebhookUrl' => route('webhooks.wappi.handle'),
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

    public function send(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($conversation->company_id === $companyId, 403);

        $validated = $request->validate([
            'body' => 'nullable|string|max:2000',
            'audio' => 'nullable|file|max:16384',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif|max:16384',
        ]);

        if (! $request->hasFile('audio')
            && ! $request->hasFile('image')
            && trim((string) ($validated['body'] ?? '')) === '') {
            return back()->withErrors(['body' => __('Введите текст, прикрепите изображение или запишите голосовое сообщение.')]);
        }

        try {
            if ($conversation->channel === IntegrationProvider::Wappi->value) {
                $integration = $this->wappi->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('WhatsApp (Wappi) не подключён.')]);
                }

                if ($request->hasFile('image')) {
                    $this->sendImage($this->wappi, $integration, $conversation, $request->file('image'), (string) ($validated['body'] ?? ''));
                } elseif ($request->hasFile('audio')) {
                    $audio = $request->file('audio');
                    $path = $audio->getRealPath();

                    if (! is_string($path) || $path === '') {
                        return back()->withErrors(['body' => __('Не удалось прочитать аудиофайл.')]);
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
                    return back()->withErrors(['body' => __('Facebook не подключён.')]);
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
                    return back()->withErrors(['body' => __('Instagram не подключён.')]);
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
                    return back()->withErrors(['body' => __('Telegram не подключён.')]);
                }

                if ($request->hasFile('image')) {
                    $this->sendImage($this->telegram, $integration, $conversation, $request->file('image'), (string) ($validated['body'] ?? ''));
                } elseif ($request->hasFile('audio')) {
                    $this->sendTelegramAudio($integration, $conversation, $request->file('audio'));
                } else {
                    $this->telegram->sendMessage($integration, $conversation, (string) $validated['body']);
                }
            } else {
                return back()->withErrors(['body' => __('Канал не поддерживается.')]);
            }

            $conversation->update(['last_message_at' => now()]);

            return redirect()
                ->route('messenger.index', ['conversation' => $conversation->id])
                ->with('success', __('Сообщение отправлено.'));
        } catch (RequestException $e) {
            $error = match ($conversation->channel) {
                IntegrationProvider::Wappi->value => $this->formatWappiRequestError($e),
                IntegrationProvider::Telegram->value => $this->formatTelegramRequestError($e),
                default => MetaMessagingSupport::formatGraphError($e->response?->json(), $e->getMessage()),
            };

            return back()->withErrors(['body' => $error]);
        } catch (\Throwable $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }
    }

    public function sendQuickReply(
        Request $request,
        MessengerConversation $conversation,
        MessengerQuickReply $quickReply,
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        abort_unless($conversation->company_id === $companyId, 403);
        abort_unless($quickReply->company_id === $companyId, 403);

        try {
            if ($conversation->channel === IntegrationProvider::Wappi->value) {
                $integration = $this->wappi->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('WhatsApp (Wappi) не подключён.')]);
                }

                if ($quickReply->type === 'text') {
                    $this->wappi->sendMessage($integration, $conversation, (string) $quickReply->body);
                } elseif (in_array($quickReply->type, ['audio', 'image'], true)) {
                    $this->dispatchWappiQuickReply($integration, $conversation, $quickReply);
                } else {
                    return back()->withErrors(['body' => __('Медиа-шаблоны для WhatsApp пока не поддерживаются.')]);
                }
            } elseif ($conversation->channel === IntegrationProvider::Facebook->value) {
                $integration = $this->facebook->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('Facebook не подключён.')]);
                }

                $this->dispatchQuickReply($this->facebook, $integration, $conversation, $quickReply);
            } elseif ($conversation->channel === IntegrationProvider::Instagram->value) {
                $integration = $this->instagram->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('Instagram не подключён.')]);
                }

                $this->dispatchQuickReply($this->instagram, $integration, $conversation, $quickReply);
            } elseif ($conversation->channel === IntegrationProvider::Telegram->value) {
                $integration = $this->telegram->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('Telegram не подключён.')]);
                }

                if ($quickReply->type === 'text') {
                    $this->telegram->sendMessage($integration, $conversation, (string) $quickReply->body);
                } elseif (in_array($quickReply->type, ['audio', 'image'], true)) {
                    $this->dispatchTelegramQuickReply($integration, $conversation, $quickReply);
                } else {
                    return back()->withErrors(['body' => __('Медиа-шаблоны для Telegram пока не поддерживаются.')]);
                }
            } else {
                return back()->withErrors(['body' => __('Канал не поддерживается.')]);
            }

            $conversation->update(['last_message_at' => now()]);

            return redirect()
                ->route('messenger.index', ['conversation' => $conversation->id])
                ->with('success', __('Сообщение отправлено.'));
        } catch (RequestException $e) {
            $error = match ($conversation->channel) {
                IntegrationProvider::Wappi->value => $this->formatWappiRequestError($e),
                IntegrationProvider::Telegram->value => $this->formatTelegramRequestError($e),
                default => MetaMessagingSupport::formatGraphError($e->response?->json(), $e->getMessage()),
            };

            return back()->withErrors(['body' => $error]);
        } catch (\Throwable $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }
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
