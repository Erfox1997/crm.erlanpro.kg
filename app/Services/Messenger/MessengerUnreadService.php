<?php

namespace App\Services\Messenger;

use App\Models\MessengerConversation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MessengerUnreadService
{
    public function __construct(
        private ChatDistributionService $chatDistribution,
    ) {}

    public function unreadCountForConversation(MessengerConversation $conversation): int
    {
        $count = (int) $conversation->messages()
            ->where('direction', 'inbound')
            ->when(
                $conversation->last_read_at,
                fn ($query) => $query->where('sent_at', '>', $conversation->last_read_at),
                fn ($query) => $query,
            )
            ->count();

        if ($conversation->is_marked_unread) {
            return max($count, 1);
        }

        return $count;
    }

    public function totalUnreadForCompany(int $companyId, ?User $user = null): int
    {
        $query = DB::table('messenger_messages as m')
            ->join('messenger_conversations as c', 'c.id', '=', 'm.messenger_conversation_id')
            ->where('m.company_id', $companyId)
            ->where('m.direction', 'inbound')
            ->where(function ($inner) {
                $inner->whereNull('c.last_read_at')
                    ->orWhereColumn('m.sent_at', '>', 'c.last_read_at');
            });

        $this->applyVisibility($query, $companyId, $user, 'c');

        $messageUnread = (int) $query->count();

        $markedOnly = DB::table('messenger_conversations as c')
            ->where('c.company_id', $companyId)
            ->where('c.is_marked_unread', true)
            ->whereNotExists(function ($exists) {
                $exists->select(DB::raw(1))
                    ->from('messenger_messages as m')
                    ->whereColumn('m.messenger_conversation_id', 'c.id')
                    ->where('m.direction', 'inbound')
                    ->where(function ($inner) {
                        $inner->whereNull('c.last_read_at')
                            ->orWhereColumn('m.sent_at', '>', 'c.last_read_at');
                    });
            });

        $this->applyVisibility($markedOnly, $companyId, $user, 'c');

        return $messageUnread + (int) $markedOnly->count();
    }

    public function markConversationRead(MessengerConversation $conversation): void
    {
        $conversation->update([
            'last_read_at' => now(),
            'is_marked_unread' => false,
        ]);
    }

    public function markConversationUnread(MessengerConversation $conversation): int
    {
        $latestInboundAt = $conversation->messages()
            ->where('direction', 'inbound')
            ->max('sent_at');

        $updates = ['is_marked_unread' => true];

        if ($latestInboundAt) {
            $updates['last_read_at'] = Carbon::parse($latestInboundAt)->subSecond();
        }

        $conversation->update($updates);

        return $this->unreadCountForConversation($conversation->fresh());
    }

    /**
     * @return list<string>
     */
    public function unreadExternalConversationIds(int $companyId): array
    {
        return MessengerConversation::query()
            ->where('company_id', $companyId)
            ->whereNotNull('external_id')
            ->where(function ($query) {
                $query->where('is_marked_unread', true)
                    ->orWhere(function ($inner) {
                        $inner->whereNull('last_read_at')
                            ->orWhereColumn('last_message_at', '>', 'last_read_at');
                    });
            })
            ->pluck('external_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function applyVisibility($query, int $companyId, ?User $user, string $alias): void
    {
        if ($user === null || $user->is_platform_admin || $user->company_role === 'owner') {
            return;
        }

        $mode = $this->chatDistribution->modeForCompany($companyId);

        $query->where(function ($inner) use ($user, $mode, $alias) {
            $inner->where("{$alias}.assigned_user_id", $user->id);

            if ($mode === ChatDistributionService::MODE_FIRST_RESPONDER) {
                $inner->orWhereNull("{$alias}.assigned_user_id");
            }
        });
    }
}
