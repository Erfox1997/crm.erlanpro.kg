<?php

namespace App\Services\Meta;

use App\Models\CompanyIntegration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetaAttachmentService
{
    public const ATTACHMENT_FIELDS = 'attachments{mime_type,name,file_url,image_data,video_data,audio_data}';

    public function uploadAudio(
        CompanyIntegration $integration,
        string $filePath,
        string $originalName,
        string $authMode,
        string $uploadEntityId,
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

        $url = $authMode === 'instagram_login'
            ? 'https://graph.instagram.com/'.MetaMessagingSupport::graphVersion()."/{$uploadEntityId}/message_attachments"
            : MetaMessagingSupport::graphUrl("{$uploadEntityId}/message_attachments");

        $response = Http::acceptJson()
            ->timeout(120)
            ->attach('filedata', fopen($filePath, 'r'), $originalName)
            ->post($url, [
                'message' => $message,
                'access_token' => $token,
            ]);

        $response->throw();

        $attachmentId = (string) ($response->json('attachment_id') ?? '');
        if ($attachmentId === '') {
            throw new \RuntimeException(__('Meta не вернула attachment_id для аудио.'));
        }

        return $attachmentId;
    }

    /**
     * @return array{message_id: string}
     */
    public function sendAudioAttachment(
        CompanyIntegration $integration,
        string $recipientId,
        string $attachmentId,
        string $authMode,
        string $messagesEntityId,
    ): array {
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
        ];

        if ($authMode === 'facebook_login') {
            $payload['messaging_type'] = 'RESPONSE';
        }

        $url = $authMode === 'instagram_login'
            ? 'https://graph.instagram.com/'.MetaMessagingSupport::graphVersion()."/{$messagesEntityId}/messages"
            : MetaMessagingSupport::graphUrl("{$messagesEntityId}/messages");

        $response = MetaMessagingSupport::client($token)->post($url, $payload);
        $response->throw();

        return [
            'message_id' => (string) ($response->json('message_id') ?? $response->json('id') ?? ''),
        ];
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
