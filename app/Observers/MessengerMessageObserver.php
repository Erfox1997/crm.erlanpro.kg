<?php

namespace App\Observers;

use App\Models\MessengerMessage;
use App\Services\Messenger\MessengerFunnelService;

class MessengerMessageObserver
{
    public function __construct(
        private MessengerFunnelService $funnel,
    ) {}

    public function created(MessengerMessage $message): void
    {
        if ($message->direction !== 'inbound') {
            return;
        }

        $conversation = $message->conversation()->first();

        if (! $conversation) {
            return;
        }

        $this->funnel->ensureClientAndDeal($conversation);
    }
}
