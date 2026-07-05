<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use App\Services\Messenger\MessengerSyncService;
use App\Services\Meta\MetaAttachmentService;
use App\Services\Meta\MetaMessagingSupport;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessengerController extends Controller
{
    public function __construct(
        private InstagramMessengerService $instagram,
        private FacebookMessengerService $facebook,
        private MetaAttachmentService $metaAttachments,
        private MessengerSyncService $messengerSync,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $instagramIntegration = $this->instagram->integrationForCompany($companyId);
        $facebookIntegration = $this->facebook->integrationForCompany($companyId);

        $channels = array_values(array_filter([
            $instagramIntegration ? IntegrationProvider::Instagram->value : null,
            $facebookIntegration ? IntegrationProvider::Facebook->value : null,
        ]));

        $conversations = MessengerConversation::query()
            ->where('company_id', $companyId)
            ->when($channels !== [], fn ($q) => $q->whereIn('channel', $channels))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->with(['messages' => fn ($q) => $q->orderByDesc('sent_at')->orderByDesc('id')->limit(1)])
            ->get()
            ->map(function (MessengerConversation $c) {
                $lastMessage = $c->messages->first();

                return [
                    'id' => $c->id,
                    'channel' => $c->channel,
                    'channel_label' => IntegrationProvider::tryFrom($c->channel)?->label() ?? $c->channel,
                    'participant_id' => $c->participant_id,
                    'participant_name' => $c->participant_name,
                    'participant_username' => $c->participant_username,
                    'last_message_at' => $c->last_message_at?->toIso8601String(),
                    'preview' => $lastMessage?->previewLabel(),
                ];
            });

        $selectedId = $request->query('conversation');
        $selectedConversation = null;
        $messages = [];

        if ($selectedId) {
            $conversation = MessengerConversation::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $selectedId)
                ->first();

            if ($conversation) {
                $selectedConversation = [
                    'id' => $conversation->id,
                    'channel' => $conversation->channel,
                    'channel_label' => IntegrationProvider::tryFrom($conversation->channel)?->label() ?? $conversation->channel,
                    'participant_name' => $conversation->participant_name,
                    'participant_username' => $conversation->participant_username,
                    'participant_id' => $conversation->participant_id,
                ];

                $messages = $conversation->messages()
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (MessengerMessage $m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'body' => $m->body,
                        'attachments' => $this->mapAttachmentsForFrontend($m),
                        'status' => $m->status,
                        'sent_at' => $m->sent_at?->toIso8601String(),
                    ]);
            }
        }

        return Inertia::render('Messenger/Index', [
            'instagramConnected' => $instagramIntegration !== null,
            'facebookConnected' => $facebookIntegration !== null,
            'instagramAccount' => $instagramIntegration ? [
                'username' => $instagramIntegration->metadata['username'] ?? null,
                'name' => $instagramIntegration->metadata['name'] ?? null,
                'page_name' => $instagramIntegration->metadata['page_name'] ?? null,
            ] : null,
            'facebookAccount' => $facebookIntegration ? [
                'page_name' => $facebookIntegration->metadata['page_name'] ?? null,
                'page_id' => $facebookIntegration->metadata['page_id'] ?? null,
            ] : null,
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'webhookUrl' => url('/webhooks/meta'),
        ]);
    }

    public function attachment(Request $request, MessengerMessage $message, int $index): BinaryFileResponse|StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($message->company_id === $companyId, 403);

        $conversation = $message->conversation()->firstOrFail();
        $integration = $this->integrationForConversation($companyId, $conversation);

        $source = $this->instagram->resolveAttachmentPlayback($integration, $message, $index);

        if ($source['type'] === 'local') {
            return response()->file($source['path'], [
                'Content-Type' => $source['mime_type'] ?? 'audio/mp4',
                'Cache-Control' => 'private, max-age=3600',
                'Accept-Ranges' => 'bytes',
            ]);
        }

        return $this->metaAttachments->streamRemoteUrl($integration, (string) $source['url']);
    }

    public function sync(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $instagramIntegration = $this->instagram->integrationForCompany($companyId);
        $facebookIntegration = $this->facebook->integrationForCompany($companyId);

        if (! $instagramIntegration && ! $facebookIntegration) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['sync' => __('Подключите Instagram или Facebook в разделе «Интеграции».')]);
        }

        if ($this->dispatchBackgroundSync($companyId)) {
            return back()->with(
                'success',
                __('Синхронизация запущена в фоне. Подождите 1–2 минуты и обновите страницу (F5).'),
            );
        }

        try {
            $result = $this->messengerSync->syncForCompany($companyId);

            if ($result['errors'] !== []) {
                return back()->withErrors([
                    'sync' => implode(' ', $result['errors']),
                ]);
            }

            return back()->with(
                'success',
                __('Диалоги обновлены: :count', ['count' => $result['synced']]),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['sync' => $e->getMessage()]);
        }
    }

    protected function dispatchBackgroundSync(int $companyId): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        if (! function_exists('exec')) {
            return false;
        }

        $logFile = storage_path('logs/messenger-sync.log');
        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        $command = sprintf(
            '%s %s messenger:sync --company=%d >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            $companyId,
            escapeshellarg($logFile),
        );

        exec($command);

        return true;
    }

    public function send(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($conversation->company_id === $companyId, 403);

        $validated = $request->validate([
            'body' => 'nullable|string|max:2000',
            'audio' => 'nullable|file|max:16384',
        ]);

        if (! $request->hasFile('audio') && trim((string) ($validated['body'] ?? '')) === '') {
            return back()->withErrors(['body' => __('Введите текст или запишите голосовое сообщение.')]);
        }

        try {
            if ($conversation->channel === IntegrationProvider::Facebook->value) {
                $integration = $this->facebook->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('Facebook не подключён.')]);
                }

                if ($request->hasFile('audio')) {
                    $this->sendAudio($this->facebook, $integration, $conversation, $request->file('audio'));
                } else {
                    $this->facebook->sendMessage($integration, $conversation, (string) $validated['body']);
                }
            } else {
                $integration = $this->instagram->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('Instagram не подключён.')]);
                }

                if ($request->hasFile('audio')) {
                    $this->sendAudio($this->instagram, $integration, $conversation, $request->file('audio'));
                } else {
                    $this->instagram->sendMessage($integration, $conversation, (string) $validated['body']);
                }
            }

            $conversation->update(['last_message_at' => now()]);

            return redirect()
                ->route('messenger.index', ['conversation' => $conversation->id])
                ->with('success', __('Сообщение отправлено.'));
        } catch (RequestException $e) {
            return back()->withErrors([
                'body' => MetaMessagingSupport::formatGraphError($e->response?->json(), $e->getMessage()),
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }
    }

    /**
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string}>
     */
    protected function mapAttachmentsForFrontend(MessengerMessage $message): array
    {
        return collect($message->normalizedAttachments())
            ->values()
            ->map(function (array $attachment, int $index) use ($message) {
                $hasSource = ($attachment['url'] ?? '') !== ''
                    || ($attachment['storage_path'] ?? '') !== '';

                return [
                    'type' => $attachment['type'] ?? 'file',
                    'url' => $hasSource
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
        if ($conversation->channel === IntegrationProvider::Facebook->value) {
            $integration = $this->facebook->integrationForCompany($companyId);
            if (! $integration) {
                throw new \RuntimeException(__('Facebook не подключён.'));
            }

            return $integration;
        }

        $integration = $this->instagram->integrationForCompany($companyId);
        if (! $integration) {
            throw new \RuntimeException(__('Instagram не подключён.'));
        }

        return $integration;
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
}
