<?php

namespace App\Services\Wappi;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Services\Meta\MetaAttachmentService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WappiMessengerService
{
    public function __construct(
        private WappiApiClient $api,
        private MetaAttachmentService $metaAttachments,
    ) {}

    public function integrationForCompany(int $companyId): ?CompanyIntegration
    {
        return CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', IntegrationProvider::Wappi->value)
            ->whereNotNull('api_token')
            ->get()
            ->first(fn (CompanyIntegration $integration) => filled($integration->metadata['profile_id'] ?? null));
    }

    /**
     * @return array{metadata: array<string, mixed>}
     */
    public function connectIntegration(CompanyIntegration $integration): array
    {
        $profileId = $this->profileId($integration);
        if ($profileId === '') {
            throw new \RuntimeException(__('Укажите ID профиля Wappi.'));
        }

        $response = $this->api->get($integration, '/api/sync/get/status');
        $response->throw();

        $metadata = array_merge($integration->metadata ?? [], [
            'profile_id' => $profileId,
            'profile_name' => $this->extractProfileName($response->json()),
            'profile_phone' => $this->extractProfilePhone($response->json()),
            'connected_at' => now()->toIso8601String(),
        ]);

        $integration->update(['metadata' => $metadata]);

        $this->registerWebhook($integration->refresh());

        return ['metadata' => $metadata];
    }

