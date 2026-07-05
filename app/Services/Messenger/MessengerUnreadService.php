<?php

namespace App\Services\Messenger;

use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use Illuminate\Support\Facades\DB;

class MessengerUnreadService
{
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

    public function totalUnreadForCompany(int $companyId): int
    {
        return (int) DB::table('messenger_messages as m')
            ->join('messenger_conversations as c', 'c.id', '=', 'm.messenger_conversation_id')
            ->where('m.company_id', $companyId)
            ->where('m.direction', 'inbound')
            ->where(function ($query) {
                $query->whereNull('c.last_read_at')
                    ->orWhereColumn('m.sent_at', '>', 'c.last_read_at');
            })
            ->count();
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
