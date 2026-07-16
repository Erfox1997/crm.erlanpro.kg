<?php

namespace App\Console\Commands;

use App\Services\Telegram\ManagerTelegramBotService;
use Illuminate\Console\Command;

class SetManagerTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:manager-webhook';

    protected $description = 'Register webhook and Mini App menu button for the manager Telegram bot';

    public function handle(ManagerTelegramBotService $managerBot): int
    {
        $me = $managerBot->getMe();
        if (is_array($me) && ! empty($me['username'])) {
            $this->info('Bot: @'.$me['username']);
            if ($managerBot->botUsername() === '') {
                $this->warn('Добавьте в .env: TELEGRAM_MANAGER_BOT_USERNAME='.$me['username']);
            }
        }

        try {
            $result = $managerBot->setWebhook();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Manager bot webhook configured.');
        $this->line('WebApp URL: '.$managerBot->webAppUrl());
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '');

        return self::SUCCESS;
    }
}
