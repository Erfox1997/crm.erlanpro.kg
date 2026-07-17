<?php

namespace App\Console\Commands;

use App\Services\Telegram\SupportTelegramBotService;
use Illuminate\Console\Command;

class SetSupportTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:support-webhook';

    protected $description = 'Register webhook for the support Telegram bot (ErlanProtask_bot)';

    public function handle(SupportTelegramBotService $supportBot): int
    {
        $me = $supportBot->getMe();
        if (is_array($me) && ! empty($me['username'])) {
            $this->info('Bot: @'.$me['username']);
            if ($supportBot->botUsername() === '') {
                $this->warn('Добавьте в .env: TELEGRAM_SUPPORT_BOT_USERNAME='.$me['username']);
            }
        }

        if ($supportBot->ownerChatId() < 1) {
            $this->error('Укажите TELEGRAM_SUPPORT_OWNER_CHAT_ID в .env');

            return self::FAILURE;
        }

        try {
            $result = $supportBot->setWebhook();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Support bot webhook configured.');
        $this->line('Owner chat_id: '.$supportBot->ownerChatId());
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '');

        return self::SUCCESS;
    }
}
