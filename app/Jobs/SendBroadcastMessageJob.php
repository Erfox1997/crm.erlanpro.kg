<?php

namespace App\Jobs;

use App\Models\BroadcastRecipient;
use App\Services\Broadcast\BroadcastSenderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBroadcastMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public int $recipientId,
    ) {}

    public function handle(BroadcastSenderService $sender): void
    {
        $recipient = BroadcastRecipient::query()
            ->with('campaign')
            ->find($this->recipientId);

        if (! $recipient) {
            return;
        }

        $sender->sendRecipient($recipient);
    }

    public function failed(?Throwable $exception): void
    {
        $recipient = BroadcastRecipient::query()
            ->with('campaign')
            ->find($this->recipientId);

        if (! $recipient || $recipient->status !== BroadcastRecipient::STATUS_PENDING) {
            return;
        }

        $recipient->forceFill([
            'status' => BroadcastRecipient::STATUS_FAILED,
            'error_message' => mb_substr($exception?->getMessage() ?? 'Job failed', 0, 1000),
        ])->save();

        $campaign = $recipient->campaign;
        if ($campaign) {
            $campaign->refreshProgressCounters();
            $campaign->markCompletedIfDone();
        }

        Log::warning('Broadcast message job failed', [
            'recipient_id' => $this->recipientId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
