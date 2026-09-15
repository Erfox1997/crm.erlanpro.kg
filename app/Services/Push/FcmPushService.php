<?php

namespace App\Services\Push;

use App\Models\DevicePushToken;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\User;
use App\Services\Messenger\ChatDistributionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    public function __construct(
        private ChatDistributionService $chatDistribution,
    ) {}

    public function isConfigured(): bool
    {
        return trim((string) config('services.fcm.server_key', '')) !== '';
    }

    public function notifyNewInboundMessage(MessengerMessage $message): void
    {
        if (! $this->isConfigured() || $message->direction !== 'inbound') {
            return;
        }

        $conversation = $message->conversation()->first();
        if (! $conversation instanceof MessengerConversation) {
            return;
        }

        $recipients = $this->recipientsForConversation($conversation);
        if ($recipients->isEmpty()) {
            return;
        }

        $name = $conversation->participant_name
            ?: $conversation->participant_username
            ?: $conversation->participant_id
            ?: 'Клиент';

        $preview = $message->previewLabel();
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 117).'...';
        }

        foreach ($recipients as $user) {
            $unread = max(1, (int) ($user->messenger_unread_hint ?? 1));
            $this->sendToUser(
                $user,
                'Новое сообщение',
                "{$name}: {$preview}",
                [
                    'conversation_id' => (string) $conversation->id,
                    'type' => 'messenger_inbound',
                ],
                $unread,
            );
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], int $badge = 1): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $tokens = DevicePushToken::query()
            ->where('user_id', $user->id)
            ->pluck('token')
            ->filter()
            ->unique()
            ->values();

        foreach ($tokens as $token) {
            $this->sendToToken((string) $token, $title, $body, $data, $badge);
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], int $badge = 1): void
    {
        $serverKey = trim((string) config('services.fcm.server_key', ''));
        if ($serverKey === '' || $token === '') {
            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
            ])
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                        'badge' => $badge,
                        'tag' => $data['conversation_id'] ?? 'messenger',
                    ],
                    'data' => array_merge($data, [
                        'badge' => (string) $badge,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'notification_count' => $badge,
                            'channel_id' => 'messenger',
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('FCM send failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                // Drop invalid tokens.
                if (in_array($response->status(), [400, 404], true)
                    || ($response->json('results.0.error') ?? null) === 'NotRegistered') {
                    DevicePushToken::query()->where('token', $token)->delete();
                }
            } else {
                DevicePushToken::query()
                    ->where('token', $token)
                    ->update(['last_used_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM send exception', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function recipientsForConversation(MessengerConversation $conversation): Collection
    {
        $query = User::query()
            ->where('company_id', $conversation->company_id);

        if ($conversation->assigned_user_id) {
            return $query->where('id', $conversation->assigned_user_id)->get();
        }

        return $query
            ->where(function ($inner) {
                $inner->where('company_role', 'owner')
                    ->orWhereNull('company_role')
                    ->orWhere('company_role', 'employee');
            })
            ->get()
            ->filter(function (User $user) use ($conversation) {
                return $this->chatDistribution->userCanViewConversation($user, $conversation);
            })
            ->values();
    }
}