    public function registerWebhook(CompanyIntegration $integration): void
    {
        $webhookUrl = route('webhooks.wappi.handle');

        $setUrl = $this->api->post($integration, '/api/webhook/url/set', [
            'url' => $webhookUrl,
        ]);

        if ($setUrl->failed()) {
            throw new \RuntimeException($this->formatApiError($setUrl, __('Не удалось установить webhook Wappi.')));
        }

        $setTypes = $this->api->postJson($integration, '/api/webhook/types/set', [
            'wh_types' => [
                'incoming_message',
                'outgoing_message_api',
                'outgoing_message_phone',
                'delivery_status',
            ],
        ]);

        if ($setTypes->failed()) {
            Log::warning('Wappi webhook types setup failed', [
                'profile_id' => $this->profileId($integration),
                'body' => $setTypes->json(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookPayload(array $payload): int
    {
        $processed = 0;

        foreach ($this->normalizeWebhookMessages($payload) as $message) {
            $profileId = (string) ($message['profile_id'] ?? '');
            $integration = $this->findIntegrationByProfileId($profileId);

            if (! $integration) {
                continue;
            }

            if ($this->processWebhookMessage($integration, $message)) {
                $processed++;
            } elseif ($this->processDeliveryStatus($integration, $message)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * @return array{synced: int, errors: list<string>}
     */
    public function syncConversations(
        CompanyIntegration $integration,
        int $days = 1,
        ?int $maxConversations = null,
        ?int $hours = null,
        array $priorityExternalIds = [],
    ): array {
        $since = $hours !== null
            ? now()->subHours($hours)
            : now()->subDays($days);

        $limit = $maxConversations ?? 20;
        $errors = [];
        $synced = 0;

        try {
            $response = $this->api->postJson(
                $integration,
                '/api/sync/chats/get',
                [],
                ['limit' => $limit, 'offset' => 0],
            );
            $response->throw();

            $chats = $this->extractList($response->json(), ['dialogs', 'chats', 'data', 'result']);

            if ($priorityExternalIds !== []) {
                usort($chats, function (array $a, array $b) use ($priorityExternalIds) {
                    $aId = $this->chatExternalId($a);
                    $bId = $this->chatExternalId($b);
                    $aPriority = in_array($aId, $priorityExternalIds, true) ? 0 : 1;
                    $bPriority = in_array($bId, $priorityExternalIds, true) ? 0 : 1;

                    return $aPriority <=> $bPriority;
                });
            }

            foreach (array_slice($chats, 0, $limit) as $chat) {
                if (! is_array($chat)) {
                    continue;
                }

                if ($this->shouldSkipChat($chat)) {
                    continue;
                }

                try {
                    if ($this->syncChatMessages($integration, $chat, $since)) {
                        $synced++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        } catch (RequestException $e) {
            $errors[] = $this->formatApiError($e->response, $e->getMessage());
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    public function sendMessage(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $text,
    ): MessengerMessage {
        $recipient = $this->recipientFromParticipantId($conversation->participant_id);

        $response = $this->api->postJson(
            $integration,
            '/api/sync/message/send',
            [
                'recipient' => $recipient,
                'body' => $text,
            ],
        );

        $response->throw();

        $messageId = (string) ($response->json('message_id')
            ?? $response->json('id')
            ?? $response->json('messages.id')
            ?? '');

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
        [$preparedPath, $preparedName, $preparedMime] = $this->prepareAudioForSend(
            $filePath,
            $originalName,
            $mimeType,
        );

        $contents = file_get_contents($preparedPath);

        if (! is_string($contents) || $contents === '') {
            throw new \RuntimeException(__('Не удалось прочитать аудиофайл.'));
        }

        $response = $this->dispatchAudioSend(
            $integration,
            $conversation,
            $contents,
            $preparedPath,
            $preparedName,
            $preparedMime,
        );

        $messageId = $this->extractMessageId($response);

        $storedPath = $this->metaAttachments->storeSentAudioCopy(
            $integration->company_id,
            $preparedPath,
            $preparedName,
        );

        if ($preparedPath !== $filePath && is_file($preparedPath)) {
            @unlink($preparedPath);
        }

        return MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'external_id' => $messageId !== '' ? $messageId : null,
            'body' => '',
            'attachments' => [[
                'type' => 'audio',
                'url' => '',
                'name' => $preparedName,
                'mime_type' => $preparedMime,
                'storage_path' => $storedPath,
            ]],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function dispatchAudioSend(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $binaryContents,
        string $preparedPath,
        string $preparedName,
        string $preparedMime,
    ): Response {
        $errors = [];
        $base64 = base64_encode($binaryContents);

        try {
            $publicUrl = $this->metaAttachments->publishTemporaryAudioForSend($preparedPath, $preparedName);

            Log::info('Wappi audio public URL prepared', [
                'profile_id' => $this->profileId($integration),
                'conversation_id' => $conversation->id,
                'url' => $publicUrl,
            ]);

            return $this->attemptUrlAudioSend(
                $integration,
                $conversation,
                $publicUrl,
                $preparedName,
                $errors,
            );
        } catch (\Throwable $e) {
            $errors[] = 'url-prep: '.$e->getMessage();
        }

        return $this->attemptBase64AudioSend(
            $integration,
            $conversation,
            $base64,
            $preparedName,
            $errors,
        );
    }

    /**
     * @param  list<string>  $errors
     */
    protected function attemptUrlAudioSend(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $url,
        string $fileName,
        array &$errors,
    ): Response {
        $mimeTypes = ['audio/ogg', 'audio/ogg; codecs=opus', 'audio/opus'];
        $payloads = [];

        foreach ($mimeTypes as $mimeType) {
            $payloads[] = ['url' => $url, 'file_name' => $fileName, 'mimetype' => $mimeType];
            $payloads[] = ['url' => $url, 'filename' => $fileName, 'mimetype' => $mimeType];
        }

        foreach ($this->recipientCandidates($conversation) as $recipient) {
            foreach ($payloads as $payload) {
                try {
                    $response = $this->postJsonPayload(
                        $integration,
                        '/api/sync/message/file/url/send',
                        $recipient,
                        $payload,
                    );

                    if ($this->confirmSentAudioMessage($integration, $conversation, $response, 'file/url/send', $recipient, $payload)) {
                        return $response;
                    }

                    $errors[] = '/api/sync/message/file/url/send/'.$recipient.': empty message shell';
                } catch (\Throwable $e) {
                    $errors[] = '/api/sync/message/file/url/send/'.$recipient.': '.$e->getMessage();
                }
            }
        }

        throw new \RuntimeException($errors[0] ?? __('Не удалось отправить голосовое по URL.'));
    }

    /**
     * @param  list<string>  $errors
     */
    protected function attemptBase64AudioSend(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $base64,
        string $fileName,
        array $errors,
    ): Response {
        $mimeTypes = ['audio/ogg', 'audio/ogg; codecs=opus', 'audio/opus'];
        $payloads = [];

        foreach ($mimeTypes as $mimeType) {
            $payloads[] = ['body' => $base64, 'file_name' => $fileName, 'mimetype' => $mimeType];
            $payloads[] = ['body' => $base64, 'filename' => $fileName, 'mimetype' => $mimeType];
            $payloads[] = ['b64' => $base64, 'file_name' => $fileName, 'mimetype' => $mimeType];
            $payloads[] = ['body' => 'data:'.$mimeType.';base64,'.$base64, 'file_name' => $fileName, 'mimetype' => $mimeType];
        }

        $payloads[] = ['body' => $base64];
        $payloads[] = ['b64' => $base64, 'file_name' => $fileName];

        $attempts = [
            ['/api/sync/message/audio/send', false],
            ['/api/sync/message/audio/send', true],
            ['/api/async/message/audio/send', false],
            ['/api/sync/message/document/send', false],
        ];

        foreach ($attempts as [$endpoint, $recipientInQuery]) {
            foreach ($this->recipientCandidates($conversation) as $recipient) {
                foreach ($payloads as $payload) {
                    try {
                        $response = $this->postJsonPayload(
                            $integration,
                            $endpoint,
                            $recipient,
                            $payload,
                            $recipientInQuery,
                        );

                        if ($this->confirmSentAudioMessage($integration, $conversation, $response, $endpoint, $recipient, $payload)) {
                            return $response;
                        }

                        $errors[] = $endpoint.'/'.$recipient.': empty message shell';
                    } catch (\Throwable $e) {
                        $errors[] = $endpoint.'/'.$recipient.': '.$e->getMessage();
                    }
                }
            }
        }

        Log::warning('Wappi audio send failed', [
            'profile_id' => $this->profileId($integration),
            'conversation_id' => $conversation->id,
            'errors' => $errors,
        ]);

        throw new \RuntimeException($errors[0] ?? __('Не удалось отправить голосовое сообщение в WhatsApp.'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postJsonPayload(
        CompanyIntegration $integration,
        string $endpoint,
        string $recipient,
        array $payload,
        bool $recipientInQuery = false,
    ): Response {
        $body = $recipientInQuery ? $payload : ['recipient' => $recipient, ...$payload];
        $query = $recipientInQuery ? ['recipient' => $recipient] : [];

        $response = $this->api->postJson($integration, $endpoint, $body, $query);

        $this->assertSuccessfulSendResponse($response);

        if ($this->extractMessageId($response) === '') {
            throw new \RuntimeException(__('Wappi не вернул ID отправленного сообщения.'));
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function confirmSentAudioMessage(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        Response $response,
        string $endpoint,
        string $recipient,
        array $payload,
    ): bool {
        $messageId = $this->extractMessageId($response);

        if ($this->sentMessageHasAudioPayload($integration, $conversation, $messageId)) {
            Log::info('Wappi audio send accepted', [
                'profile_id' => $this->profileId($integration),
                'conversation_id' => $conversation->id,
                'endpoint' => $endpoint,
                'recipient' => $recipient,
                'message_id' => $messageId,
                'payload_keys' => array_keys($payload),
                'verified' => true,
            ]);

            return true;
        }

        Log::warning('Wappi audio send returned empty shell', [
            'profile_id' => $this->profileId($integration),
            'conversation_id' => $conversation->id,
            'endpoint' => $endpoint,
            'recipient' => $recipient,
            'message_id' => $messageId,
            'payload_keys' => array_keys($payload),
        ]);

        return false;
    }

    protected function sentMessageHasAudioPayload(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $messageId,
    ): bool {
        if ($messageId === '') {
            return false;
        }

        $chatId = $this->chatIdFromConversation($conversation);
        if ($chatId === '') {
            return true;
        }

        usleep(750000);

        $response = $this->api->get($integration, '/api/sync/messages/get', [
            'chat_id' => $chatId,
            'limit' => 15,
            'order' => 'desc',
        ]);

        if ($response->failed()) {
            return false;
        }

        foreach ($this->extractList($response->json(), ['messages', 'data', 'result']) as $message) {
            if (! is_array($message)) {
                continue;
            }

            $id = (string) ($message['id'] ?? $message['message_id'] ?? '');
            if ($id !== $messageId) {
                continue;
            }

            return $this->messageHasAudioPayload($message);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function messageHasAudioPayload(array $message): bool
    {
        $type = strtolower((string) ($message['type'] ?? ''));

        if (in_array($type, ['ptt', 'audio', 'voice'], true)) {
            if (($message['file_link'] ?? $message['file_url'] ?? '') !== '') {
                return true;
            }

            $body = (string) ($message['body'] ?? '');

            return $this->looksLikeBase64($body);
        }

        $mimetype = strtolower((string) ($message['mimetype'] ?? $message['mime_type'] ?? ''));
        if (str_contains($mimetype, 'audio') || str_contains($mimetype, 'ogg') || str_contains($mimetype, 'opus')) {
            return true;
        }

        if (($message['file_link'] ?? $message['file_url'] ?? '') !== '') {
            return true;
        }

        $body = (string) ($message['body'] ?? '');
        if ($this->looksLikeBase64($body)) {
            return true;
        }

        if (in_array($type, ['chat', 'text', ''], true)) {
            return false;
        }

        return $type !== '';
    }

    /**
     * @return list<string>
     */
    protected function recipientCandidates(MessengerConversation $conversation, bool $preferChatId = false): array
    {
        $phone = $this->recipientFromParticipantId($conversation->participant_id);
        $chatId = $this->chatIdFromConversation($conversation);

        $candidates = array_values(array_unique(array_filter([
            $phone,
            $chatId !== $phone ? $chatId : null,
        ])));

        if ($preferChatId && count($candidates) === 2) {
            return array_reverse($candidates);
        }

        return $candidates;
    }

    protected function normalizeWhatsAppAudioMime(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));

        if ($mimeType === '' || str_contains($mimeType, 'ogg') || str_contains($mimeType, 'opus')) {
            return 'audio/ogg';
        }

        return $mimeType;
    }

    protected function chatIdFromConversation(MessengerConversation $conversation): string
    {
        $id = trim((string) ($conversation->external_id ?: $conversation->participant_id));

        if ($id === '') {
            return '';
        }

        if (str_contains($id, '@')) {
            return $id;
        }

        $digits = preg_replace('/\D+/', '', $id);

        return $digits !== '' ? $digits.'@c.us' : $id;
    }

    protected function extractMessageId(Response $response): string
    {
        return (string) ($response->json('message_id')
            ?? $response->json('id')
            ?? $response->json('messages.id')
            ?? $response->json('task_id')
            ?? '');
    }

    protected function assertSuccessfulSendResponse(Response $response): void
    {
        $response->throw();

        $payload = $response->json();
        if (! is_array($payload)) {
            return;
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        if (in_array($status, ['error', 'failed', 'fail', 'undelivered'], true)) {
            throw new \RuntimeException($this->formatApiError($response, __('Wappi не принял голосовое сообщение.')));
        }

        $message = trim((string) ($payload['message'] ?? $payload['detail'] ?? $payload['error'] ?? ''));
        if ($message !== '' && preg_match('/error|fail|invalid|не удалось|ошиб/i', $message)) {
            throw new \RuntimeException($message);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function processDeliveryStatus(CompanyIntegration $integration, array $message): bool
    {
        if ((string) ($message['wh_type'] ?? '') !== 'delivery_status') {
            return false;
        }

        $externalId = (string) ($message['id'] ?? '');
        if ($externalId === '') {
            return false;
        }

        $status = strtolower((string) ($message['status'] ?? ''));
        $mapped = match ($status) {
            'delivered', 'read' => 'delivered',
            'pending' => 'sent',
            'undelivered', 'error', 'failed', 'fail', 'temporary ban' => 'failed',
            default => null,
        };

        if ($mapped === null) {
            return false;
        }

        $updated = MessengerMessage::query()
            ->where('company_id', $integration->company_id)
            ->where('external_id', $externalId)
            ->where('direction', 'outbound')
            ->update(['status' => $mapped]);

        if ($updated > 0) {
            Log::info('Wappi delivery_status received', [
                'profile_id' => $this->profileId($integration),
                'message_id' => $externalId,
                'status' => $status,
                'mapped' => $mapped,
            ]);
        }

        if ($updated > 0 && $mapped === 'failed') {
            Log::warning('Wappi message delivery failed', [
                'profile_id' => $this->profileId($integration),
                'message_id' => $externalId,
                'status' => $status,
            ]);
        }

        return $updated > 0;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function processWebhookMessage(CompanyIntegration $integration, array $message): bool
    {
        $whType = (string) ($message['wh_type'] ?? '');

        if (! in_array($whType, ['incoming_message', 'outgoing_message_api', 'outgoing_message_phone'], true)) {
            return false;
        }

        if ($this->shouldSkipMessageType((string) ($message['type'] ?? ''))) {
            return false;
        }

        $chatId = (string) ($message['chatId'] ?? $message['chat_id'] ?? '');
        if ($chatId === '') {
            return false;
        }

        if ($this->shouldSkipChatType((string) ($message['chat_type'] ?? 'dialog'))) {
            return false;
        }

        $externalId = (string) ($message['id'] ?? '');
        if ($externalId === '') {
            return false;
        }

        $direction = $this->resolveDirection($whType, $message);
        $participantId = $this->participantIdFromMessage($message, $direction);
        if ($participantId === '') {
            $participantId = $chatId;
        }

        $conversation = MessengerConversation::query()->firstOrCreate(
            [
                'company_id' => $integration->company_id,
                'channel' => IntegrationProvider::Wappi->value,
                'participant_id' => $participantId,
            ],
            [
                'external_id' => $chatId,
                'participant_name' => $this->participantNameFromMessage($message),
                'participant_username' => $this->participantPhoneFromMessage($message),
            ],
        );

        $this->updateConversationMeta($conversation, $message, $chatId);

        $existing = MessengerMessage::query()
            ->where('messenger_conversation_id', $conversation->id)
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            return false;
        }

        [$body, $attachments] = $this->resolveBodyAndAttachments($message);
        $attachments = $this->materializeInboundAttachments($integration->company_id, $message, $attachments);
        $sentAt = $this->resolveSentAt($message);

        MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => $direction,
            'external_id' => $externalId,
            'body' => $body,
            'attachments' => $attachments !== [] ? $attachments : null,
            'status' => $direction === 'outbound' ? 'sent' : 'received',
            'sent_at' => $sentAt,
        ]);

        $conversation->update(['last_message_at' => $sentAt]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    protected function syncChatMessages(CompanyIntegration $integration, array $chat, Carbon $since): bool
    {
        $chatId = $this->chatExternalId($chat);
        if ($chatId === '') {
            return false;
        }

        $response = $this->api->get($integration, '/api/sync/messages/get', [
            'chat_id' => $chatId,
            'limit' => 50,
            'order' => 'desc',
        ]);
        $response->throw();

        $messages = $this->extractList($response->json(), ['messages', 'data', 'result']);
        $syncedAny = false;

        $conversation = MessengerConversation::query()->firstOrCreate(
            [
                'company_id' => $integration->company_id,
                'channel' => IntegrationProvider::Wappi->value,
                'participant_id' => $chatId,
            ],
            [
                'external_id' => $chatId,
                'participant_name' => $this->chatDisplayName($chat),
                'participant_username' => $this->chatPhone($chat),
            ],
        );

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $sentAt = $this->resolveSentAt($message);
            if ($sentAt->lt($since)) {
                continue;
            }

            if ($this->shouldSkipMessageType((string) ($message['type'] ?? ''))) {
                continue;
            }

            $externalId = (string) ($message['id'] ?? $message['message_id'] ?? '');
            if ($externalId === '') {
                continue;
            }

            if (MessengerMessage::query()
                ->where('messenger_conversation_id', $conversation->id)
                ->where('external_id', $externalId)
                ->exists()) {
                continue;
            }

            $direction = ($message['is_me'] ?? false) ? 'outbound' : 'inbound';
            [$body, $attachments] = $this->resolveBodyAndAttachments($message);
            $attachments = $this->materializeInboundAttachments($integration->company_id, $message, $attachments);

            MessengerMessage::query()->create([
                'company_id' => $integration->company_id,
                'messenger_conversation_id' => $conversation->id,
                'direction' => $direction,
                'external_id' => $externalId,
                'body' => $body,
                'attachments' => $attachments !== [] ? $attachments : null,
                'status' => $direction === 'outbound' ? 'sent' : 'received',
                'sent_at' => $sentAt,
            ]);

            $syncedAny = true;

            if (! $conversation->last_message_at || $sentAt->gt($conversation->last_message_at)) {
                $conversation->update(['last_message_at' => $sentAt]);
            }
        }

        return $syncedAny;
    }

    protected function findIntegrationByProfileId(string $profileId): ?CompanyIntegration
    {
        if ($profileId === '') {
            return null;
        }

        return CompanyIntegration::query()
            ->where('provider', IntegrationProvider::Wappi->value)
            ->whereNotNull('api_token')
            ->get()
            ->first(fn (CompanyIntegration $integration) => $this->profileId($integration) === $profileId);
    }

    protected function profileId(CompanyIntegration $integration): string
    {
        return trim((string) ($integration->metadata['profile_id'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    protected function normalizeWebhookMessages(array $payload): array
    {
        $messages = $payload['messages'] ?? [];

        if (! is_array($messages)) {
            return [];
        }

        if ($this->isAssocArray($messages)) {
            return [$messages];
        }

        return array_values(array_filter($messages, fn ($item) => is_array($item)));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function resolveDirection(string $whType, array $message): string
    {
        if (in_array($whType, ['outgoing_message_api', 'outgoing_message_phone'], true)) {
            return 'outbound';
        }

        if (($message['is_me'] ?? false) === true) {
            return 'outbound';
        }

        return 'inbound';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function participantIdFromMessage(array $message, string $direction): string
    {
        $chatId = (string) ($message['chatId'] ?? $message['chat_id'] ?? '');
        if ($chatId !== '') {
            return $chatId;
        }

        if ($direction === 'inbound') {
            return (string) ($message['from'] ?? '');
        }

        return (string) ($message['to'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function participantNameFromMessage(array $message): ?string
    {
        $name = trim((string) ($message['contact_name'] ?? $message['senderName'] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function participantPhoneFromMessage(array $message): ?string
    {
        $phone = trim((string) ($message['phone'] ?? $message['contact_phone'] ?? ''));

        if ($phone !== '') {
            return $phone;
        }

        $from = (string) ($message['from'] ?? $message['chatId'] ?? '');

        return $this->phoneFromJid($from);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function updateConversationMeta(MessengerConversation $conversation, array $message, string $chatId): void
    {
        $updates = [];

        $name = $this->participantNameFromMessage($message);
        if ($name && $conversation->participant_name !== $name) {
            $updates['participant_name'] = $name;
        }

        $phone = $this->participantPhoneFromMessage($message);
        if ($phone && $conversation->participant_username !== $phone) {
            $updates['participant_username'] = $phone;
        }

        if (! $conversation->external_id) {
            $updates['external_id'] = $chatId;
        }

        if ($updates !== []) {
            $conversation->update($updates);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{0: string, 1: list<array{type: string, url: string, name: ?string, mime_type: ?string}>}
     */
    protected function resolveBodyAndAttachments(array $message): array
    {
        $type = strtolower((string) ($message['type'] ?? 'chat'));
        $caption = trim((string) ($message['caption'] ?? ''));
        $body = trim((string) ($message['body'] ?? ''));

        $textTypes = ['chat', 'text', 'buttons_response', 'list_response'];

        if (in_array($type, $textTypes, true)) {
            return [$body, []];
        }

        $attachmentType = match ($type) {
            'image' => 'image',
            'video' => 'video',
            'audio', 'ptt', 'voice' => 'audio',
            'document', 'file' => 'file',
            default => 'file',
        };

        $url = (string) ($message['file_link'] ?? $message['file_url'] ?? '');
        if ($url === '' && ! $this->looksLikeBase64($body)) {
            $url = filter_var($body, FILTER_VALIDATE_URL) ? $body : '';
        }

        $attachments = [[
            'type' => $attachmentType,
            'url' => $url,
            'name' => $message['file_name'] ?? null,
            'mime_type' => $message['mimetype'] ?? $message['mime_type'] ?? null,
        ]];

        return [$caption, $attachments];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function resolveSentAt(array $message): Carbon
    {
        if (isset($message['time']) && is_numeric($message['time'])) {
            return Carbon::createFromTimestamp((int) $message['time']);
        }

        $timestamp = (string) ($message['timestamp'] ?? '');
        if ($timestamp !== '') {
            try {
                return Carbon::parse($timestamp);
            } catch (\Throwable) {
                // fall through
            }
        }

        return now();
    }

    protected function shouldSkipMessageType(string $type): bool
    {
        return in_array(strtolower($type), [
            'reaction',
            'poll',
            'poll_vote',
            'incoming_call',
            'missed_call',
            'call_terminate',
            'call_accept',
            'buttons',
            'list',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    protected function shouldSkipChat(array $chat): bool
    {
        $type = strtolower((string) ($chat['chat_type'] ?? $chat['type'] ?? 'dialog'));

        return $this->shouldSkipChatType($type);
    }

    protected function shouldSkipChatType(string $type): bool
    {
        return in_array(strtolower($type), ['group', 'community', 'broadcast'], true);
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    protected function chatExternalId(array $chat): string
    {
        return (string) ($chat['id'] ?? $chat['chat_id'] ?? $chat['chatId'] ?? $chat['jid'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    protected function chatDisplayName(array $chat): ?string
    {
        $name = trim((string) ($chat['name'] ?? $chat['contact_name'] ?? $chat['title'] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    protected function chatPhone(array $chat): ?string
    {
        $phone = trim((string) ($chat['phone'] ?? $chat['contact_phone'] ?? ''));

        if ($phone !== '') {
            return $phone;
        }

        return $this->phoneFromJid($this->chatExternalId($chat));
    }

    protected function recipientFromParticipantId(string $participantId): string
    {
        if (str_contains($participantId, '@')) {
            return explode('@', $participantId)[0];
        }

        return preg_replace('/\D+/', '', $participantId) ?: $participantId;
    }

    protected function phoneFromJid(string $jid): ?string
    {
        if ($jid === '' || ! str_contains($jid, '@')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', explode('@', $jid)[0]);

        return $digits !== '' ? $digits : null;
    }

    protected function looksLikeBase64(string $value): bool
    {
        if (strlen($value) < 40) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', substr($value, 0, 200));
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function prepareAudioForSend(string $filePath, string $originalName, ?string $mimeType): array
    {
        if ($this->canTranscodeWithFfmpeg()) {
            return $this->transcodeToWhatsAppAudio($filePath);
        }

        $mimeType = strtolower((string) $mimeType);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($extension, ['ogg', 'opus'], true)
            || str_contains($mimeType, 'ogg')
            || str_contains($mimeType, 'opus')) {
            return [$filePath, 'voice.ogg', 'audio/ogg'];
        }

        throw new \RuntimeException(
            __('Для голосовых WhatsApp нужен ffmpeg на сервере (конвертация в OGG/Opus).'),
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function transcodeToWhatsAppAudio(string $filePath): array
    {
        $outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('wappi_voice_', true).'.ogg';

        $command = sprintf(
            'ffmpeg -y -i %s -vn -map_metadata -1 -c:a libopus -application voip -b:a 32k -vbr on -compression_level 10 -ac 1 -ar 48000 %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($outputPath),
        );

        $code = $this->runShellCommand($command);

        if ($code === 0 && is_file($outputPath) && filesize($outputPath) >= 256 && $this->isOggOpusFile($outputPath)) {
            return [$outputPath, 'voice.ogg', 'audio/ogg'];
        }

        if (is_file($outputPath)) {
            @unlink($outputPath);
        }

        throw new \RuntimeException(__('Не удалось конвертировать аудио для WhatsApp. Установите ffmpeg с libopus.'));
    }

    protected function runShellCommand(string $command): int
    {
        if (function_exists('exec')) {
            $output = [];
            $code = 1;
            @exec($command, $output, $code);

            return (int) $code;
        }

        if (! function_exists('proc_open')) {
            return 1;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            return 1;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return (int) proc_close($process);
    }

    protected function isOggOpusFile(string $filePath): bool
    {
        $handle = @fopen($filePath, 'rb');

        if (! is_resource($handle)) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return $header === 'OggS';
    }

    protected function canTranscodeWithFfmpeg(): bool
    {
        if (! function_exists('exec') && ! function_exists('proc_open')) {
            return false;
        }

        return $this->runShellCommand('ffmpeg -version 2>&1') === 0;
    }

    protected function normalizeAudioFilename(string $originalName, ?string $mimeType): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($extension, ['ogg', 'opus', 'mp3', 'mpeg', 'm4a', 'mp4', 'webm', 'aac', 'wav'], true)) {
            return $originalName;
        }

        $mimeType = strtolower((string) $mimeType);

        if (str_contains($mimeType, 'ogg') || str_contains($mimeType, 'opus')) {
            return 'voice.ogg';
        }

        if (str_contains($mimeType, 'mpeg') || str_contains($mimeType, 'mp3')) {
            return 'voice.mp3';
        }

        if (str_contains($mimeType, 'webm')) {
            return 'voice.webm';
        }

        return 'voice.m4a';
    }

    /**
     * @param  list<array{type: string, url: string, name: ?string, mime_type: ?string, storage_path?: string}>  $attachments
     * @param  array<string, mixed>  $message
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string, storage_path?: string}>
     */
    protected function materializeInboundAttachments(int $companyId, array $message, array $attachments): array
    {
        if ($attachments === []) {
            return $attachments;
        }

        $first = $attachments[0];
        $type = (string) ($first['type'] ?? '');

        if ($type !== 'audio') {
            return $attachments;
        }

        if (($first['url'] ?? '') !== '' || ($first['storage_path'] ?? '') !== '') {
            return $attachments;
        }

        $rawBody = (string) ($message['body'] ?? '');
        if (! $this->looksLikeBase64($rawBody)) {
            return $attachments;
        }

        $storagePath = $this->storeInboundAudio($companyId, $rawBody, $first['mime_type'] ?? null);
        if ($storagePath === null) {
            return $attachments;
        }

        $attachments[0]['storage_path'] = $storagePath;
        $attachments[0]['url'] = '';

        return $attachments;
    }

    protected function storeInboundAudio(int $companyId, string $base64Body, ?string $mimeType): ?string
    {
        $binary = base64_decode(preg_replace('/\s+/', '', $base64Body) ?: '', true);

        if ($binary === false || strlen($binary) < 128) {
            return null;
        }

        $extension = $this->extensionForMime($mimeType);
        $filename = uniqid('wappi_voice_', true).'.'.$extension;
        $relativePath = 'messenger/inbound/'.$companyId.'/'.$filename;

        Storage::disk('public')->put($relativePath, $binary);

        return 'public/'.$relativePath;
    }

    protected function extensionForMime(?string $mimeType): string
    {
        $mimeType = strtolower((string) $mimeType);

        if (str_contains($mimeType, 'ogg') || str_contains($mimeType, 'opus')) {
            return 'ogg';
        }

        if (str_contains($mimeType, 'mpeg') || str_contains($mimeType, 'mp3')) {
            return 'mp3';
        }

        if (str_contains($mimeType, 'webm')) {
            return 'webm';
        }

        if (str_contains($mimeType, 'wav')) {
            return 'wav';
        }

        return 'm4a';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    protected function extractList(?array $payload, array $keys): array
    {
        if (! is_array($payload)) {
            return [];
        }

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_array($value)) {
                return array_values(array_filter($value, fn ($item) => is_array($item)));
            }
        }

        if ($this->isListArray($payload)) {
            return array_values(array_filter($payload, fn ($item) => is_array($item)));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function extractProfileName(?array $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $name = trim((string) ($payload['name'] ?? $payload['profile_name'] ?? $payload['phone'] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function extractProfilePhone(?array $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $phone = trim((string) ($payload['phone'] ?? $payload['profile_phone'] ?? ''));

        return $phone !== '' ? $phone : null;
    }

    protected function formatApiError(?Response $response, string $fallback): string
    {
        if (! $response) {
            return $fallback;
        }

        $message = trim((string) ($response->json('message') ?? $response->json('detail') ?? $response->json('error') ?? ''));

        if ($message !== '') {
            return $message;
        }

        return $fallback.' (HTTP '.$response->status().')';
    }

    /**
     * @param  array<mixed>  $array
     */
    protected function isAssocArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param  array<mixed>  $array
     */
    protected function isListArray(array $array): bool
    {
        return ! $this->isAssocArray($array);
    }
}
