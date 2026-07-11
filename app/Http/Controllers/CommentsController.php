<?php

namespace App\Http\Controllers;

use App\Models\InstagramComment;
use App\Models\InstagramMedia;
use App\Services\Comments\CommentsSyncService;
use App\Services\Comments\CommentsUnreadService;
use App\Services\Instagram\InstagramCommentsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommentsController extends Controller
{
    public function __construct(
        private InstagramCommentsService $comments,
        private CommentsSyncService $sync,
        private CommentsUnreadService $unread,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;
        $integration = $this->comments->integrationForCompany($companyId);

        $mediaItems = InstagramMedia::query()
            ->where('company_id', $companyId)
            ->orderByDesc('last_comment_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (InstagramMedia $media) => [
                'id' => $media->id,
                'external_id' => $media->external_id,
                'caption' => $media->caption,
                'media_type' => $media->media_type,
                'thumbnail_url' => $media->thumbnail_url ?: $media->media_url,
                'permalink' => $media->permalink,
                'comment_count' => $media->comment_count,
                'published_at' => $media->published_at?->toIso8601String(),
                'last_comment_at' => $media->last_comment_at?->toIso8601String(),
                'unread_count' => $this->unread->unreadCountForMedia($media),
            ]);

        $selectedMedia = null;
        $comments = [];

        $selectedId = $request->query('media');
        if ($selectedId) {
            $media = InstagramMedia::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $selectedId)
                ->first();

            if ($media) {
                $this->unread->markMediaRead($media);

                $selectedMedia = [
                    'id' => $media->id,
                    'caption' => $media->caption,
                    'media_type' => $media->media_type,
                    'thumbnail_url' => $media->thumbnail_url ?: $media->media_url,
                    'permalink' => $media->permalink,
                    'published_at' => $media->published_at?->toIso8601String(),
                ];

                $comments = $media->topLevelComments()
                    ->with('replies')
                    ->get()
                    ->map(fn (InstagramComment $comment) => $this->commentPayload($comment))
                    ->values()
                    ->all();
            }
        }

        return Inertia::render('Comments/Index', [
            'instagramConnected' => $integration !== null,
            'instagramAccount' => $integration ? [
                'username' => $integration->metadata['username'] ?? null,
                'name' => $integration->metadata['name'] ?? null,
            ] : null,
            'mediaItems' => $mediaItems,
            'selectedMedia' => $selectedMedia,
            'comments' => $comments,
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        set_time_limit(120);

        try {
            $result = $this->sync->syncForCompany($companyId);

            if ($result['errors'] !== []) {
                return back()->withErrors([
                    'sync' => implode(' ', array_unique($result['errors'])),
                ]);
            }

            return back()->with(
                'success',
                __('Обновлено: :media публикаций, :comments комментариев', [
                    'media' => $result['synced_media'],
                    'comments' => $result['synced_comments'],
                ]),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['sync' => $e->getMessage()]);
        }
    }

    public function reply(Request $request, InstagramComment $comment): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($comment->company_id === $companyId, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2200'],
        ]);

        $integration = $this->comments->integrationForCompany($companyId);
        if (! $integration) {
            return back()->withErrors(['reply' => __('Подключите Instagram в разделе «Интеграции».')]);
        }

        try {
            $this->comments->replyToComment($integration, $comment, $validated['body']);

            $comment->media?->update(['last_comment_at' => now()]);

            return redirect()
                ->route('comments.index', ['media' => $comment->instagram_media_id])
                ->with('success', __('Ответ отправлен.'));
        } catch (\Throwable $e) {
            return back()->withErrors(['reply' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function commentPayload(InstagramComment $comment): array
    {
        return [
            'id' => $comment->id,
            'external_id' => $comment->external_id,
            'author_username' => $comment->author_username,
            'author_name' => $comment->author_name,
            'body' => $comment->body,
            'direction' => $comment->direction,
            'sent_at' => $comment->sent_at?->toIso8601String(),
            'replies' => $comment->replies->map(fn (InstagramComment $reply) => [
                'id' => $reply->id,
                'author_username' => $reply->author_username,
                'author_name' => $reply->author_name,
                'body' => $reply->body,
                'direction' => $reply->direction,
                'sent_at' => $reply->sent_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
