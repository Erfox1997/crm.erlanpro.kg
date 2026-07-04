<?php

namespace App\Services\Facebook;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Services\Instagram\InstagramMessengerService;
use App\Services\Meta\MetaAttachmentService;
use App\Services\Meta\MetaMessagingSupport;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;

class FacebookMessengerService
{
    public function __construct(
        private InstagramMessengerService $attachments,
        private MetaAttachmentService $metaAttachments,
    ) {}

    public function integrationForCompany(int $companyId): ?CompanyIntegration
    {
        return CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', IntegrationProvider::Facebook->value)
            ->whereNotNull('api_token')
            ->first();
    }

    /**
     * @return array{api_token: string, metadata: array<string, mixed>}
     */
    public function connectAccountFromManualToken(string $accessToken): array
    {
        $accessToken = MetaMessagingSupport::normalizeAccessToken($accessToken);

        if (! str_starts_with($accessToken, 'EAA')) {
            throw new \RuntimeException(__('Для Facebook Messenger нужен Page Access Token (EAA...).'));
        }

        $response = MetaMessagingSupport::client($accessToken)->get(
            MetaMessagingSupport::graphUrl('me'),
            ['fields' => 'id,name'],
        );
        $response->throw();

        return [
            'api_token' => $accessToken,
            'metadata' => [
                'page_id' => (string) ($response->json('id') ?? ''),
                'page_name' => $response->json('name'),
                'auth_mode' => 'facebook_login',
                'connected_via' => 'manual',
            ],
        ];
    }

    public function refreshIntegrationMetadata(CompanyIntegration $integration): CompanyIntegration
    {
        $response = MetaMessagingSupport::client((string) $integration->api_token)->get(
            MetaMessagingSupport::graphUrl('me'),
            ['fields' => 'id,name'],
        );
        $response->throw();

        $metadata = array_merge($integration->metadata ?? [], [
            'page_id' => (string) ($response->json('id') ?? ''),
            'page_name' => $response->json('name'),
        ]);

        $integration->update(['metadata' => $metadata]);

        return $integration->refresh();
    }

