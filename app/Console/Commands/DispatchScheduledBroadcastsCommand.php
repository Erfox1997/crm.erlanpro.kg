<?php

namespace App\Console\Commands;

use App\Models\BroadcastCampaign;
use App\Services\Broadcast\BroadcastCampaignService;
use Illuminate\Console\Command;

class DispatchScheduledBroadcastsCommand extends Command
{
    protected $signature = 'broadcasts:dispatch-scheduled';

    protected $description = 'Запускает запланированные рассылки, у которых наступило время отправки';

    public function handle(BroadcastCampaignService $campaigns): int
    {
        $due = BroadcastCampaign::query()
            ->where('status', BroadcastCampaign::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        foreach ($due as $campaign) {
            $campaigns->dispatchScheduled($campaign);
            $this->info("Dispatched campaign #{$campaign->id}");
        }

        if ($due->isEmpty()) {
            $this->line('No scheduled broadcasts due.');
        }

        return self::SUCCESS;
    }
}
