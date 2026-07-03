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

    /**
     * @return array{id: string, username: ?string, name: ?string}
     */
    public function fetchProfile(string $accessToken): array
    {
        $response = $this->client($accessToken)->get($this->url('me'), [
            'fields' => 'id,username,name',
        ]);

        $response->throw();

        $data = $response->json();

        return [
            'id' => (string) ($data['id'] ?? ''),
            'username' => $data['username'] ?? null,
            'name' => $data['name'] ?? null,
        ];
    }

    public function refreshIntegrationMetadata(CompanyIntegration $integration): CompanyIntegration
    {
        $profile = $this->fetchProfile($integration->api_token);

        $metadata = $integration->metadata ?? [];
        $metadata['instagram_user_id'] = $profile['id'];
        $metadata['username'] = $profile['username'];
        $metadata['name'] = $profile['name'];

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
            $response = $this->client($integration->api_token)->get(
                $this->url("{$igUserId}/conversations"),
                [
                    'platform' => 'instagram',
                    'fields' => 'id,updated_time,participants',
                ],
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

        $messagesResponse = $this->client($integration->api_token)->get(
            $this->url("{$externalId}/messages"),
            [
                'fields' => 'id,created_time,from,to,message',
            ],
        );

        $messagesResponse->throw();

        foreach ($messagesResponse->json('data', []) as $messageData) {
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
        $direction = $fromId === $igUserId ? 'outbound' : 'inbound';
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

        $response = $this->client($integration->api_token)->post(
            $this->url("{$igUserId}/messages"),
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

    protected function client(string $accessToken): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->withToken($accessToken);
    }

    protected function url(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->graphVersion().'/'.ltrim($path, '/');
    }

    protected function formatApiError(RequestException $e): string
    {
        $body = $e->response?->json();
        $message = $body['error']['message'] ?? $e->getMessage();

        return (string) $message;
    }
}