    /**
     * @return array{synced: int, errors: list<string>}
     */
    public function syncConversations(CompanyIntegration $integration): array
    {
        if (! ($integration->metadata['page_id'] ?? null)) {
            $integration = $this->refreshIntegrationMetadata($integration);
        }

        $pageId = (string) ($integration->metadata['page_id'] ?? '');
        if ($pageId === '') {
            return ['synced' => 0, 'errors' => [__('Не задан page_id Facebook.')]];
        }

        $errors = [];
        $synced = 0;

        try {
            $response = MetaMessagingSupport::client((string) $integration->api_token)->get(
                MetaMessagingSupport::graphUrl("{$pageId}/conversations"),
                [
                    'platform' => 'messenger',
                    'fields' => 'id,updated_time,participants',
                ],
            );

            $response->throw();

            foreach ($response->json('data', []) as $item) {
                try {
                    $this->syncConversation($integration, $pageId, $item);
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
        string $pageId,
        array $conversationData,
    ): void {
        $participant = $this->resolveParticipant($conversationData, $pageId);
        if (! $participant) {
            return;
        }

        $conversation = MessengerConversation::query()->updateOrCreate(
            [
                'company_id' => $integration->company_id,
                'channel' => IntegrationProvider::Facebook->value,
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

        $messagesResponse = MetaMessagingSupport::client((string) $integration->api_token)->get(
            MetaMessagingSupport::graphUrl($externalId),
            ['fields' => 'messages{message,from,created_time,id,'.MetaAttachmentService::ATTACHMENT_FIELDS.'}'],
        );

        $messagesResponse->throw();

        foreach ($messagesResponse->json('messages.data', []) as $messageData) {
            $this->storeMessageFromApi($integration, $conversation, $pageId, $messageData);
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
    protected function resolveParticipant(array $conversationData, string $pageId): ?array
    {
        foreach ($conversationData['participants']['data'] ?? [] as $participant) {
            $id = (string) ($participant['id'] ?? '');
            if ($id !== '' && $id !== $pageId) {
                return [
                    'id' => $id,
                    'name' => $participant['name'] ?? null,
                    'username' => $participant['username'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    protected function storeMessageFromApi(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $pageId,
        array $messageData,
    ): ?MessengerMessage {
        $externalId = isset($messageData['id']) ? (string) $messageData['id'] : null;
        $attachments = $this->attachments->resolveAttachmentsForMessage($integration, $messageData);
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

                if (! $this->attachments->attachmentsHavePlayableMedia($existing->normalizedAttachments())) {
                    $resolved = $this->attachments->attachmentsHavePlayableMedia($attachments)
                        ? $attachments
                        : ($externalId ? $this->attachments->fetchMessageAttachmentsFromApi($integration, $externalId) : []);

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
        $direction = $fromId === $pageId ? 'outbound' : 'inbound';
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
        $pageId = (string) ($integration->metadata['page_id'] ?? '');
        if ($pageId === '') {
            $integration = $this->refreshIntegrationMetadata($integration);
            $pageId = (string) ($integration->metadata['page_id'] ?? '');
        }

        if ($pageId === '') {
            throw new \RuntimeException(__('Facebook не настроен: нет page_id.'));
        }

        $response = MetaMessagingSupport::client((string) $integration->api_token)->post(
            MetaMessagingSupport::graphUrl("{$pageId}/messages"),
            [
                'recipient' => ['id' => $conversation->participant_id],
                'message' => ['text' => $text],
                'messaging_type' => 'RESPONSE',
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

    public function sendAudioMessage(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $filePath,
        string $originalName,
        ?string $mimeType = null,
    ): MessengerMessage {
        $pageId = (string) ($integration->metadata['page_id'] ?? '');
        if ($pageId === '') {
            $integration = $this->refreshIntegrationMetadata($integration);
            $pageId = (string) ($integration->metadata['page_id'] ?? '');
        }

        if ($pageId === '') {
            throw new \RuntimeException(__('Facebook не настроен: нет page_id.'));
        }

        $attachmentId = $this->metaAttachments->sendAudio(
            $integration,
            $filePath,
            $originalName,
            $mimeType,
            'facebook_login',
            $conversation->participant_id,
            $pageId,
            $pageId,
            false,
        );

        $storedPath = $this->attachments->storeLocalAudioCopy($integration->company_id, $filePath, $originalName);

        return MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'external_id' => $attachmentId['message_id'] !== '' ? $attachmentId['message_id'] : null,
            'body' => '',
            'attachments' => [[
                'type' => 'audio',
                'url' => '',
                'name' => $originalName,
                'mime_type' => $mimeType,
                'storage_path' => $storedPath,
            ]],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookPayload(array $payload): int
    {
        if (($payload['object'] ?? '') !== 'page') {
            return 0;
        }

        $processed = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            $pageId = (string) ($entry['id'] ?? '');
            $integration = $this->findIntegrationByPageId($pageId);
            if (! $integration) {
                continue;
            }

            foreach ($entry['messaging'] ?? [] as $event) {
                if ($this->processMessagingEvent($integration, $pageId, $event)) {
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
        string $pageId,
        array $event,
    ): bool {
        $message = $event['message'] ?? null;
        if (! is_array($message) || ! isset($message['mid'])) {
            return false;
        }

        $senderId = (string) ($event['sender']['id'] ?? '');
        $recipientId = (string) ($event['recipient']['id'] ?? '');
        $customerId = $senderId === $pageId ? $recipientId : $senderId;

        if ($customerId === '' || $customerId === $pageId) {
            return false;
        }

        $conversation = MessengerConversation::query()->firstOrCreate(
            [
                'company_id' => $integration->company_id,
                'channel' => IntegrationProvider::Facebook->value,
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

        $direction = $senderId === $pageId ? 'outbound' : 'inbound';
        $sentAt = isset($event['timestamp'])
            ? Carbon::createFromTimestampMs((int) $event['timestamp'])
            : now();

        $attachments = $this->attachments->resolveWebhookAttachments($integration, $message);

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

    protected function findIntegrationByPageId(string $pageId): ?CompanyIntegration
    {
        if ($pageId === '') {
            return null;
        }

        return CompanyIntegration::query()
            ->where('provider', IntegrationProvider::Facebook->value)
            ->whereNotNull('api_token')
            ->get()
            ->first(fn (CompanyIntegration $integration) => (string) ($integration->metadata['page_id'] ?? '') === $pageId);
    }

    protected function formatApiError(RequestException $e): string
    {
        $body = $e->response?->json();
        $message = $body['error']['message'] ?? $e->getMessage();

        return (string) $message;
    }
}
