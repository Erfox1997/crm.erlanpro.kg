<?php

namespace App\Services\Comments;

use App\Models\InstagramMedia;
use Illuminate\Support\Facades\DB;

class CommentsUnreadService
{
    public function unreadCountForMedia(InstagramMedia $media): int
    {
        return (int) $media->comments()
            ->where('direction', 'inbound')
            ->where('sent_at', '>=', now()->subHours(48))
            ->when(
                $media->last_read_at,
                fn ($query) => $query->where('sent_at', '>', $media->last_read_at),
                fn ($query) => $query,
            )
            ->count();
    }

    public function totalUnreadForCompany(int $companyId): int
    {
        return (int) DB::table('instagram_comments as c')
            ->join('instagram_media as m', 'm.id', '=', 'c.instagram_media_id')
            ->where('c.company_id', $companyId)
            ->where('c.direction', 'inbound')
            ->where('c.sent_at', '>=', now()->subHours(48))
            ->where(function ($query) {
                $query->whereNull('m.last_read_at')
                    ->orWhereColumn('c.sent_at', '>', 'm.last_read_at');
            })
            ->count();
    }

    public function markMediaRead(InstagramMedia $media): void
    {
        $media->update(['last_read_at' => now()]);
    }
}
