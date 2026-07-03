<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Services\Instagram\InstagramMessengerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessengerController extends Controller
{
    public function __construct(
        private InstagramMessengerService $instagram,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $integration = $this->instagram->integrationForCompany($companyId);

        $conversations = MessengerConversation::query()
            ->where('company_id', $companyId)
            ->where('channel', IntegrationProvider::Instagram->value)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MessengerConversation $c) => [
                'id' => $c->id,
                'channel' => $c->channel,
                'participant_id' => $c->participant_id,
                'participant_name' => $c->participant_name,
                'participant_username' => $c->participant_username,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'preview' => $c->messages()->orderByDesc('sent_at')->orderByDesc('id')->value('body'),
            ]);

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
                        'status' => $m->status,
                        'sent_at' => $m->sent_at?->toIso8601String(),
                    ]);
            }
        }

        return Inertia::render('Messenger/Index', [
            'instagramConnected' => $integration !== null,
            'instagramAccount' => $integration ? [
                'username' => $integration->metadata['username'] ?? null,
                'name' => $integration->metadata['name'] ?? null,
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
        $integration = $this->instagram->integrationForCompany($companyId);

        if (! $integration) {
            return redirect()
                ->route('integrations.index')
                ->withErrors(['instagram' => __('Подключите Instagram в разделе «Интеграции».')]);
        }

        try {
            if (! ($integration->metadata['instagram_user_id'] ?? null)) {
                $integration = $this->instagram->refreshIntegrationMetadata($integration);
            }

            $result = $this->instagram->syncConversations($integration);

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

    public function send(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($conversation->company_id === $companyId, 403);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $integration = $this->instagram->integrationForCompany($companyId);
        if (! $integration) {
            return back()->withErrors(['body' => __('Instagram не подключён.')]);
        }

        try {
            $this->instagram->sendMessage($integration, $conversation, $validated['body']);

            $conversation->update(['last_message_at' => now()]);

            return redirect()
                ->route('messenger.index', ['conversation' => $conversation->id])
                ->with('success', __('Сообщение отправлено.'));
        } catch (\Throwable $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }
    }
}
