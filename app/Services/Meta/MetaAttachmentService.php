<?php

namespace App\Services\Meta;

use App\Models\CompanyIntegration;
use Illuminate\Http\Client\Response;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetaAttachmentService
{
    public const ATTACHMENT_FIELDS = 'attachments{mime_type,name,file_url,image_data,video_data,audio_data}';

    /**
     * @return array{message_id: string, public_url: ?string, prepared_path: string, prepared_name: string, prepared_mime: ?string}
     */
    public function sendAudio(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        ?string $mimeType,
        string $authMode,
        string $recipientId,
        string $messagesEntityId,
        ?string $uploadEntityId = null,
        bool $instagramPlatform = false,
    ): array {
        [$filePath, $originalName, $mimeType] = $this->prepareMetaAudio(
            $filePath,
            $originalName,
            $mimeType,
        );

        if ($authMode === 'instagram_login') {
            $result = $this->sendInstagramLoginAudioByUrl(
                $integration,
                $filePath,
                $originalName,
                $mimeType,
                $recipientId,
                $messagesEntityId,
            );

            return array_merge($result, [
                'prepared_path' => $filePath,
                'prepared_name' => $originalName,
                'prepared_mime' => $mimeType,
            ]);
        }

        $uploadEntityId = $uploadEntityId ?: $messagesEntityId;

        $messageId = $this->sendPageAudioDirectUpload(
            $integration,
            $filePath,
            $originalName,
            $mimeType,
            $recipientId,
            $uploadEntityId,
            $instagramPlatform,
        );

        return [
            'message_id' => $messageId,
            'public_url' => null,
            'prepared_path' => $filePath,
            'prepared_name' => $originalName,
            'prepared_mime' => $mimeType,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: ?string}
     */
    public function prepareMetaAudio(string $filePath, string $originalName, ?string $mimeType): array
    {
        if ($this->canTranscodeWithFfmpeg()) {
            return $this->transcodeToMobileMessengerAudio($filePath);
        }

        if ($this->isMetaSupportedAudio($originalName, $mimeType)) {
            return [$filePath, 'voice.m4a', 'audio/mp4'];
        }

        throw new \RuntimeException(
            __('Meta принимает M4A/MP4 (AAC). Установите ffmpeg на сервере или запишите голос в Safari/Edge.'),
        );
    }

    protected function isMetaSupportedAudio(string $originalName, ?string $mimeType): bool
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $supportedExtensions = ['m4a', 'mp4', 'aac', 'wav'];
        $mimeType = strtolower((string) $mimeType);

        if (in_array($extension, $supportedExtensions, true)) {
            return true;
        }

        return str_contains($mimeType, 'mp4')
            || str_contains($mimeType, 'aac')
            || str_contains($mimeType, 'wav')
            || str_contains($mimeType, 'm4a');
    }

    protected function normalizeAudioFilename(string $originalName, ?string $mimeType): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($extension, ['m4a', 'mp4', 'aac', 'wav', 'mp3'], true)) {
            return $originalName;
        }

        $mimeType = strtolower((string) $mimeType);

        if (str_contains($mimeType, 'wav')) {
            return 'voice.wav';
        }

        return 'voice.m4a';
    }

    protected function canTranscodeWithFfmpeg(): bool
    {
        if (! function_exists('exec')) {
            return false;
        }

        $output = [];
        $code = 1;
        @exec('ffmpeg -version 2>&1', $output, $code);

        return $code === 0;
    }

    /**
     * AAC-LC mono M4A — совместим с Messenger/Facebook на iOS и Android.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    protected function transcodeToMobileMessengerAudio(string $filePath): array
    {
        $outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('voice_', true).'.m4a';

        $command = sprintf(
            'ffmpeg -y -i %s -vn -map_metadata -1 -c:a aac -profile:a aac_low -b:a 128k -ac 1 -ar 44100 -movflags +faststart -f mp4 %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($outputPath),
        );

        $output = [];
        $code = 1;
        exec($command, $output, $code);

        if ($code !== 0 || ! is_file($outputPath) || filesize($outputPath) < 256) {
            throw new \RuntimeException(__('Не удалось конвертировать аудио для Messenger.'));
        }

        return [$outputPath, 'voice.m4a', 'audio/mp4'];
    }

    /**
     * @return array{message_id: string, public_url: string}
     */
    protected function sendInstagramLoginAudioByUrl(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        ?string $mimeType,
        string $recipientId,
        string $igUserId,
    ): array {
        $publicUrl = $this->publishTemporaryAudio($filePath, $originalName);
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);

        $url = 'https://graph.instagram.com/'.MetaMessagingSupport::graphVersion()."/{$igUserId}/messages";

        $response = MetaMessagingSupport::client($token)->post($url, [
            'recipient' => ['id' => $recipientId],
            'messaging_type' => 'RESPONSE',
            'message' => [
                'attachment' => [
                    'type' => 'audio',
                    'payload' => [
                        'url' => $publicUrl,
                        'is_reusable' => false,
                    ],
                ],
            ],
        ]);

        $response->throw();

        return [
            'message_id' => (string) ($response->json('message_id') ?? $response->json('id') ?? ''),
            'public_url' => $publicUrl,
        ];
    }

    protected function sendPageAudioDirectUpload(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        ?string $mimeType,
        string $recipientId,
        string $pageId,
        bool $instagramPlatform,
    ): string {
        $attachmentId = $this->uploadReusableAudioAttachment(
            $integration,
            $filePath,
            $originalName,
            $mimeType,
            $pageId,
            $instagramPlatform,
        );

        return $this->sendMessageWithAudioAttachmentId(
            $integration,
            $recipientId,
            $pageId,
            $attachmentId,
            $instagramPlatform,
        );
    }

    protected function uploadReusableAudioAttachment(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        ?string $mimeType,
        string $pageId,
        bool $instagramPlatform,
    ): string {
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);
        $url = MetaMessagingSupport::graphUrl("{$pageId}/message_attachments", $instagramPlatform ? 'instagram' : null);

        $message = json_encode([
            'attachment' => [
                'type' => 'audio',
                'payload' => [
                    'is_reusable' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Http::acceptJson()
            ->timeout(120)
            ->attach(
                'filedata',
                fopen($filePath, 'r'),
                'voice.m4a',
                ['Content-Type' => 'audio/mp4'],
            )
            ->post($url, [
                'message' => $message,
                'access_token' => $token,
            ]);

        $response->throw();

        $attachmentId = (string) ($response->json('attachment_id') ?? '');

        if ($attachmentId === '') {
            throw new \RuntimeException(__('Meta не вернула attachment_id для голосового сообщения.'));
        }

        return $attachmentId;
    }

    protected function sendMessageWithAudioAttachmentId(
        CompanyIntegration $integration,
        string $recipientId,
        string $pageId,
        string $attachmentId,
        bool $instagramPlatform,
    ): string {
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);
        $url = MetaMessagingSupport::graphUrl("{$pageId}/messages", $instagramPlatform ? 'instagram' : null);

        $response = MetaMessagingSupport::client($token)->post($url, [
            'recipient' => ['id' => $recipientId],
            'messaging_type' => 'RESPONSE',
            'message' => [
                'attachment' => [
                    'type' => 'audio',
                    'payload' => [
                        'attachment_id' => $attachmentId,
                    ],
                ],
            ],
        ]);

        $response->throw();

        return (string) ($response->json('message_id') ?? $response->json('id') ?? '');
    }

    /**
     * @return array{message_id: string, public_url: ?string, prepared_path: string, prepared_name: string, prepared_mime: ?string}
     */
    public function sendImage(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        ?string $mimeType,
        string $authMode,
        string $recipientId,
        string $messagesEntityId,
        ?string $uploadEntityId = null,
        bool $instagramPlatform = false,
    ): array {
        if ($authMode === 'instagram_login') {
            $publicUrl = $this->publishTemporaryImage($filePath, $originalName);
            $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);
            $url = 'https://graph.instagram.com/'.MetaMessagingSupport::graphVersion()."/{$messagesEntityId}/messages";

            $response = MetaMessagingSupport::client($token)->post($url, [
                'recipient' => ['id' => $recipientId],
                'messaging_type' => 'RESPONSE',
                'message' => [
                    'attachment' => [
                        'type' => 'image',
                        'payload' => [
                            'url' => $publicUrl,
                            'is_reusable' => false,
                        ],
                    ],
                ],
            ]);

            $response->throw();

            return [
                'message_id' => (string) ($response->json('message_id') ?? $response->json('id') ?? ''),
                'public_url' => $publicUrl,
                'prepared_path' => $filePath,
                'prepared_name' => $originalName,
                'prepared_mime' => $mimeType,
            ];
        }

        $uploadEntityId = $uploadEntityId ?: $messagesEntityId;

        $attachmentId = $this->uploadReusableImageAttachment(
            $integration,
            $filePath,
            $originalName,
            $mimeType,
            $uploadEntityId,
            $instagramPlatform,
        );

        $messageId = $this->sendMessageWithImageAttachmentId(
            $integration,
            $recipientId,
            $messagesEntityId,
            $attachmentId,
            $instagramPlatform,
        );

        return [
            'message_id' => $messageId,
            'public_url' => null,
            'prepared_path' => $filePath,
            'prepared_name' => $originalName,
            'prepared_mime' => $mimeType,
        ];
    }

    protected function uploadReusableImageAttachment(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        ?string $mimeType,
        string $pageId,
        bool $instagramPlatform,
    ): string {
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);
        $url = MetaMessagingSupport::graphUrl("{$pageId}/message_attachments", $instagramPlatform ? 'instagram' : null);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName) ?: 'image.jpg';
        $contentType = $mimeType ?: 'image/jpeg';

        $message = json_encode([
            'attachment' => [
                'type' => 'image',
                'payload' => [
                    'is_reusable' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Http::acceptJson()
            ->timeout(120)
            ->attach(
                'filedata',
                fopen($filePath, 'r'),
                $safeName,
                ['Content-Type' => $contentType],
            )
            ->post($url, [
                'message' => $message,
                'access_token' => $token,
            ]);

        $response->throw();

        $attachmentId = (string) ($response->json('attachment_id') ?? '');

        if ($attachmentId === '') {
            throw new \RuntimeException(__('Meta не вернула attachment_id для изображения.'));
        }

        return $attachmentId;
    }

    protected function sendMessageWithImageAttachmentId(
        CompanyIntegration $integration,
        string $recipientId,
        string $pageId,
        string $attachmentId,
        bool $instagramPlatform,
    ): string {
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);
        $url = MetaMessagingSupport::graphUrl("{$pageId}/messages", $instagramPlatform ? 'instagram' : null);

        $response = MetaMessagingSupport::client($token)->post($url, [
            'recipient' => ['id' => $recipientId],
            'messaging_type' => 'RESPONSE',
            'message' => [
                'attachment' => [
                    'type' => 'image',
                    'payload' => [
                        'attachment_id' => $attachmentId,
                    ],
                ],
            ],
        ]);

        $response->throw();

        return (string) ($response->json('message_id') ?? $response->json('id') ?? '');
    }

    protected function publishTemporaryImage(string $filePath, string $originalName): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName) ?: 'image.jpg';
        $filename = uniqid('image_', true).'_'.$safeName;

        Storage::disk('public')->putFileAs(
            'messenger/outbound',
            new File($filePath),
            $filename,
        );

        return Storage::disk('public')->url('messenger/outbound/'.$filename);
    }

    public function publishTemporaryAudioForSend(string $filePath, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! in_array($extension, ['ogg', 'opus', 'mp3', 'mpeg', 'm4a', 'mp4', 'webm', 'aac', 'wav'], true)) {
            $extension = 'ogg';
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'voice';
        $filename = uniqid('voice_', true).'_'.$safeName.'.'.$extension;

        Storage::disk('public')->putFileAs(
            'messenger/outbound',
            new File($filePath),
            $filename,
        );

        return Storage::disk('public')->url('messenger/outbound/'.$filename);
    }

    protected function publishTemporaryAudio(string $filePath, string $originalName): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName) ?: 'voice.m4a';
        if (! str_ends_with(strtolower($safeName), '.m4a')) {
            $safeName = pathinfo($safeName, PATHINFO_FILENAME).'.m4a';
        }

        $filename = uniqid('voice_', true).'_'.$safeName;

        Storage::disk('public')->putFileAs(
            'messenger/outbound',
            new File($filePath),
            $filename,
        );

        return Storage::disk('public')->url('messenger/outbound/'.$filename);
    }

    public function storeSentAudioCopy(int $companyId, string $filePath, string $originalName): string
    {
        $filename = uniqid('voice_', true).'_voice.m4a';

        Storage::disk('public')->putFileAs(
            'messenger/sent/'.$companyId,
            new File($filePath),
            $filename,
        );

        return 'public/messenger/sent/'.$companyId.'/'.$filename;
    }

    public function resolveLocalStoragePath(string $storagePath): ?string
    {
        if (str_starts_with($storagePath, 'public/')) {
            $path = storage_path('app/public/'.substr($storagePath, 7));

            return is_file($path) ? $path : null;
        }

        foreach ([
            storage_path('app/'.$storagePath),
            storage_path('app/public/'.$storagePath),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function mimeTypeForPath(string $path, ?string $fallback = null): ?string
    {
        if ($fallback) {
            return $fallback;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'webm' => 'audio/webm',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'mp3' => 'audio/mpeg',
            'm4a', 'mp4' => 'audio/mp4',
            default => 'audio/mp4',
        };
    }

    public function streamRemoteUrl(string $url, array $tokens): StreamedResponse
    {
        $remote = $this->fetchRemoteAttachment($url, $tokens);
        $body = $remote->body();
        $headers = $this->responseHeadersFromRemote($remote);
        $headers['Content-Length'] = (string) strlen($body);

        return response()->stream(function () use ($body): void {
            echo $body;
        }, 200, $headers);
    }

    /**
     * @param  list<string>  $tokens
     */
    public function fetchRemoteAttachment(string $url, array $tokens): Response
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            throw new \RuntimeException(__('Нет токена Meta для загрузки вложения.'));
        }

        foreach ($tokens as $token) {
            foreach ($this->remoteAttachmentRequests($url, $token) as $request) {
                try {
                    $response = $request();
                    if ($this->isValidMediaResponse($response)) {
                        return $response;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        throw new \RuntimeException(__('Не удалось скачать вложение Meta.'));
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $remoteUrls
     */
    public function cacheInboundAttachment(
        int $companyId,
        int $messageId,
        int $index,
        array $remoteUrls,
        array $tokens,
        ?string $mimeType = null,
    ): ?string {
        $storagePath = sprintf('public/messenger/inbound/%d/%d_%d.m4a', $companyId, $messageId, $index);
        $fullPath = $this->resolveLocalStoragePath($storagePath);

        if ($fullPath !== null && filesize($fullPath) > 256 && $this->isBrowserPlayableAudio($fullPath)) {
            return $storagePath;
        }

        $remoteUrls = array_values(array_unique(array_filter($remoteUrls)));

        foreach ($remoteUrls as $remoteUrl) {
            try {
                $response = $this->fetchRemoteAttachment($remoteUrl, $tokens);
            } catch (\Throwable) {
                continue;
            }

            $directory = storage_path('app/public/messenger/inbound/'.$companyId);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $fullPath = storage_path('app/public/messenger/inbound/'.$companyId.'/'.$messageId.'_'.$index.'.m4a');
            file_put_contents($fullPath, $response->body());

            if (! is_file($fullPath) || filesize($fullPath) < 256) {
                @unlink($fullPath);

                continue;
            }

            $this->normalizeCachedInboundAudio($fullPath);

            if (is_file($fullPath) && filesize($fullPath) > 256) {
                return $storagePath;
            }
        }

        return null;
    }

    protected function normalizeCachedInboundAudio(string $fullPath): void
    {
        if ($this->isBrowserPlayableAudio($fullPath)) {
            return;
        }

        if (! $this->canTranscodeWithFfmpeg()) {
            return;
        }

        try {
            [$transcodedPath] = $this->transcodeToMobileMessengerAudio($fullPath);
            if (is_file($transcodedPath) && filesize($transcodedPath) > 256) {
                rename($transcodedPath, $fullPath);
            }
        } catch (\Throwable) {
            // Keep original bytes if transcode fails.
        }
    }

    public function isBrowserPlayableAudio(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $head = (string) file_get_contents($path, false, null, 0, 16);

        if ($head === '') {
            return false;
        }

        if (str_contains($head, 'ftyp')) {
            return true;
        }

        return str_starts_with($head, 'ID3') || str_starts_with($head, 'OggS') || str_starts_with($head, 'RIFF');
    }

    /**
     * @return list<callable(): Response>
     */
    protected function remoteAttachmentRequests(string $url, string $token): array
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        $http = fn () => Http::timeout(60)
            ->withOptions(['allow_redirects' => ['max' => 5]])
            ->withHeaders(['User-Agent' => 'CRM-ErlanPro/1.0']);

        return [
            fn () => $http()->withToken($token)->get($url),
            fn () => $http()->get($url.$separator.'access_token='.urlencode($token)),
            fn () => $http()->get($url),
        ];
    }

    protected function isValidMediaResponse(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) < 128) {
            return false;
        }

        if ($this->isLikelyBinaryMedia($body)) {
            return true;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if ($contentType === '') {
            return false;
        }

        if (str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/json')
            || str_contains($contentType, 'text/plain')) {
            return false;
        }

        return str_starts_with($contentType, 'audio/')
            || str_starts_with($contentType, 'video/')
            || str_starts_with($contentType, 'image/')
            || str_contains($contentType, 'octet-stream')
            || str_contains($contentType, 'mp4');
    }

    protected function isLikelyBinaryMedia(string $body): bool
    {
        $head = substr($body, 0, 16);

        return str_contains($head, 'ftyp')
            || str_starts_with($head, 'ID3')
            || str_starts_with($head, 'OggS')
            || str_starts_with($head, 'RIFF');
    }

    /**
     * @return array<string, string>
     */
    protected function responseHeadersFromRemote(Response $response): array
    {
        $contentType = strtolower((string) ($response->header('Content-Type') ?: 'audio/mp4'));

        if (str_contains($contentType, 'octet-stream') || str_contains($contentType, 'text/')) {
            $contentType = 'audio/mp4';
        }

        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
        ];
    }
}
