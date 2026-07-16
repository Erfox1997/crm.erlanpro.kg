<?php

namespace App\Jobs;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBroadcastCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(): void
    {
        $campaign = BroadcastCampaign::query()->find($this->campaignId);
        if (! $campaign) {
            return;
        }

        if (in_array($campaign->status, [
            BroadcastCampaign::STATUS_CANCELLED,
            BroadcastCampaign::STATUS_COMPLETED,
            BroadcastCampaign::STATUS_FAILED,
        ], true)) {
            return;
        }

        if ($campaign->status === BroadcastCampaign::STATUS_SCHEDULED) {
            return;
        }

        $campaign->forceFill([
            'status' => BroadcastCampaign::STATUS_RUNNING,
            'started_at' => $campaign->started_at ?? now(),
        ])->save();

        $delaySeconds = max(1, (int) $campaign->delay_seconds);
        $index = 0;

        BroadcastRecipient::query()
            ->where('broadcast_campaign_id', $campaign->id)
            ->where('status', BroadcastRecipient::STATUS_PENDING)
            ->orderBy('id')
            ->chunkById(100, function ($recipients) use (&$index, $delaySeconds) {
                foreach ($recipients as $recipient) {
                    SendBroadcastMessageJob::dispatch($recipient->id)
                        ->delay(now()->addSeconds($index * $delaySeconds));
                    $index++;
                }
            });

        if ($index === 0) {
            $campaign->refreshProgressCounters();
            $campaign->markCompletedIfDone();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $campaign = BroadcastCampaign::query()->find($this->campaignId);
        if (! $campaign || $campaign->isFinished()) {
            return;
        }

        $campaign->forceFill([
            'status' => BroadcastCampaign::STATUS_FAILED,
            'error_message' => $exception?->getMessage(),
            'completed_at' => now(),
        ])->save();

        Log::error('Broadcast campaign failed to process', [
            'campaign_id' => $this->campaignId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
