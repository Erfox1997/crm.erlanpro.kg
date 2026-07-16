<?php

namespace App\Services\Messenger;

use App\Models\MessengerConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MessengerUnreadService
{
    public function __construct(
        private ChatDistributionService $chatDistribution,
    ) {}

    public function unreadCountForConversation(MessengerConversation $conversation): int
    {
        return (int) $conversation->messages()
            ->where('direction', 'inbound')
            ->when(
                $conversation->last_read_at,
                fn ($query) => $query->where('sent_at', '>', $conversation->last_read_at),
                fn ($query) => $query,
            )
            ->count();
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

        if ($user !== null && ! $user->is_platform_admin && $user->company_role !== 'owner') {
            $mode = $this->chatDistribution->modeForCompany($companyId);

            $query->where(function ($inner) use ($user, $mode) {
                $inner->where('c.assigned_user_id', $user->id);

                if ($mode === ChatDistributionService::MODE_FIRST_RESPONDER) {
                    $inner->orWhereNull('c.assigned_user_id');
                }
            });
        }

        return (int) $query->count();
    }

    public function markConversationRead(MessengerConversation $conversation): void
    {
        $conversation->update(['last_read_at' => now()]);
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
                $query->whereNull('last_read_at')
                    ->orWhereColumn('last_message_at', '>', 'last_read_at');
            })
            ->pluck('external_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }
}
