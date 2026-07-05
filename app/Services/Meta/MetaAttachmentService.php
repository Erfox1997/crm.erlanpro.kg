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
        if ($this->isMetaSupportedAudio($originalName, $mimeType)) {
            return [$filePath, $this->normalizeAudioFilename($originalName, $mimeType), $mimeType];
        }

        if ($this->canTranscodeWithFfmpeg()) {
            return $this->transcodeToM4a($filePath);
        }

        throw new \RuntimeException(
            __('Meta принимает M4A, MP4, WAV или AAC. Установите ffmpeg на сервере или запишите голос в Safari/Edge.'),
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
     * @return array{0: string, 1: string, 2: string}
     */
    protected function transcodeToM4a(string $filePath): array
    {
        $outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('voice_', true).'.m4a';
        $command = sprintf(
            'ffmpeg -y -i %s -vn -acodec aac -b:a 64k -ac 1 -ar 44100 -movflags +faststart -f ipod %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($outputPath),
        );

        $output = [];
        $code = 1;
        exec($command, $output, $code);

        if ($code !== 0 || ! is_file($outputPath)) {
            throw new \RuntimeException(__('Не удалось конвертировать аудио для Meta.'));
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
                $originalName,
                ['Content-Type' => $mimeType ?: 'audio/mp4'],
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
            'mp3' => 'audio/mpeg',
            'm4a', 'mp4' => 'audio/mp4',
            default => 'audio/mp4',
        };
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
