<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use Illuminate\Console\Command;

class PruneMessengerMessagesCommand extends Command
{
    protected $signature = 'messenger:prune-expired';

    protected $description = 'Удаляет сообщения старше срока хранения по тарифу компании';

    public function handle(): int
    {
        $companies = Company::query()
            ->with('tariff:id,message_retention_days')
            ->whereHas('tariff', fn ($query) => $query->whereNotNull('message_retention_days'))
            ->get(['id', 'tariff_id']);

        $totalDeleted = 0;

        foreach ($companies as $company) {
            $days = $company->messageRetentionDays();

            if ($days === null || $days < 1) {
                continue;
            }

            $cutoff = now()->subDays($days);

            $conversationIds = MessengerMessage::query()
                ->where('company_id', $company->id)
                ->where('sent_at', '<', $cutoff)
                ->distinct()
                ->pluck('messenger_conversation_id')
                ->all();

            $deleted = MessengerMessage::query()
                ->where('company_id', $company->id)
                ->where('sent_at', '<', $cutoff)
                ->delete();

            if ($deleted > 0 && $conversationIds !== []) {
                $this->refreshConversationTimestamps($conversationIds);
            }

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $this->info("Company #{$company->id}: deleted {$deleted} messages (retention {$days} days).");
            }
        }

        if ($totalDeleted === 0) {
            $this->line('No expired messenger messages.');
        } else {
            $this->info("Deleted {$totalDeleted} expired messages in total.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<int|string>  $conversationIds
     */
    private function refreshConversationTimestamps(array $conversationIds): void
    {
        foreach ($conversationIds as $conversationId) {
            $lastSentAt = MessengerMessage::query()
                ->where('messenger_conversation_id', $conversationId)
                ->max('sent_at');

            MessengerConversation::query()
                ->where('id', $conversationId)
                ->update(['last_message_at' => $lastSentAt]);
        }
    }
}
