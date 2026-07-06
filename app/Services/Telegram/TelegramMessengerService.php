<?php

namespace App\Services\Telegram;

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
use Illuminate\Support\Str;

class TelegramMessengerService
{
    public function __construct(
        private TelegramApiClient $api,
        private MetaAttachmentService $metaAttachments,
    ) {}

    public function integrationForCompany(int $companyId): ?CompanyIntegration
    {
        return CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', IntegrationProvider::Telegram->value)
            ->whereNotNull('api_token')
            ->get()
            ->first(fn (CompanyIntegration $integration) => filled($integration->metadata['bot_id'] ?? null));
    }

    public function findIntegrationByWebhookSecret(string $secret): ?CompanyIntegration
    {
        $secret = trim($secret);
        if ($secret === '') {
            return null;
        }

        return CompanyIntegration::query()
            ->where('provider', IntegrationProvider::Telegram->value)
            ->whereNotNull('api_token')
            ->get()
            ->first(fn (CompanyIntegration $integration) => ($integration->metadata['webhook_secret'] ?? '') === $secret);
    }

    /**
     * @param  array<string, mixed>|null  $existingMetadata
     * @return array{api_token: string, metadata: array<string, mixed>}
     */
    public function connectFromToken(string $apiToken, ?array $existingMetadata = null): array
    {
        $apiToken = TelegramApiClient::normalizeBotToken($apiToken);

        $integration = new CompanyIntegration([
            'provider' => IntegrationProvider::Telegram->value,
            'api_token' => $apiToken,
            'metadata' => [],
        ]);

        $response = $this->api->get($integration, 'getMe');
        $response->throw();

        $result = $response->json('result');
        if (! is_array($result)) {
            throw new \RuntimeException(__('Telegram API не вернул данные бота.'));
        }

        $botId = (string) ($result['id'] ?? '');
        if ($botId === '') {
            throw new \RuntimeException(__('Не удалось определить ID Telegram-бота.'));
        }

        $webhookSecret = is_array($existingMetadata)
            ? ($existingMetadata['webhook_secret'] ?? Str::random(40))
            : Str::random(40);

        return [
            'api_token' => $apiToken,
            'metadata' => [
                'bot_id' => $botId,
                'bot_username' => $result['username'] ?? null,
                'bot_name' => $result['first_name'] ?? null,
                'webhook_secret' => $webhookSecret,
                'connected_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{metadata: array<string, mixed>}
     */
    public function connectIntegration(CompanyIntegration $integration): array
    {
        $metadata = array_merge($integration->metadata ?? [], [
            'webhook_secret' => $integration->metadata['webhook_secret'] ?? Str::random(40),
        ]);

        $integration->update(['metadata' => $metadata]);
        $this->registerWebhook($integration->refresh());

        return ['metadata' => $integration->metadata ?? []];
    }

    public function registerWebhook(CompanyIntegration $integration): void
    {
        $secret = trim((string) ($integration->metadata['webhook_secret'] ?? ''));
        if ($secret === '') {
            throw new \RuntimeException(__('Не удалось сформировать секрет webhook Telegram.'));
        }

        $response = $this->api->postJson($integration, 'setWebhook', [
            'url' => route('webhooks.telegram.handle', ['secret' => $secret]),
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'edited_message'],
            'drop_pending_updates' => false,
        ]);

        if ($response->failed() || $response->json('ok') !== true) {
            throw new \RuntimeException($this->formatApiError($response, __('Не удалось установить webhook Telegram.')));
        }
    }

    public function disconnectIntegration(CompanyIntegration $integration): void
    {
        try {
            $this->api->postJson($integration, 'deleteWebhook', [
                'drop_pending_updates' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram deleteWebhook failed', [
                'company_id' => $integration->company_id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookPayload(array $payload, CompanyIntegration $integration): int
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? null;

        if (! is_array($message)) {
            return 0;
        }

        return $this->processInboundMessage($integration, $message) ? 1 : 0;
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
        return ['synced' => 0, 'errors' => []];
    }

    public function sendMessage(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $text,
    ): MessengerMessage {
        $response = $this->api->postJson($integration, 'sendMessage', [
            'chat_id' => $this->chatIdFromParticipant($conversation->participant_id),
            'text' => $text,
        ]);

        $this->ensureSuccessfulResponse($response, __('Не удалось отправить сообщение в Telegram.'));

        return $this->createOutboundMessage(
            $integration,
            $conversation,
            $this->extractMessageId($response),
            $text,
        );
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

        $response = $this->api->postWithFile(
            $integration,
            'sendVoice',
            [
                'chat_id' => $this->chatIdFromParticipant($conversation->participant_id),
            ],
            'voice',
            $contents,
            $preparedName,
        );

        if ($response->failed()) {
            $response = $this->api->postWithFile(
                $integration,
                'sendAudio',
                [
                    'chat_id' => $this->chatIdFromParticipant($conversation->participant_id),
                ],
                'audio',
                $contents,
                $preparedName,
            );
        }

        $this->ensureSuccessfulResponse($response, __('Не удалось отправить голосовое в Telegram.'));

        $storedPath = $this->metaAttachments->storeSentAudioCopy(
            $integration->company_id,
            $preparedPath,
            $preparedName,
        );

        if ($preparedPath !== $filePath && is_file($preparedPath)) {
            @unlink($preparedPath);
        }

        return $this->createOutboundMessage(
            $integration,
            $conversation,
            $this->extractMessageId($response),
            '',
            [[
                'type' => 'audio',
                'url' => '',
                'name' => $preparedName,
                'mime_type' => $preparedMime,
                'storage_path' => $storedPath,
            ]],
        );
    }

    public function sendImageMessage(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $filePath,
        string $originalName,
        ?string $mimeType = null,
        ?string $caption = null,
    ): MessengerMessage {
        if (! is_file($filePath)) {
            throw new \RuntimeException(__('Не удалось прочитать изображение.'));
        }

        $contents = file_get_contents($filePath);
        if (! is_string($contents) || $contents === '') {
            throw new \RuntimeException(__('Не удалось прочитать изображение.'));
        }

        $mimeType = $this->normalizeImageMime($originalName, $mimeType);
        $fields = [
            'chat_id' => $this->chatIdFromParticipant($conversation->participant_id),
        ];

        if ($caption !== null && trim($caption) !== '') {
            $fields['caption'] = trim($caption);
        }

        $response = $this->api->postWithFile(
            $integration,
            'sendPhoto',
            $fields,
            'photo',
            $contents,
            $originalName,
        );

        $this->ensureSuccessfulResponse($response, __('Не удалось отправить изображение в Telegram.'));

        $storedPath = $this->metaAttachments->storeSentImageCopy(
            $integration->company_id,
            $filePath,
            $originalName,
        );

        return $this->createOutboundMessage(
            $integration,
            $conversation,
            $this->extractMessageId($response),
            $caption ?? '',
            [[
                'type' => 'image',
                'url' => '',
                'name' => $originalName,
                'mime_type' => $mimeType,
                'storage_path' => $storedPath,
            ]],
        );
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function processInboundMessage(CompanyIntegration $integration, array $message): bool
    {
        $chat = $message['chat'] ?? null;
        if (! is_array($chat) || ($chat['type'] ?? '') !== 'private') {
            return false;
        }

        $botId = (string) ($integration->metadata['bot_id'] ?? '');
        $fromId = (string) ($message['from']['id'] ?? '');

        if ($botId !== '' && $fromId === $botId) {
            return false;
        }

        $externalId = (string) ($message['message_id'] ?? '');
        $chatId = (string) ($chat['id'] ?? '');

        if ($externalId === '' || $chatId === '') {
            return false;
        }

        $conversation = MessengerConversation::query()->firstOrCreate(
            [
                'company_id' => $integration->company_id,
                'channel' => IntegrationProvider::Telegram->value,
                'participant_id' => $chatId,
            ],
            [
                'external_id' => $chatId,
                'participant_name' => $this->participantNameFromMessage($message),
                'participant_username' => $message['from']['username'] ?? null,
            ],
        );

        $this->updateConversationMeta($conversation, $message, $chat);

        $existing = MessengerMessage::query()
            ->where('messenger_conversation_id', $conversation->id)
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            return false;
        }

        [$body, $attachments] = $this->resolveBodyAndAttachments($integration, $message);
        $sentAt = $this->resolveSentAt($message);

        MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'external_id' => $externalId,
            'body' => $body,
            'attachments' => $attachments !== [] ? $attachments : null,
            'status' => 'received',
            'sent_at' => $sentAt,
        ]);

        $conversation->update(['last_message_at' => $sentAt]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $chat
     */
    protected function updateConversationMeta(
        MessengerConversation $conversation,
        array $message,
        array $chat,
    ): void {
        $updates = [];

        $name = $this->participantNameFromMessage($message);
        if ($name !== '' && $name !== $conversation->participant_name) {
            $updates['participant_name'] = $name;
        }

        $username = $message['from']['username'] ?? $chat['username'] ?? null;
        if (is_string($username) && $username !== '' && $username !== $conversation->participant_username) {
            $updates['participant_username'] = $username;
        }

        if ($updates !== []) {
            $conversation->update($updates);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{0: string, 1: list<array{type: string, url: string, name: ?string, mime_type: ?string, storage_path?: string}>}
     */
    protected function resolveBodyAndAttachments(CompanyIntegration $integration, array $message): array
    {
        $body = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        $attachments = [];

        if (isset($message['voice']) && is_array($message['voice'])) {
            $attachments[] = $this->downloadAttachment(
                $integration,
                (string) ($message['voice']['file_id'] ?? ''),
                'audio',
                'voice.ogg',
                'audio/ogg',
            );
        } elseif (isset($message['audio']) && is_array($message['audio'])) {
            $attachments[] = $this->downloadAttachment(
                $integration,
                (string) ($message['audio']['file_id'] ?? ''),
                'audio',
                $message['audio']['file_name'] ?? 'audio.mp3',
                $message['audio']['mime_type'] ?? 'audio/mpeg',
            );
        }

        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            $largest = $message['photo'][array_key_last($message['photo'])];
            if (is_array($largest)) {
                $attachments[] = $this->downloadAttachment(
                    $integration,
                    (string) ($largest['file_id'] ?? ''),
                    'image',
                    'photo.jpg',
                    'image/jpeg',
                );
            }
        }

        return [$body, array_values(array_filter($attachments))];
    }

    /**
     * @return array{type: string, url: string, name: ?string, mime_type: ?string, storage_path?: string}
     */
    protected function downloadAttachment(
        CompanyIntegration $integration,
        string $fileId,
        string $type,
        string $defaultName,
        ?string $mimeType,
    ): array {
        if ($fileId === '') {
            return [
                'type' => $type,
                'url' => '',
                'name' => $defaultName,
                'mime_type' => $mimeType,
            ];
        }

        try {
            $fileResponse = $this->api->get($integration, 'getFile', ['file_id' => $fileId]);
            $fileResponse->throw();

            $filePath = (string) ($fileResponse->json('result.file_path') ?? '');
            if ($filePath === '') {
                throw new \RuntimeException('Missing file_path');
            }

            $downloadUrl = $this->fileDownloadUrl($integration, $filePath);
            $contents = file_get_contents($downloadUrl);

            if (! is_string($contents) || $contents === '') {
                throw new \RuntimeException('Empty file body');
            }

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION) ?: pathinfo($defaultName, PATHINFO_EXTENSION));
            if ($extension === '') {
                $extension = $type === 'image' ? 'jpg' : 'ogg';
            }

            $storagePath = $this->storeInboundBinary($integration->company_id, $contents, $extension, $type);

            return [
                'type' => $type,
                'url' => '',
                'name' => basename($filePath) ?: $defaultName,
                'mime_type' => $mimeType,
                'storage_path' => $storagePath,
            ];
        } catch (\Throwable $e) {
            Log::warning('Telegram attachment download failed', [
                'company_id' => $integration->company_id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return [
                'type' => $type,
                'url' => '',
                'name' => $defaultName,
                'mime_type' => $mimeType,
            ];
        }
    }

    protected function storeInboundBinary(int $companyId, string $contents, string $extension, string $prefix): string
    {
        $filename = uniqid($prefix.'_', true).'.'.ltrim($extension, '.');

        Storage::disk('public')->put(
            'messenger/inbound/'.$companyId.'/'.$filename,
            $contents,
        );

        return 'public/messenger/inbound/'.$companyId.'/'.$filename;
    }

    protected function fileDownloadUrl(CompanyIntegration $integration, string $filePath): string
    {
        $token = TelegramApiClient::normalizeBotToken((string) $integration->api_token);

        return 'https://api.telegram.org/file/bot'.$token.'/'.ltrim($filePath, '/');
    }

    /**
     * @param  list<array{type: string, url: string, name: ?string, mime_type: ?string, storage_path?: string}>|null  $attachments
     */
    protected function createOutboundMessage(
        CompanyIntegration $integration,
        MessengerConversation $conversation,
        string $externalId,
        string $body = '',
        ?array $attachments = null,
    ): MessengerMessage {
        return MessengerMessage::query()->create([
            'company_id' => $integration->company_id,
            'messenger_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'external_id' => $externalId !== '' ? $externalId : null,
            'body' => $body,
            'attachments' => $attachments,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    protected function extractMessageId(Response $response): string
    {
        return (string) ($response->json('result.message_id') ?? '');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function participantNameFromMessage(array $message): string
    {
        $from = $message['from'] ?? [];
        if (! is_array($from)) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $from['first_name'] ?? null,
            $from['last_name'] ?? null,
        ])));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function resolveSentAt(array $message): Carbon
    {
        $timestamp = (int) ($message['date'] ?? 0);

        return $timestamp > 0 ? Carbon::createFromTimestamp($timestamp) : now();
    }

    protected function chatIdFromParticipant(string $participantId): string
    {
        return trim($participantId);
    }

    protected function normalizeImageMime(string $originalName, ?string $mimeType): string
    {
        $mimeType = strtolower(trim((string) $mimeType));

        if ($mimeType !== '' && str_starts_with($mimeType, 'image/')) {
            return $mimeType;
        }

        return match (strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function prepareAudioForSend(string $filePath, string $originalName, ?string $mimeType): array
    {
        $mimeType = strtolower(trim((string) $mimeType));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === 'ogg' || $mimeType === 'audio/ogg') {
            return [$filePath, 'voice.ogg', 'audio/ogg'];
        }

        if (! $this->canTranscodeWithFfmpeg()) {
            return [$filePath, $originalName ?: 'voice.webm', $mimeType ?: 'audio/webm'];
        }

        $outputPath = sys_get_temp_dir().'/tg_voice_'.uniqid('', true).'.ogg';
        $command = sprintf(
            'ffmpeg -y -i %s -vn -map_metadata -1 -c:a libopus -application voip -b:a 32k -vbr on -compression_level 10 -ac 1 -ar 48000 %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($outputPath),
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode === 0 && is_file($outputPath) && filesize($outputPath) > 0) {
            return [$outputPath, 'voice.ogg', 'audio/ogg'];
        }

        return [$filePath, $originalName ?: 'voice.webm', $mimeType ?: 'audio/webm'];
    }

    protected function canTranscodeWithFfmpeg(): bool
    {
        $output = [];
        $exitCode = 0;
        exec('ffmpeg -version 2>&1', $output, $exitCode);

        return $exitCode === 0;
    }

    protected function ensureSuccessfulResponse(Response $response, string $fallback): void
    {
        if ($response->failed() || $response->json('ok') !== true) {
            throw new \RuntimeException($this->formatApiError($response, $fallback));
        }
    }

    protected function formatApiError(?Response $response, string $fallback): string
    {
        $description = trim((string) ($response?->json('description') ?? ''));

        return $description !== '' ? $description : $fallback;
    }
}
