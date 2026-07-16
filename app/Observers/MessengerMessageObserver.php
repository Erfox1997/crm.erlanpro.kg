<?php

namespace App\Observers;

use App\Models\MessengerMessage;
use App\Services\Telegram\ManagerTelegramBotService;

class MessengerMessageObserver
{
    public function __construct(
        private ManagerTelegramBotService $managerBot,
    ) {}

    public function created(MessengerMessage $message): void
    {
        if ($message->direction !== 'inbound') {
            return;
        }

        try {
            $this->managerBot->notifyNewInboundMessage($message);
        } catch (\Throwable) {
            // Never break inbound webhook processing because of manager notify.
        }
    }
}
