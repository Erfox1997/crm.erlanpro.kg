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
     * @return array{message_id: string, public_url: ?string}
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
        if ($instagramPlatform || $authMode === 'instagram_login') {
            [$filePath, $originalName, $mimeType] = $this->prepareInstagramAudio(
                $filePath,
                $originalName,
                $mimeType,
            );
        }

        if ($authMode === 'instagram_login') {
            return $this->sendInstagramLoginAudioByUrl(
                $integration,
                $filePath,
                $originalName,
                $mimeType,
                $recipientId,
                $messagesEntityId,
            );
        }

        $uploadEntityId = $uploadEntityId ?: $messagesEntityId;

        $attachmentId = $this->uploadAudioToPage(
            $integration,
            $filePath,
            $originalName,
            $uploadEntityId,
            $instagramPlatform,
        );

        $messageId = $this->sendPageAudioByAttachmentId(
            $integration,
            $recipientId,
            $attachmentId,
            $messagesEntityId,
            $instagramPlatform,
        );

        return [
            'message_id' => $messageId,
            'public_url' => null,
        ];
    }

    /**
     * @return array{message_id: string, public_url: string}
     */
    /**
     * @return array{0: string, 1: string, 2: ?string}
     */
    public function prepareInstagramAudio(string $filePath, string $originalName, ?string $mimeType): array
    {
        if ($this->isInstagramSupportedAudio($originalName, $mimeType)) {
            return [$filePath, $originalName, $mimeType];
        }

        if ($this->canTranscodeWithFfmpeg()) {
            return $this->transcodeToM4a($filePath);
        }

        throw new \RuntimeException(
            __('Instagram принимает только M4A, MP4, WAV или AAC. Используйте Safari/Edge или установите ffmpeg на сервере.'),
        );
    }

    protected function isInstagramSupportedAudio(string $originalName, ?string $mimeType): bool
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
     * @return array{0: string, 1: string, 2: string}
     */
    protected function transcodeToM4a(string $filePath): array
    {
        $outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('voice_', true).'.m4a';
        $command = sprintf(
            'ffmpeg -y -i %s -c:a aac -b:a 128k %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($outputPath),
        );

        $output = [];
        $code = 1;
        exec($command, $output, $code);

        if ($code !== 0 || ! is_file($outputPath)) {
            throw new \RuntimeException(__('Не удалось конвертировать аудио для Instagram.'));
        }

        return [$outputPath, 'voice.m4a', 'audio/mp4'];
    }

    protected function sendInstagramLoginAudioByUrl(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        ?string $mimeType,
        string $recipientId,
        string $igUserId,
    ): array {
        $publicUrl = $this->publishTemporaryAudio($filePath, $originalName, $mimeType);
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);

        $url = 'https://graph.instagram.com/'.MetaMessagingSupport::graphVersion()."/{$igUserId}/messages";

        $response = MetaMessagingSupport::client($token)->post($url, [
            'recipient' => ['id' => $recipientId],
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

    protected function publishTemporaryAudio(string $filePath, string $originalName, ?string $mimeType = null): string
    {
        $filename = uniqid('voice_', true).'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);

        Storage::disk('public')->putFileAs(
            'messenger/outbound',
            new File($filePath),
            $filename,
        );

        return Storage::disk('public')->url('messenger/outbound/'.$filename);
    }

    public function storeSentAudioCopy(int $companyId, string $filePath, string $originalName): string
    {
        $filename = uniqid('voice_', true).'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);

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
            'm4a', 'mp4' => 'audio/mp4',
            default => 'audio/mp4',
        };
    }

    protected function uploadAudioToPage(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        string $pageId,
        bool $instagramPlatform,
    ): string {
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);
        $message = json_encode([
            'attachment' => [
                'type' => 'audio',
                'payload' => [
                    'is_reusable' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $query = $instagramPlatform ? '?platform=instagram' : '';

        $response = Http::acceptJson()
            ->timeout(120)
            ->attach('filedata', fopen($filePath, 'r'), $originalName)
            ->post(
                MetaMessagingSupport::graphUrl("{$pageId}/message_attachments").$query,
                [
                    'message' => $message,
                    'access_token' => $token,
                ],
            );

        $response->throw();

        $attachmentId = (string) ($response->json('attachment_id') ?? '');
        if ($attachmentId === '') {
            throw new \RuntimeException(__('Meta не вернула attachment_id для аудио.'));
        }

        return $attachmentId;
    }

    protected function sendPageAudioByAttachmentId(
        CompanyIntegration $integration,
        string $recipientId,
        string $attachmentId,
        string $pageId,
        bool $instagramPlatform,
    ): string {
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);

        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => [
                'attachment' => [
                    'type' => 'audio',
                    'payload' => [
                        'attachment_id' => $attachmentId,
                    ],
                ],
            ],
            'messaging_type' => 'RESPONSE',
        ];

        $query = $instagramPlatform ? '?platform=instagram' : '';

        $response = MetaMessagingSupport::client($token)->post(
            MetaMessagingSupport::graphUrl("{$pageId}/messages").$query,
            $payload,
        );

        $response->throw();

        return (string) ($response->json('message_id') ?? $response->json('id') ?? '');
    }

    public function streamRemoteUrl(CompanyIntegration $integration, string $url): StreamedResponse
    {
        $token = MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token);
        $remote = $this->fetchRemoteAttachment($token, $url);

        return response()->stream(function () use ($remote): void {
            echo $remote->body();
        }, 200, $this->responseHeadersFromRemote($remote));
    }

    public function fetchRemoteAttachment(string $token, string $url): Response
    {
        if ($this->isPublicCdnUrl($url)) {
            $response = Http::timeout(60)->get($url);
            if ($response->successful()) {
                return $response;
            }
        }

        $response = Http::timeout(60)
            ->withToken($token)
            ->get($url);

        if ($response->successful()) {
            return $response;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $response = Http::timeout(60)->get($url.$separator.'access_token='.urlencode($token));

        $response->throw();

        return $response;
    }

    protected function isPublicCdnUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($host, 'fbcdn.net')
            || str_contains($host, 'cdninstagram.com')
            || str_contains($host, 'lookaside.fbsbx.com');
    }

    /**
     * @return array<string, string>
     */
    protected function responseHeadersFromRemote(Response $response): array
    {
        $contentType = $response->header('Content-Type') ?: 'audio/mp4';

        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
        ];
    }
}
