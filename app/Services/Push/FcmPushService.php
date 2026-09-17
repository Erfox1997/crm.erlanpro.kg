<?php

namespace App\Services\Push;

use App\Models\DevicePushToken;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\User;
use App\Services\Messenger\ChatDistributionService;
use App\Services\Messenger\MessengerUnreadService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    public function __construct(
        private ChatDistributionService $chatDistribution,
        private MessengerUnreadService $unread,
    ) {}

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
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
            $unread = max(1, $this->unread->totalUnreadForCompany((int) $user->company_id, $user));

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
        $credentials = $this->credentials();
        $accessToken = $this->accessToken();
        $projectId = (string) ($credentials['project_id'] ?? '');

        if ($credentials === null || $accessToken === null || $projectId === '' || $token === '') {
            return;
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => array_merge($data, [
                                'badge' => (string) $badge,
                            ]),
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'channel_id' => 'messenger',
                                    'notification_count' => $badge,
                                    'sound' => 'default',
                                    'tag' => $data['conversation_id'] ?? 'messenger',
                                ],
                            ],
                        ],
                    ],
                );

            if ($response->failed()) {
                Log::warning('FCM send failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                $errorCode = (string) data_get($response->json(), 'error.status', '');
                $errorDetails = (string) data_get($response->json(), 'error.details.0.errorCode', '');

                if (
                    in_array($response->status(), [404], true)
                    || in_array($errorCode, ['NOT_FOUND', 'INVALID_ARGUMENT'], true)
                    || in_array($errorDetails, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)
                ) {
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
     * @return array{project_id?: string, client_email?: string, private_key?: string, token_uri?: string}|null
     */
    protected function credentials(): ?array
    {
        $path = trim((string) config('services.fcm.credentials', ''));
        if ($path === '') {
            return null;
        }

        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key']) || empty($json['project_id'])) {
            return null;
        }

        return $json;
    }

    protected function accessToken(): ?string
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            return null;
        }

        $cached = Cache::get('fcm_access_token_v1');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsigned = $header.'.'.$claim;
        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);
        if ($privateKey === false) {
            Log::warning('FCM credentials: invalid private key');

            return null;
        }

        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            Log::warning('FCM credentials: failed to sign JWT');

            return null;
        }

        $jwt = $unsigned.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout(15)
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if ($response->failed()) {
            Log::warning('FCM OAuth token failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        $accessToken = $response->json('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            return null;
        }

        Cache::put('fcm_access_token_v1', $accessToken, now()->addMinutes(55));

        return $accessToken;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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
