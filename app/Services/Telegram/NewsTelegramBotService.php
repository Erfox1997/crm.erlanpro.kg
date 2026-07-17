<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsTelegramBotService
{
    public function token(): string
    {
        return trim((string) config('services.telegram.news_bot_token', ''));
    }

    public function botUsername(): string
    {
        return ltrim(trim((string) config('services.telegram.news_bot_username', '')), '@');
    }

    public function announcementChatId(): string
    {
        return trim((string) config('services.telegram.announcement_chat_id', ''));
    }

    public function isConfigured(): bool
    {
        return $this->token() !== '' && $this->announcementChatId() !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMe(): ?array
    {
        if ($this->token() === '') {
            return null;
        }

        try {
            $json = Http::baseUrl('https://api.telegram.org')
                ->timeout(15)
                ->get('/bot'.$this->token().'/getMe')
                ->throw()
                ->json();

            return is_array($json['result'] ?? null) ? $json['result'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Send a message to the rules news channel/group. Returns Telegram message_id.
     *
     * @throws \RuntimeException
     */
    public function sendAnnouncement(string $text): int
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'Telegram-анонсы не настроены: укажите TELEGRAM_NEWS_BOT_TOKEN и TELEGRAM_ANNOUNCEMENT_CHAT_ID.',
            );
        }

        $chatId = $this->announcementChatId();
        $resolvedChatId = is_numeric($chatId) ? (int) $chatId : $chatId;

        try {
            $json = Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/sendMessage', [
                    'chat_id' => $resolvedChatId,
                    'text' => $text,
                    'disable_web_page_preview' => false,
                ])
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::warning('News Telegram bot announcement failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'Не удалось отправить сообщение в Telegram: '.$e->getMessage(),
                0,
                $e,
            );
        }

        $messageId = $json['result']['message_id'] ?? null;
        if (! is_numeric($messageId)) {
            throw new \RuntimeException('Telegram не вернул message_id.');
        }

        return (int) $messageId;
    }
}
