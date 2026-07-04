<?php

namespace App\Services\Instagram;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class InstagramMessengerService
{
    public function graphVersion(): string
    {
        return (string) config('services.meta.graph_version', 'v21.0');
    }

    public function integrationForCompany(int $companyId): ?CompanyIntegration
    {
        return CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', IntegrationProvider::Instagram->value)
            ->whereNotNull('api_token')
            ->first();
    }

    public function oauthRedirectUri(): string
    {
        $configured = config('services.meta.oauth_redirect_uri');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return route('integrations.instagram.callback', absolute: true);
    }

    public function oauthProvider(): string
    {
        $provider = (string) config('services.meta.oauth_provider', 'facebook');

        return $provider === 'instagram' ? 'instagram' : 'facebook';
    }

    public function oauthAuthorizationUrl(string $state): string
    {
        $appId = self::normalizeAppId((string) config('services.instagram.app_id'));
        if ($appId === '') {
            throw new \RuntimeException(__('INSTAGRAM_APP_ID не задан в .env'));
        }

        if (! preg_match('/^\d{10,20}$/', $appId)) {
            throw new \RuntimeException(__('INSTAGRAM_APP_ID должен быть числовым ID приложения из Meta → Настройки → Основное → ID приложения.'));
        }

        $query = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $this->oauthRedirectUri(),
            'scope' => (string) config('services.meta.oauth_scopes'),
            'response_type' => 'code',
            'state' => $state,
        ]);

        if ($this->oauthProvider() === 'instagram') {
            return 'https://www.instagram.com/oauth/authorize?'.$query;
        }

        // OAuth dialog must be unversioned; /v21.0/dialog/oauth returns PLATFORM_INVALID_APP_ID.
        return 'https://www.facebook.com/dialog/oauth?'.$query;
    }

    public function exchangeCodeForLongLivedUserToken(string $code): string
    {
        if ($this->oauthProvider() === 'instagram') {
            return $this->exchangeInstagramCodeForLongLivedUserToken($code);
        }

        return $this->exchangeFacebookCodeForLongLivedUserToken($code);
    }

    public function exchangeFacebookCodeForLongLivedUserToken(string $code): string
    {
        $shortLivedToken = $this->requestFacebookAccessToken([
            'client_id' => config('services.instagram.app_id'),
            'client_secret' => config('services.instagram.app_secret'),
            'redirect_uri' => $this->oauthRedirectUri(),
            'code' => $code,
        ]);

        return $this->requestFacebookAccessToken([
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.instagram.app_id'),
            'client_secret' => config('services.instagram.app_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);
    }

    public function exchangeInstagramCodeForLongLivedUserToken(string $code): string
    {
        $code = preg_replace('/#_.*$/', '', $code) ?? $code;

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://api.instagram.com/oauth/access_token', [
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->oauthRedirectUri(),
                'code' => $code,
            ]);

        $response->throw();

        $shortLivedToken = (string) ($response->json('access_token') ?? '');
        if ($shortLivedToken === '') {
            throw new \RuntimeException(__('Meta не вернула short-lived access token.'));
        }

        return $this->exchangeForLongLivedToken($shortLivedToken);
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): string
    {
        $response = Http::acceptJson()
            ->timeout(30)
            ->get('https://graph.instagram.com/access_token', [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => config('services.instagram.app_secret'),
                'access_token' => $shortLivedToken,
            ]);

        $response->throw();

        $token = (string) ($response->json('access_token') ?? '');
        if ($token === '') {
            throw new \RuntimeException(__('Meta не вернула long-lived access token.'));
        }

        return $token;
    }

    /**
     * @return array{api_token: string, metadata: array<string, mixed>}
     */
    public function connectAccountFromManualToken(string $accessToken): array
    {
        $accessToken = self::normalizeAccessToken($accessToken);

        if (str_starts_with($accessToken, 'EAA')) {
            try {
                $response = $this->client($accessToken, 'facebook_login')->get(
                    $this->url('me', 'facebook_login'),
                    ['fields' => 'id,name,instagram_business_account{id,username,name}'],
                );
                $response->throw();
                $data = $response->json();
                $igAccount = $data['instagram_business_account'] ?? [];

                if (is_array($igAccount) && ($igAccount['id'] ?? null)) {
                    return [
                        'api_token' => $accessToken,
                        'metadata' => [
                            'instagram_user_id' => (string) $igAccount['id'],
                            'username' => $igAccount['username'] ?? null,
                            'name' => $igAccount['name'] ?? null,
                            'page_id' => (string) ($data['id'] ?? ''),
                            'page_name' => $data['name'] ?? null,
                            'auth_mode' => 'facebook_login',
                            'connected_via' => 'manual',
                        ],
                    ];
                }
            } catch (\Throwable) {
                // Fall through to user-token resolution below.
            }

            try {
                $account = $this->resolveInstagramPageAccount($accessToken);

                return [
                    'api_token' => $account['access_token'],
                    'metadata' => [
                        'instagram_user_id' => $account['instagram_user_id'],
                        'username' => $account['username'],
                        'name' => $account['name'],
                        'page_id' => $account['page_id'],
                        'page_name' => $account['page_name'],
                        'auth_mode' => 'facebook_login',
                        'connected_via' => 'manual',
                    ],
                ];
            } catch (\Throwable) {
                // Fall through to Instagram Login token validation.
            }
        }

        $profile = $this->fetchProfile($accessToken);

        return [
            'api_token' => $accessToken,
            'metadata' => [
                'instagram_user_id' => $profile['id'],
                'username' => $profile['username'],
                'name' => $profile['name'],
                'auth_mode' => 'instagram_login',
                'connected_via' => 'manual',
            ],
        ];
    }

    public function connectAccountFromOAuth(string $userAccessToken): array
    {
        if ($this->oauthProvider() === 'instagram') {
            $account = $this->resolveInstagramLoginAccount($userAccessToken);

            return [
                'api_token' => $account['access_token'],
                'metadata' => [
                    'instagram_user_id' => $account['instagram_user_id'],
                    'username' => $account['username'],
                    'name' => $account['name'],
                    'auth_mode' => 'instagram_login',
                    'connected_via' => 'oauth',
                ],
            ];
        }

        $account = $this->resolveInstagramPageAccount($userAccessToken);

        return [
            'api_token' => $account['access_token'],
            'metadata' => [
                'instagram_user_id' => $account['instagram_user_id'],
                'username' => $account['username'],
                'name' => $account['name'],
                'page_id' => $account['page_id'],
                'page_name' => $account['page_name'],
                'auth_mode' => 'facebook_login',
                'connected_via' => 'oauth',
            ],
        ];
    }

    /**
     * @return array{
     *     access_token: string,
     *     instagram_user_id: string,
     *     username: ?string,
     *     name: ?string
     * }
     */
    public function resolveInstagramLoginAccount(string $accessToken, ?string $userId = null): array
    {
        $profile = $this->fetchInstagramLoginProfile($accessToken);

        return [
            'access_token' => $accessToken,
            'instagram_user_id' => $userId ?: $profile['id'],
            'username' => $profile['username'],
            'name' => $profile['name'],
        ];
    }

    /**
     * @return array{id: string, username: ?string, name: ?string}
     */
    public function fetchInstagramLoginProfile(string $accessToken): array
    {
        $response = $this->client($accessToken, 'instagram_login')->get(
            $this->url('me', 'instagram_login'),
            ['fields' => 'user_id,username,name'],
        );

        $response->throw();

        $data = $response->json();

        return [
            'id' => (string) ($data['user_id'] ?? $data['id'] ?? ''),
            'username' => $data['username'] ?? null,
            'name' => $data['name'] ?? null,
        ];
    }

    /**
     * @return array{
     *     page_id: string,
     *     page_name: ?string,
     *     access_token: string,
     *     instagram_user_id: string,
     *     username: ?string,
     *     name: ?string
     * }
     */
    public function resolveInstagramPageAccount(string $userAccessToken): array
    {
        $response = $this->client($userAccessToken, 'facebook_login')->get($this->url('me/accounts', 'facebook_login'), [
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name}',
        ]);

        $response->throw();

        foreach ($response->json('data', []) as $page) {
            $igAccount = $page['instagram_business_account'] ?? null;

            if (! is_array($igAccount) || empty($igAccount['id'])) {
                continue;
            }

            $pageToken = (string) ($page['access_token'] ?? '');
            if ($pageToken === '') {
                continue;
            }

            return [
                'page_id' => (string) $page['id'],
                'page_name' => $page['name'] ?? null,
                'access_token' => $pageToken,
                'instagram_user_id' => (string) $igAccount['id'],
                'username' => $igAccount['username'] ?? null,
                'name' => $igAccount['name'] ?? null,
            ];
        }

        throw new \RuntimeException(
            __('Не найден Instagram Business/Creator аккаунт, привязанный к странице Facebook. Подключите erlanpro.kg к странице в Meta Business Suite.'),
        );
    }

    /**
     * @param  array<string, string|null>  $params
     */
    protected function requestFacebookAccessToken(array $params): string
    {
        $response = Http::acceptJson()
            ->timeout(30)
            ->get($this->url('oauth/access_token', 'facebook_login'), array_filter($params));

        $response->throw();

        $token = (string) ($response->json('access_token') ?? '');
        if ($token === '') {
            throw new \RuntimeException(__('Meta не вернула access token.'));
        }

        return $token;
    }

    public static function normalizeAppId(string $appId): string
    {
        return trim($appId, " \t\n\r\0\x0B\"'");
    }

    public static function normalizeAccessToken(string $token): string
    {
        $token = trim($token);
        $token = preg_replace('/\s+/', '', $token) ?? $token;

        if (str_starts_with(strtolower($token), 'bearer')) {
            $token = trim(substr($token, 6));
        }

        return trim($token, " \t\n\r\0\x0B\"'");
    }

    /**
     * @return array{id: string, username: ?string, name: ?string}
     */
    public function fetchProfile(string $accessToken): array
    {
        $accessToken = self::normalizeAccessToken($accessToken);

        if ($accessToken === '' || strlen($accessToken) < 20) {
            throw new \InvalidArgumentException(__('Маркер доступа пустой или слишком короткий. Скопируйте полную строку из Meta for Developers.'));
        }

        try {
            return $this->fetchInstagramLoginProfile($accessToken);
        } catch (\Throwable) {
            // Fallback for page tokens from Facebook Login.
        }

        $response = $this->client($accessToken, 'facebook_login')->get($this->url('me', 'facebook_login'), [
            'fields' => 'id,username,name,instagram_business_account{id,username,name}',
        ]);

        $response->throw();

        $data = $response->json();
        $igAccount = $data['instagram_business_account'] ?? null;

        if (is_array($igAccount) && ($igAccount['id'] ?? null)) {
            return [
                'id' => (string) $igAccount['id'],
                'username' => $igAccount['username'] ?? null,
                'name' => $igAccount['name'] ?? null,
            ];
        }

        return [
            'id' => (string) ($data['id'] ?? ''),
            'username' => $data['username'] ?? null,
            'name' => $data['name'] ?? null,
        ];
    }

    public function refreshIntegrationMetadata(CompanyIntegration $integration): CompanyIntegration
    {
        $authMode = $this->authMode($integration);

        if ($authMode === 'facebook_login') {
            $response = $this->client((string) $integration->api_token, 'facebook_login')->get(
                $this->url('me', 'facebook_login'),
                ['fields' => 'id,name,instagram_business_account{id,username,name}'],
            );
            $response->throw();
            $data = $response->json();
            $igAccount = $data['instagram_business_account'] ?? [];

            $metadata = $integration->metadata ?? [];
            $metadata['page_id'] = (string) ($data['id'] ?? $metadata['page_id'] ?? '');
            $metadata['page_name'] = $data['name'] ?? $metadata['page_name'] ?? null;
            $metadata['instagram_user_id'] = (string) ($igAccount['id'] ?? $metadata['instagram_user_id'] ?? '');
            $metadata['username'] = $igAccount['username'] ?? $metadata['username'] ?? null;
            $metadata['name'] = $igAccount['name'] ?? $metadata['name'] ?? null;
        } else {
            $profile = $this->fetchProfile((string) $integration->api_token);
            $metadata = $integration->metadata ?? [];
            $metadata['instagram_user_id'] = $profile['id'];
            $metadata['username'] = $profile['username'];
            $metadata['name'] = $profile['name'];
        }

        $integration->update(['metadata' => $metadata]);

        return $integration->fresh();
    }

    /**
     * @return array{synced: int, errors: list<string>}
     */
    public function syncConversations(CompanyIntegration $integration): array
    {
        if (! ($integration->metadata['instagram_user_id'] ?? null)) {
            $integration = $this->refreshIntegrationMetadata($integration);
        }

        $igUserId = (string) ($integration->metadata['instagram_user_id'] ?? '');
        if ($igUserId === '') {
            return ['synced' => 0, 'errors' => [__('Не удалось определить ID аккаунта Instagram.')]];
        }

        $errors = [];
        $synced = 0;

        try {
            $authMode = $this->authMode($integration);
            $query = ['fields' => 'id,updated_time,participants'];

            if ($authMode === 'facebook_login') {
                $pageId = (string) ($integration->metadata['page_id'] ?? '');
                if ($pageId === '') {
                    return ['synced' => 0, 'errors' => [__('Не задан page_id Facebook для Instagram.')]];
                }

                $query['platform'] = 'instagram';
                $conversationsPath = "{$pageId}/conversations";
            } else {
                $conversationsPath = "{$igUserId}/conversations";
            }

            $response = $this->client($integration->api_token, $authMode)->get(
                $this->url($conversationsPath, $authMode),
                $query,
            );

            $response->throw();

            $conversations = $response->json('data', []);

            foreach ($conversations as $item) {
                try {
                    $this->syncConversation($integration, $igUserId, $item);
                    $synced++;
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        } catch (RequestException $e) {
            $errors[] = $this->formatApiError($e);
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $conversationData
     */
    protected function syncConversation(
        CompanyIntegration $integration,
        string $igUserId,
        array $conversationData,
    ): void {
        $participant = $this->resolveParticipant($conversationData, $igUserId);
        if (! $participant) {
            return;
        }

        $conversation = MessengerConversation::query()->updateOrCreate(
            [
                'company_id' => $integration->company_id,
                'channel' => IntegrationProvider::Instagram->value,
                'participant_id' => $participant['id'],
            ],
            [
                'external_id' => isset($conversationData['id']) ? (string) $conversationData['id'] : null,
                'participant_name' => $participant['name'],
                'participant_username' => $participant['username'],
            ],
        );

        $externalId = (string) ($conversationData['id'] ?? '');
        if ($externalId === '') {
            return;
        }

        $authMode = $this->authMode($integration);

        if ($authMode === 'instagram_login') {
            $messagesResponse = $this->client($integration->api_token, $authMode)->get(
                $this->url($externalId, $authMode),
                ['fields' => 'messages{id,created_time,from,to,message}'],
            );
        } else {
            $messagesResponse = $this->client($integration->api_token, $authMode)->get(
                $this->url($externalId, $authMode),
                ['fields' => 'messages{message,from,created_time,id}'],
            );
        }

        $messagesResponse->throw();

        $messageItems = $messagesResponse->json('messages.data', []);

        foreach ($messageItems as $messageData) {
            $this->storeMessageFromApi($integration, $conversation, $igUserId, $messageData);
        }

        $last = $conversation->messages()->orderByDesc('sent_at')->orderByDesc('id')->first();
        if ($last?->sent_at) {
            $conversation->update(['last_message_at' => $last->sent_at]);
        }
    }

    /**
     * @param  array<string, mixed>  $conversationData
     * @return array{id: string, name: ?string, username: ?string}|null
     */
    protected function resolveParticipant(array $conversationData, string $igUserId): ?array
    {
        $participants = $conversationData['participants']['data'] ?? [];

        foreach ($participants as $p) {
            $id = (string) ($p['id'] ?? '');
            if ($id !== '' && $id !== $igUserId) {
                return [
                    'id' => $id,
                    'name' => $p['name'] ?? $p['username'] ?? null,
                    'username' => $p['username'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    public function storeMessageFromApi(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $igUserId,
        array $messageData,
    ): ?MessengerMessage {
        $externalId = isset($messageData['id']) ? (string) $messageData['id'] : null;
        if ($externalId) {
            $exists = MessengerMessage::query()
                ->where('messenger_conversation_id', $conversation->id)
                ->where('external_id', $externalId)
                ->exists();
            if ($exists) {
                return null;
            }
        }

        $fromId = (string) ($messageData['from']['id'] ?? '');
        $fromUsername = (string) ($messageData['from']['username'] ?? '');
        $ownUsername = (string) ($integration->metadata['username'] ?? '');
        $isOutbound = $fromId === $igUserId
            || ($ownUsername !== '' && strcasecmp($fromUsername, $ownUsername) === 0);
        $direction = $isOutbound ? 'outbound' : 'inbound';
        $sentAt = isset($messageData['created_time'])
            ? Carbon::parse($messageData['created_time'])
            : now();

        $message = MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => $direction,
            'external_id' => $externalId,
            'body' => $messageData['message'] ?? '',
            'status' => $direction === 'outbound' ? 'sent' : 'received',
            'sent_at' => $sentAt,
        ]);

        if ($sentAt->greaterThan($conversation->last_message_at ?? $sentAt->copy()->subYear())) {
            $conversation->update(['last_message_at' => $sentAt]);
        }

        return $message;
    }

    public function sendMessage(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $text,
    ): MessengerMessage {
        $igUserId = (string) ($integration->metadata['instagram_user_id'] ?? '');
        if ($igUserId === '') {
            $integration = $this->refreshIntegrationMetadata($integration);
            $igUserId = (string) ($integration->metadata['instagram_user_id'] ?? '');
        }

        if ($igUserId === '') {
            throw new \RuntimeException(__('Instagram не настроен: нет ID аккаунта.'));
        }

        $authMode = $this->authMode($integration);
        $pageId = (string) ($integration->metadata['page_id'] ?? '');

        if ($authMode === 'facebook_login') {
            if ($pageId === '') {
                throw new \RuntimeException(__('Instagram не настроен: нет page_id Facebook.'));
            }

            $messagesPath = "{$pageId}/messages";
        } else {
            $messagesPath = "{$igUserId}/messages";
        }

        $response = $this->client($integration->api_token, $authMode)->post(
            $this->url($messagesPath, $authMode),
            [
                'recipient' => ['id' => $conversation->participant_id],
                'message' => ['text' => $text],
            ],
        );

        $response->throw();

        $messageId = (string) ($response->json('message_id') ?? $response->json('id') ?? '');

        return MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'external_id' => $messageId !== '' ? $messageId : null,
            'body' => $text,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookPayload(array $payload): int
    {
        if (($payload['object'] ?? '') !== 'instagram') {
            return 0;
        }

        $processed = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            $igAccountId = (string) ($entry['id'] ?? '');
            $integration = $this->findIntegrationByInstagramAccountId($igAccountId);
            if (! $integration) {
                continue;
            }

            foreach ($entry['messaging'] ?? [] as $event) {
                if ($this->processMessagingEvent($integration, $igAccountId, $event)) {
                    $processed++;
                }
            }
        }

        return $processed;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function processMessagingEvent(
        CompanyIntegration $integration,
        string $igAccountId,
        array $event,
    ): bool {
        $message = $event['message'] ?? null;
        if (! is_array($message) || ! isset($message['mid'])) {
            return false;
        }

        $senderId = (string) ($event['sender']['id'] ?? '');
        $recipientId = (string) ($event['recipient']['id'] ?? '');
        $customerId = $senderId === $igAccountId ? $recipientId : $senderId;

        if ($customerId === '' || $customerId === $igAccountId) {
            return false;
        }

        $conversation = MessengerConversation::query()->firstOrCreate(
            [
                'company_id' => $integration->company_id,
                'channel' => IntegrationProvider::Instagram->value,
                'participant_id' => $customerId,
            ],
            [
                'participant_name' => null,
                'participant_username' => null,
            ],
        );

        $externalId = (string) $message['mid'];
        if (
            MessengerMessage::query()
                ->where('messenger_conversation_id', $conversation->id)
                ->where('external_id', $externalId)
                ->exists()
        ) {
            return false;
        }

        $direction = $senderId === $igAccountId ? 'outbound' : 'inbound';
        $sentAt = isset($event['timestamp'])
            ? Carbon::createFromTimestampMs((int) $event['timestamp'])
            : now();

        MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => $direction,
            'external_id' => $externalId,
            'body' => $message['text'] ?? '',
            'status' => $direction === 'outbound' ? 'sent' : 'received',
            'sent_at' => $sentAt,
        ]);

        $conversation->update(['last_message_at' => $sentAt]);

        return true;
    }

    protected function findIntegrationByInstagramAccountId(string $instagramAccountId): ?CompanyIntegration
    {
        if ($instagramAccountId === '') {
            return null;
        }

        return CompanyIntegration::query()
            ->where('provider', IntegrationProvider::Instagram->value)
            ->whereNotNull('api_token')
            ->get()
            ->first(fn (CompanyIntegration $i) => (string) ($i->metadata['instagram_user_id'] ?? '') === $instagramAccountId);
    }

    protected function authMode(?CompanyIntegration $integration): string
    {
        $mode = (string) ($integration?->metadata['auth_mode'] ?? '');

        if ($mode === 'instagram_login') {
            return 'instagram_login';
        }

        if ($mode === 'facebook_login') {
            return 'facebook_login';
        }

        return $this->oauthProvider() === 'instagram' ? 'instagram_login' : 'facebook_login';
    }

    protected function client(string $accessToken, ?string $authMode = null): \Illuminate\Http\Client\PendingRequest
    {
        $accessToken = self::normalizeAccessToken($accessToken);

        return Http::acceptJson()
            ->timeout(30)
            ->withToken($accessToken);
    }

    protected function url(string $path, ?string $authMode = 'instagram_login'): string
    {
        if ($path === 'oauth/access_token') {
            return 'https://graph.facebook.com/'.$this->graphVersion().'/oauth/access_token';
        }

        $host = $authMode === 'facebook_login'
            ? 'https://graph.facebook.com/'
            : 'https://graph.instagram.com/';

        return $host.$this->graphVersion().'/'.ltrim($path, '/');
    }

    protected function formatApiError(RequestException $e): string
    {
        $body = $e->response?->json();
        $message = $body['error']['message'] ?? $e->getMessage();

        return (string) $message;
    }
}
