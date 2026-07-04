<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessengerController extends Controller
{
    public function __construct(
        private InstagramMessengerService $instagram,
        private FacebookMessengerService $facebook,
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
                        'attachments' => $m->normalizedAttachments(),
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

        $errors = [];
        $synced = 0;

        try {
            if ($instagramIntegration) {
                if (! ($instagramIntegration->metadata['instagram_user_id'] ?? null)) {
                    $instagramIntegration = $this->instagram->refreshIntegrationMetadata($instagramIntegration);
                }

                $result = $this->instagram->syncConversations($instagramIntegration);
                $synced += $result['synced'];
                $errors = array_merge($errors, $result['errors']);
            }

            if ($facebookIntegration) {
                if (! ($facebookIntegration->metadata['page_id'] ?? null)) {
                    $facebookIntegration = $this->facebook->refreshIntegrationMetadata($facebookIntegration);
                }

                $result = $this->facebook->syncConversations($facebookIntegration);
                $synced += $result['synced'];
                $errors = array_merge($errors, $result['errors']);
            }

            if ($errors !== []) {
                return back()->withErrors([
                    'sync' => implode(' ', $errors),
                ]);
            }

            return back()->with(
                'success',
                __('Диалоги обновлены: :count', ['count' => $synced]),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['sync' => $e->getMessage()]);
        }
    }

    public function send(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($conversation->company_id === $companyId, 403);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        try {
            if ($conversation->channel === IntegrationProvider::Facebook->value) {
                $integration = $this->facebook->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('Facebook не подключён.')]);
                }

                $this->facebook->sendMessage($integration, $conversation, $validated['body']);
            } else {
                $integration = $this->instagram->integrationForCompany($companyId);
                if (! $integration) {
                    return back()->withErrors(['body' => __('Instagram не подключён.')]);
                }

                $this->instagram->sendMessage($integration, $conversation, $validated['body']);
            }

            $conversation->update(['last_message_at' => now()]);

            return redirect()
                ->route('messenger.index', ['conversation' => $conversation->id])
                ->with('success', __('Сообщение отправлено.'));
        } catch (\Throwable $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }
    }
}
