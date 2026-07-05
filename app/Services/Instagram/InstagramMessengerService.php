<?php

namespace App\Services\Instagram;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Services\Meta\MetaAttachmentService;
use App\Services\Meta\MetaMessagingSupport;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class InstagramMessengerService
{
    public function __construct(
        private MetaAttachmentService $metaAttachments,
    ) {}

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
    public function syncConversations(CompanyIntegration $integration, int $days = 1): array
    {
        if (! ($integration->metadata['instagram_user_id'] ?? null)) {
            $integration = $this->refreshIntegrationMetadata($integration);
        }

        $igUserId = (string) ($integration->metadata['instagram_user_id'] ?? '');
        if ($igUserId === '') {
            return ['synced' => 0, 'errors' => [__('Не удалось определить ID аккаунта Instagram.')]];
        }

        $since = $this->syncSinceFromDays($days);
        $errors = [];
        $synced = 0;

        try {
            $authMode = $this->authMode($integration);
            $query = [
                'fields' => 'id,updated_time,participants',
                'since' => $since->timestamp,
            ];

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
                if (! $this->isConversationWithinSyncWindow($item, $since)) {
                    continue;
                }

                try {
                    $this->syncConversation($integration, $igUserId, $item, $since);
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

    public function syncSinceFromDays(int $days): Carbon
    {
        return Carbon::now()->subDays(max(1, $days));
    }

    /**
     * @param  array<string, mixed>  $conversationData
     */
    public function isConversationWithinSyncWindow(array $conversationData, Carbon $since): bool
    {
        if (! isset($conversationData['updated_time'])) {
            return true;
        }

        return Carbon::parse($conversationData['updated_time'])->greaterThanOrEqualTo($since);
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    public function isMessageWithinSyncWindow(array $messageData, Carbon $since): bool
    {
        if (! isset($messageData['created_time'])) {
            return true;
        }

        return Carbon::parse($messageData['created_time'])->greaterThanOrEqualTo($since);
    }

    public function recentMessagesFields(): string
    {
        return 'messages.limit(30){message,from,created_time,id,'.MetaAttachmentService::ATTACHMENT_FIELDS.'}';
    }

    /**
     * @param  array<string, mixed>  $conversationData
     */
    protected function syncConversation(
        CompanyIntegration $integration,
        string $igUserId,
        array $conversationData,
        ?Carbon $since = null,
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
        $messageFields = $authMode === 'instagram_login'
            ? 'messages.limit(30){id,created_time,from,to,message,'.MetaAttachmentService::ATTACHMENT_FIELDS.'}'
            : $this->recentMessagesFields();

        $messagesResponse = $this->client($integration->api_token, $authMode)->get(
            $this->url($externalId, $authMode),
            ['fields' => $messageFields],
        );

        $messagesResponse->throw();

        $messageItems = $messagesResponse->json('messages.data', []);

        foreach ($messageItems as $messageData) {
            if ($since && ! $this->isMessageWithinSyncWindow($messageData, $since)) {
                continue;
            }

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
        $attachments = $this->resolveAttachmentsForMessage($integration, $messageData);
        $body = (string) ($messageData['message'] ?? '');

        if ($externalId) {
            $existing = MessengerMessage::query()
                ->where('messenger_conversation_id', $conversation->id)
                ->where('external_id', $externalId)
                ->first();

            if ($existing) {
                $updates = [];

                if ($body !== '' && trim((string) $existing->body) === '') {
                    $updates['body'] = $body;
                }

                if (! $this->attachmentsHavePlayableMedia($existing->normalizedAttachments())) {
                    $resolved = $this->attachmentsHavePlayableMedia($attachments)
                        ? $attachments
                        : ($externalId ? $this->fetchMessageAttachmentsFromApi($integration, $externalId) : []);

                    if ($resolved !== []) {
                        $updates['attachments'] = $resolved;
                    }
                }

                if ($updates !== []) {
                    $existing->update($updates);
                }

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
            'body' => $body,
            'attachments' => $attachments !== [] ? $attachments : null,
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

            $response = MetaMessagingSupport::client($integration->api_token)->post(
                MetaMessagingSupport::graphUrl("{$pageId}/messages", 'instagram'),
                [
                    'recipient' => ['id' => $conversation->participant_id],
                    'message' => ['text' => $text],
                    'messaging_type' => 'RESPONSE',
                ],
            );
        } else {
            $response = $this->client($integration->api_token, $authMode)->post(
                $this->url("{$igUserId}/messages", $authMode),
                [
                    'recipient' => ['id' => $conversation->participant_id],
                    'message' => ['text' => $text],
                ],
            );
        }

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

    public function sendAudioMessage(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $filePath,
        string $originalName,
        ?string $mimeType = null,
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

            $messagesEntityId = $pageId;
            $uploadEntityId = $pageId;
            $instagramPlatform = true;
        } else {
            $messagesEntityId = $igUserId;
            $uploadEntityId = null;
            $instagramPlatform = false;
        }

        $result = $this->metaAttachments->sendAudio(
            $integration,
            $filePath,
            $originalName,
            $mimeType,
            $authMode,
            $conversation->participant_id,
            $messagesEntityId,
            $uploadEntityId,
            $instagramPlatform,
        );

        $storedPath = $this->metaAttachments->storeSentAudioCopy(
            $integration->company_id,
            $result['prepared_path'],
            $result['prepared_name'],
        );

        return MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'external_id' => $result['message_id'] !== '' ? $result['message_id'] : null,
            'body' => '',
            'attachments' => [[
                'type' => 'audio',
                'url' => '',
                'name' => $result['prepared_name'],
                'mime_type' => $result['prepared_mime'],
                'storage_path' => $storedPath,
            ]],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function storeLocalAudioCopy(int $companyId, string $filePath, string $originalName): string
    {
        return $this->metaAttachments->storeSentAudioCopy($companyId, $filePath, $originalName);
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
                if ($this->processInstagramMessagingEvent($integration, $igAccountId, $event)) {
                    $processed++;
                }
            }
        }

        return $processed;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function processInstagramMessagingEvent(
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

        $attachments = $this->resolveWebhookAttachments($integration, $message);

        MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => $direction,
            'external_id' => $externalId,
            'body' => (string) ($message['text'] ?? ''),
            'attachments' => $attachments !== [] ? $attachments : null,
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
            ->first(function (CompanyIntegration $integration) use ($instagramAccountId) {
                $metadata = $integration->metadata ?? [];

                return (string) ($metadata['instagram_user_id'] ?? '') === $instagramAccountId
                    || (string) ($metadata['page_id'] ?? '') === $instagramAccountId;
            });
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

    protected function client(string $accessToken, ?string $authMode = null): PendingRequest
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
        return MetaMessagingSupport::formatGraphError(
            $e->response?->json(),
            $e->getMessage(),
        );
    }

    /**
     * @param  array<string, mixed>  $messageData
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string}>
     */
    public function resolveAttachmentsForMessage(CompanyIntegration $integration, array $messageData): array
    {
        $attachments = $this->normalizeAttachmentsFromApi($messageData);

        if ($attachments !== [] && $this->attachmentsHavePlayableMedia($attachments)) {
            return $attachments;
        }

        $messageId = (string) ($messageData['id'] ?? '');
        if ($messageId === '') {
            return $attachments;
        }

        $fetched = $this->fetchMessageAttachmentsFromApi($integration, $messageId);

        return $fetched !== [] ? $fetched : $attachments;
    }

    /**
     * @param  list<array{type: string, url: string, name: ?string, mime_type: ?string}>  $attachments
     */
    public function attachmentsHavePlayableMedia(array $attachments): bool
    {
        foreach ($attachments as $attachment) {
            if (($attachment['url'] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    protected function messageHasAttachmentHints(array $messageData): bool
    {
        $items = $messageData['attachments']['data'] ?? [];

        return is_array($items) && $items !== [];
    }

    /**
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string}>
     */
    public function fetchMessageAttachmentsFromApi(CompanyIntegration $integration, string $messageId): array
    {
        $authMode = $this->authMode($integration);
        $attempts = $this->attachmentFetchAttempts($integration, $authMode, $messageId);

        foreach ($attempts as $attempt) {
            try {
                $response = $this->client($integration->api_token, $attempt['auth_mode'])->get(
                    $this->url($attempt['path'], $attempt['auth_mode']),
                    $attempt['query'] ?? [],
                );
                $response->throw();

                if (($attempt['parse'] ?? 'items') === 'items') {
                    $items = $response->json('data', []);
                    if (is_array($items) && $items !== []) {
                        $parsed = $this->normalizeAttachmentItems($items);
                        if ($parsed !== []) {
                            return $parsed;
                        }
                    }

                    continue;
                }

                $parsed = $this->normalizeAttachmentsFromApi($response->json());
                if ($parsed !== []) {
                    return $parsed;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    /**
     * @return list<array{auth_mode: string, path: string, query?: array<string, mixed>, parse?: string}>
     */
    protected function attachmentFetchAttempts(
        CompanyIntegration $integration,
        string $authMode,
        string $messageId,
    ): array {
        $attempts = [];
        $pageId = (string) ($integration->metadata['page_id'] ?? '');

        if ($pageId !== '' && $authMode === 'facebook_login') {
            $attempts[] = [
                'auth_mode' => 'facebook_login',
                'path' => $messageId,
                'query' => ['fields' => MetaAttachmentService::ATTACHMENT_FIELDS],
            ];
            $attempts[] = [
                'auth_mode' => 'facebook_login',
                'path' => "{$messageId}/attachments",
                'parse' => 'items',
            ];
        }

        if ($authMode === 'instagram_login') {
            $attempts[] = [
                'auth_mode' => 'instagram_login',
                'path' => $messageId,
                'query' => ['fields' => MetaAttachmentService::ATTACHMENT_FIELDS],
            ];
            $attempts[] = [
                'auth_mode' => 'instagram_login',
                'path' => "{$messageId}/attachments",
                'parse' => 'items',
            ];
        }

        $attempts[] = [
            'auth_mode' => $authMode,
            'path' => $messageId,
            'query' => ['fields' => MetaAttachmentService::ATTACHMENT_FIELDS],
        ];
        $attempts[] = [
            'auth_mode' => $authMode,
            'path' => "{$messageId}/attachments",
            'parse' => 'items',
        ];

        return $attempts;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string}>
     */
    protected function normalizeAttachmentItems(array $items): array
    {
        $attachments = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parsed = $this->parseGraphAttachment($item);
            if ($parsed !== null) {
                $attachments[] = $parsed;
            }
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $messageData
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string}>
     */
    public function normalizeAttachmentsFromApi(array $messageData): array
    {
        $items = $messageData['attachments']['data'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $attachments = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parsed = $this->parseGraphAttachment($item);
            if ($parsed !== null) {
                $attachments[] = $parsed;
            }
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string, storage_path?: string}>
     */
    public function resolveWebhookAttachments(CompanyIntegration $integration, array $message): array
    {
        $attachments = $this->normalizeAttachmentsFromWebhook($message);

        if ($attachments !== [] && $this->attachmentsHavePlayableMedia($attachments)) {
            return $attachments;
        }

        $messageId = (string) ($message['mid'] ?? '');
        if ($messageId === '') {
            return $attachments;
        }

        $fetched = $this->fetchMessageAttachmentsFromApi($integration, $messageId);

        return $fetched !== [] ? $fetched : $attachments;
    }

    /**
     * @return array{type: string, path?: string, url?: string, mime_type: ?string}
     */
    public function resolveAttachmentPlayback(
        CompanyIntegration $integration,
        MessengerMessage $message,
        int $index,
    ): array {
        $attachments = $message->normalizedAttachments();
        $attachment = $attachments[$index] ?? null;

        if (! is_array($attachment)) {
            throw new \RuntimeException(__('Вложение не найдено.'));
        }

        $storagePath = (string) ($attachment['storage_path'] ?? '');
        if ($storagePath !== '') {
            $fullPath = $this->metaAttachments->resolveLocalStoragePath($storagePath);
            if ($fullPath !== null) {
                return [
                    'type' => 'local',
                    'path' => $fullPath,
                    'mime_type' => $this->metaAttachments->mimeTypeForPath(
                        $fullPath,
                        $attachment['mime_type'] ?? null,
                    ),
                ];
            }
        }

        $url = (string) ($attachment['url'] ?? '');
        if ($url === '' && $message->external_id) {
            $fetched = $this->fetchMessageAttachmentsFromApi($integration, (string) $message->external_id);
            $fetchedAttachment = $fetched[$index] ?? $fetched[0] ?? null;
            if (is_array($fetchedAttachment) && ($fetchedAttachment['url'] ?? '') !== '') {
                $url = (string) $fetchedAttachment['url'];
                $attachments[$index] = array_merge($attachment, $fetchedAttachment);
                $message->update(['attachments' => $attachments]);
            }
        }

        if ($url === '') {
            throw new \RuntimeException(__('Не удалось получить ссылку на аудио.'));
        }

        return [
            'type' => 'remote',
            'url' => $url,
            'mime_type' => $attachment['mime_type'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string}>
     */
    public function normalizeAttachmentsFromWebhook(array $message): array
    {
        $items = $message['attachments'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $attachments = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? 'file');
            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $url = (string) (
                $payload['url']
                ?? $payload['story_media_url']
                ?? $payload['src']
                ?? $item['url']
                ?? ''
            );
            $mimeType = isset($payload['mime_type'])
                ? (string) $payload['mime_type']
                : (isset($item['mime_type']) ? (string) $item['mime_type'] : null);

            if ($url === '' && ! $this->looksLikeAudioAttachment($item, $mimeType, null) && ! in_array(strtolower($type), ['audio', 'file'], true)) {
                continue;
            }

            if ($url === '') {
                $attachments[] = [
                    'type' => $this->normalizeAttachmentType($type, $mimeType),
                    'url' => '',
                    'name' => isset($item['title']) ? (string) $item['title'] : null,
                    'mime_type' => $mimeType,
                ];

                continue;
            }

            $attachments[] = [
                'type' => $this->normalizeAttachmentType($type, $mimeType),
                'url' => $url,
                'name' => isset($item['title']) ? (string) $item['title'] : null,
                'mime_type' => $mimeType,
            ];
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{type: string, url: string, name: ?string, mime_type: ?string}|null
     */
    protected function parseGraphAttachment(array $item): ?array
    {
        $mimeType = isset($item['mime_type']) ? (string) $item['mime_type'] : null;
        $name = isset($item['name']) ? (string) $item['name'] : null;

        if (isset($item['image_data']) && is_array($item['image_data'])) {
            $url = (string) ($item['image_data']['url'] ?? $item['image_data']['preview_url'] ?? '');

            if ($url !== '') {
                return [
                    'type' => 'image',
                    'url' => $url,
                    'name' => $name,
                    'mime_type' => $mimeType,
                ];
            }
        }

        if (isset($item['video_data']) && is_array($item['video_data'])) {
            $url = (string) ($item['video_data']['url'] ?? $item['video_data']['preview_url'] ?? '');

            if ($url !== '') {
                return [
                    'type' => 'video',
                    'url' => $url,
                    'name' => $name,
                    'mime_type' => $mimeType,
                ];
            }
        }

        if (isset($item['audio_data']) && is_array($item['audio_data'])) {
            $url = (string) ($item['audio_data']['url'] ?? $item['audio_data']['file_url'] ?? '');

            if ($url !== '') {
                return [
                    'type' => 'audio',
                    'url' => $url,
                    'name' => $name,
                    'mime_type' => $mimeType,
                ];
            }
        }

        $fileUrl = (string) ($item['file_url'] ?? $item['url'] ?? '');

        if ($fileUrl === '') {
            if ($this->looksLikeAudioAttachment($item, $mimeType, $name)) {
                return [
                    'type' => 'audio',
                    'url' => '',
                    'name' => $name,
                    'mime_type' => $mimeType,
                ];
            }

            return null;
        }

        return [
            'type' => $this->normalizeAttachmentType('', $mimeType),
            'url' => $fileUrl,
            'name' => $name,
            'mime_type' => $mimeType,
        ];
    }

    protected function normalizeAttachmentType(string $type, ?string $mimeType): string
    {
        $type = strtolower(trim($type));

        if (in_array($type, ['audio', 'image', 'video', 'file'], true)) {
            return $type;
        }

        if (str_contains($type, 'audio') || str_contains($type, 'voice')) {
            return 'audio';
        }

        $mimeType = strtolower((string) $mimeType);

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'file';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function looksLikeAudioAttachment(array $item, ?string $mimeType, ?string $name): bool
    {
        if (str_starts_with(strtolower((string) $mimeType), 'audio/')) {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            $name,
            (string) ($item['type'] ?? ''),
        ])));

        return str_contains($haystack, 'audio')
            || str_contains($haystack, 'voice')
            || str_contains($haystack, '.m4a')
            || str_contains($haystack, '.aac')
            || str_contains($haystack, '.mp3')
            || str_contains($haystack, '.wav');
    }
}
