<?php

namespace App\Services\Instagram;

use App\Models\CompanyIntegration;
use App\Models\InstagramComment;
use App\Models\InstagramMedia;
use App\Services\Meta\MetaMessagingSupport;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class InstagramCommentsService
{
    public function __construct(
        private InstagramMessengerService $instagram,
    ) {}

    public function integrationForCompany(int $companyId): ?CompanyIntegration
    {
        return $this->instagram->integrationForCompany($companyId);
    }

    /**
     * @return array{synced: int, errors: list<string>}
     */
    public function syncMedia(CompanyIntegration $integration, int $limit = 25): array
    {
        $integration = $this->ensureInstagramUserId($integration);
        $igUserId = (string) ($integration->metadata['instagram_user_id'] ?? '');

        if ($igUserId === '') {
            return ['synced' => 0, 'errors' => [__('Не удалось определить ID аккаунта Instagram.')]];
        }

        $errors = [];
        $synced = 0;

        try {
            $response = $this->client($integration)->get(
                $this->url("{$igUserId}/media", $integration),
                [
                    'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,comments_count',
                    'limit' => $limit,
                ],
            );

            $response->throw();

            foreach ($response->json('data', []) as $item) {
                try {
                    $this->upsertMedia($integration, $item);
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
     * @return array{synced: int, errors: list<string>}
     */
    public function syncCommentsForMedia(CompanyIntegration $integration, InstagramMedia $media): array
    {
        $errors = [];
        $synced = 0;
        $igAccountId = (string) ($integration->metadata['instagram_user_id'] ?? '');

        try {
            $response = $this->client($integration)->get(
                $this->url("{$media->external_id}/comments", $integration),
                [
                    'fields' => 'id,text,timestamp,username,from{id,username},replies{id,text,timestamp,username,from{id,username}}',
                    'limit' => 50,
                ],
            );

            $response->throw();

            $latestAt = $media->last_comment_at;

            foreach ($response->json('data', []) as $commentData) {
                try {
                    $comment = $this->storeCommentFromApi($integration, $media, $commentData, $igAccountId);
                    if ($comment?->sent_at && ($latestAt === null || $comment->sent_at->gt($latestAt))) {
                        $latestAt = $comment->sent_at;
                    }
                    $synced++;

                    foreach ($commentData['replies']['data'] ?? [] as $replyData) {
                        $reply = $this->storeReplyFromApi(
                            $integration,
                            $media,
                            $comment,
                            $replyData,
                            $igAccountId,
                        );
                        if ($reply?->sent_at && ($latestAt === null || $reply->sent_at->gt($latestAt))) {
                            $latestAt = $reply->sent_at;
                        }
                        $synced++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }

            $media->update([
                'last_comment_at' => $latestAt ?? $media->last_comment_at,
            ]);
        } catch (RequestException $e) {
            $errors[] = $this->formatApiError($e);
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * @return array{synced_media: int, synced_comments: int, errors: list<string>}
     */
    public function syncAll(CompanyIntegration $integration, int $mediaLimit = 25): array
    {
        $mediaResult = $this->syncMedia($integration, $mediaLimit);
        $errors = $mediaResult['errors'];
        $syncedComments = 0;

        $mediaItems = InstagramMedia::query()
            ->where('company_id', $integration->company_id)
            ->orderByDesc('last_comment_at')
            ->orderByDesc('published_at')
            ->limit($mediaLimit)
            ->get();

        foreach ($mediaItems as $media) {
            $result = $this->syncCommentsForMedia($integration, $media);
            $syncedComments += $result['synced'];
            $errors = array_merge($errors, $result['errors']);
        }

        return [
            'synced_media' => $mediaResult['synced'],
            'synced_comments' => $syncedComments,
            'errors' => $errors,
        ];
    }

    public function replyToComment(
        CompanyIntegration $integration,
        InstagramComment $comment,
        string $text,
    ): InstagramComment {
        abort_unless($comment->parent_id === null, 422, __('Можно отвечать только на комментарии верхнего уровня.'));

        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException(__('Введите текст ответа.'));
        }

        $response = $this->client($integration)->post(
            $this->url("{$comment->external_id}/replies", $integration),
            ['message' => $text],
        );

        $response->throw();

        $externalId = (string) ($response->json('id') ?? '');
        $igAccountId = (string) ($integration->metadata['instagram_user_id'] ?? '');

        return InstagramComment::query()->create([
            'company_id' => $integration->company_id,
            'instagram_media_id' => $comment->instagram_media_id,
            'external_id' => $externalId !== '' ? $externalId : 'local-'.uniqid(),
            'parent_id' => $comment->id,
            'author_id' => $igAccountId,
            'author_username' => $integration->metadata['username'] ?? null,
            'author_name' => $integration->metadata['name'] ?? null,
            'body' => $text,
            'direction' => 'outbound',
            'sent_at' => now(),
        ]);
    }

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

            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'comments') {
                    continue;
                }

                $value = $change['value'] ?? null;
                if (! is_array($value)) {
                    continue;
                }

                if ($this->processCommentWebhook($integration, $igAccountId, $value)) {
                    $processed++;
                }
            }
        }

        return $processed;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function processCommentWebhook(
        CompanyIntegration $integration,
        string $igAccountId,
        array $value,
    ): bool {
        $mediaExternalId = (string) ($value['media']['id'] ?? '');
        $commentExternalId = (string) ($value['id'] ?? '');

        if ($mediaExternalId === '' || $commentExternalId === '') {
            return false;
        }

        $media = InstagramMedia::query()->firstOrCreate(
            [
                'company_id' => $integration->company_id,
                'external_id' => $mediaExternalId,
            ],
            [
                'caption' => null,
                'published_at' => now(),
            ],
        );

        $existing = InstagramComment::query()
            ->where('company_id', $integration->company_id)
            ->where('external_id', $commentExternalId)
            ->first();

        if ($existing) {
            return false;
        }

        $from = is_array($value['from'] ?? null) ? $value['from'] : [];
        $authorId = (string) ($from['id'] ?? '');
        $direction = $authorId === $igAccountId ? 'outbound' : 'inbound';

        $parentExternalId = (string) ($value['parent_id'] ?? '');
        $parentId = null;
        if ($parentExternalId !== '') {
            $parent = InstagramComment::query()
                ->where('company_id', $integration->company_id)
                ->where('external_id', $parentExternalId)
                ->first();
            $parentId = $parent?->id;
        }

        $sentAt = isset($value['created_time'])
            ? Carbon::parse($value['created_time'])
            : now();

        InstagramComment::query()->create([
            'company_id' => $integration->company_id,
            'instagram_media_id' => $media->id,
            'external_id' => $commentExternalId,
            'parent_id' => $parentId,
            'author_id' => $authorId !== '' ? $authorId : null,
            'author_username' => $from['username'] ?? ($value['username'] ?? null),
            'author_name' => $from['name'] ?? null,
            'body' => (string) ($value['text'] ?? ''),
            'direction' => $direction,
            'sent_at' => $sentAt,
        ]);

        $media->update([
            'last_comment_at' => $sentAt,
            'comment_count' => (int) $media->comment_count + 1,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function upsertMedia(CompanyIntegration $integration, array $data): InstagramMedia
    {
        $publishedAt = isset($data['timestamp'])
            ? Carbon::parse($data['timestamp'])
            : null;

        return InstagramMedia::query()->updateOrCreate(
            [
                'company_id' => $integration->company_id,
                'external_id' => (string) ($data['id'] ?? ''),
            ],
            [
                'caption' => $data['caption'] ?? null,
                'media_type' => $data['media_type'] ?? null,
                'media_url' => $data['media_url'] ?? null,
                'thumbnail_url' => $data['thumbnail_url'] ?? ($data['media_url'] ?? null),
                'permalink' => $data['permalink'] ?? null,
                'comment_count' => (int) ($data['comments_count'] ?? 0),
                'published_at' => $publishedAt,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeCommentFromApi(
        CompanyIntegration $integration,
        InstagramMedia $media,
        array $data,
        string $igAccountId,
    ): ?InstagramComment {
        $externalId = (string) ($data['id'] ?? '');
        if ($externalId === '') {
            return null;
        }

        $from = is_array($data['from'] ?? null) ? $data['from'] : [];
        $authorId = (string) ($from['id'] ?? '');
        $direction = $authorId === $igAccountId ? 'outbound' : 'inbound';
        $sentAt = isset($data['timestamp'])
            ? Carbon::parse($data['timestamp'])
            : now();

        return InstagramComment::query()->updateOrCreate(
            [
                'company_id' => $integration->company_id,
                'external_id' => $externalId,
            ],
            [
                'instagram_media_id' => $media->id,
                'parent_id' => null,
                'author_id' => $authorId !== '' ? $authorId : null,
                'author_username' => $from['username'] ?? ($data['username'] ?? null),
                'author_name' => $from['name'] ?? null,
                'body' => (string) ($data['text'] ?? ''),
                'direction' => $direction,
                'sent_at' => $sentAt,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeReplyFromApi(
        CompanyIntegration $integration,
        InstagramMedia $media,
        InstagramComment $parent,
        array $data,
        string $igAccountId,
    ): ?InstagramComment {
        $externalId = (string) ($data['id'] ?? '');
        if ($externalId === '') {
            return null;
        }

        $from = is_array($data['from'] ?? null) ? $data['from'] : [];
        $authorId = (string) ($from['id'] ?? '');
        $direction = $authorId === $igAccountId ? 'outbound' : 'inbound';
        $sentAt = isset($data['timestamp'])
            ? Carbon::parse($data['timestamp'])
            : now();

        return InstagramComment::query()->updateOrCreate(
            [
                'company_id' => $integration->company_id,
                'external_id' => $externalId,
            ],
            [
                'instagram_media_id' => $media->id,
                'parent_id' => $parent->id,
                'author_id' => $authorId !== '' ? $authorId : null,
                'author_username' => $from['username'] ?? ($data['username'] ?? null),
                'author_name' => $from['name'] ?? null,
                'body' => (string) ($data['text'] ?? ''),
                'direction' => $direction,
                'sent_at' => $sentAt,
            ],
        );
    }

    protected function ensureInstagramUserId(CompanyIntegration $integration): CompanyIntegration
    {
        if (! ($integration->metadata['instagram_user_id'] ?? null)) {
            return $this->instagram->refreshIntegrationMetadata($integration);
        }

        return $integration;
    }

    protected function findIntegrationByInstagramAccountId(string $instagramAccountId): ?CompanyIntegration
    {
        if ($instagramAccountId === '') {
            return null;
        }

        return CompanyIntegration::query()
            ->where('provider', 'instagram')
            ->whereNotNull('api_token')
            ->get()
            ->first(function (CompanyIntegration $integration) use ($instagramAccountId) {
                $metadata = $integration->metadata ?? [];

                return (string) ($metadata['instagram_user_id'] ?? '') === $instagramAccountId
                    || (string) ($metadata['page_id'] ?? '') === $instagramAccountId;
            });
    }

    protected function authMode(CompanyIntegration $integration): string
    {
        $mode = (string) ($integration->metadata['auth_mode'] ?? '');

        if (in_array($mode, ['instagram_login', 'facebook_login'], true)) {
            return $mode;
        }

        return config('services.meta.oauth_provider') === 'instagram'
            ? 'instagram_login'
            : 'facebook_login';
    }

    protected function client(CompanyIntegration $integration): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->withToken(MetaMessagingSupport::normalizeAccessToken((string) $integration->api_token));
    }

    protected function url(string $path, CompanyIntegration $integration): string
    {
        $authMode = $this->authMode($integration);
        $host = $authMode === 'instagram_login'
            ? 'https://graph.instagram.com/'
            : 'https://graph.facebook.com/';

        return $host.MetaMessagingSupport::graphVersion().'/'.ltrim($path, '/');
    }

    protected function formatApiError(RequestException $e): string
    {
        return MetaMessagingSupport::formatGraphError(
            $e->response?->json(),
            $e->getMessage(),
        );
    }
}
