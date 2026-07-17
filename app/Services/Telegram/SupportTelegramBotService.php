<?php

namespace App\Services\Telegram;

use App\Models\TelegramSupportRelay;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupportTelegramBotService
{
    public function token(): string
    {
        return trim((string) config('services.telegram.support_bot_token', ''));
    }

    public function botUsername(): string
    {
        return ltrim(trim((string) config('services.telegram.support_bot_username', '')), '@');
    }

    public function ownerChatId(): int
    {
        return (int) config('services.telegram.support_owner_chat_id', 0);
    }

    public function isConfigured(): bool
    {
        return $this->token() !== '' && $this->ownerChatId() > 0;
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
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookPayload(array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $message = $payload['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $chatId = (int) ($chat['id'] ?? 0);
        $messageId = (int) ($message['message_id'] ?? 0);
        if ($chatId < 1 || $messageId < 1) {
            return;
        }

        $ownerChatId = $this->ownerChatId();

        // Owner replies to a forwarded support message → send back to client
        if ($chatId === $ownerChatId) {
            $this->handleOwnerReply($message);

            return;
        }

        // Ignore non-private chats from clients
        if (($chat['type'] ?? '') !== 'private') {
            return;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        if ($text === '/start' || str_starts_with($text, '/start ')) {
            $this->sendMessage($chatId, $this->welcomeText());

            return;
        }

        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $username = isset($from['username']) ? ltrim((string) $from['username'], '@') : null;
        $name = trim(implode(' ', array_filter([
            (string) ($from['first_name'] ?? ''),
            (string) ($from['last_name'] ?? ''),
        ])));

        $header = '📩 Сообщение в поддержку ErlanPro'."\n"
            .'От: '.($name !== '' ? $name : 'без имени')
            .($username ? ' (@'.$username.')' : '')
            ."\nID: {$chatId}\n"
            .'— — —'."\n";

        $body = $text !== '' ? $text : '[вложение или сообщение без текста — ответьте reply, клиенту уйдёт ваш текст]';
        $relayText = $header.$body."\n\n↩️ Ответьте reply на это сообщение, чтобы написать клиенту.";

        $ownerMessageId = $this->sendMessage($ownerChatId, $relayText);
        if ($ownerMessageId === null) {
            $this->sendMessage($chatId, 'Сейчас не удалось передать сообщение. Попробуйте позже.');

            return;
        }

        TelegramSupportRelay::query()->create([
            'owner_chat_id' => $ownerChatId,
            'owner_message_id' => $ownerMessageId,
            'client_chat_id' => $chatId,
            'client_message_id' => $messageId,
            'client_username' => $username,
            'client_name' => $name !== '' ? $name : null,
        ]);

        // Also try native forward so media is preserved
        if ($text === '' || isset($message['photo']) || isset($message['document']) || isset($message['voice']) || isset($message['video'])) {
            $this->forwardMessage($chatId, $messageId, $ownerChatId);
        }

        $this->sendMessage($chatId, '✅ Сообщение передано в поддержку ErlanPro. Мы ответим здесь.');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleOwnerReply(array $message): void
    {
        $replyTo = $message['reply_to_message'] ?? null;
        if (! is_array($replyTo)) {
            return;
        }

        $replyToId = (int) ($replyTo['message_id'] ?? 0);
        if ($replyToId < 1) {
            return;
        }

        $relay = TelegramSupportRelay::query()
            ->where('owner_chat_id', $this->ownerChatId())
            ->where('owner_message_id', $replyToId)
            ->first();

        if (! $relay) {
            return;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        if ($text === '') {
            $this->sendMessage(
                $this->ownerChatId(),
                'Чтобы ответить клиенту, отправьте текстовый reply на сообщение поддержки.',
            );

            return;
        }

        $sent = $this->sendMessage(
            (int) $relay->client_chat_id,
            "💬 Ответ поддержки ErlanPro:\n\n".$text,
        );

        if ($sent !== null) {
            $this->sendMessage($this->ownerChatId(), '✅ Ответ отправлен клиенту.');
        }
    }

    private function welcomeText(): string
    {
        return "Здравствуйте! Это поддержка CRM ErlanPro.\n\n"
            ."Напишите вопрос или предложение по правилам / сервису — сообщение уйдёт оператору.\n\n"
            .'Сайт: https://crm.erlanpro.kg';
    }

    public function sendMessage(int $chatId, string $text): ?int
    {
        if ($this->token() === '') {
            return null;
        }

        try {
            $json = Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ])
                ->throw()
                ->json();

            $messageId = $json['result']['message_id'] ?? null;

            return is_numeric($messageId) ? (int) $messageId : null;
        } catch (\Throwable $e) {
            Log::warning('Support Telegram bot send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function forwardMessage(int $fromChatId, int $messageId, int $toChatId): void
    {
        try {
            Http::baseUrl('https://api.telegram.org')
                ->timeout(20)
                ->asJson()
                ->post('/bot'.$this->token().'/forwardMessage', [
                    'chat_id' => $toChatId,
                    'from_chat_id' => $fromChatId,
                    'message_id' => $messageId,
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Support Telegram bot forward failed', [
                'from' => $fromChatId,
                'to' => $toChatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function setWebhook(): array
    {
        if ($this->token() === '') {
            throw new \RuntimeException('TELEGRAM_SUPPORT_BOT_TOKEN is not set.');
        }

        $secret = trim((string) config('services.telegram.support_webhook_secret', ''));
        if ($secret === '') {
            throw new \RuntimeException('TELEGRAM_SUPPORT_WEBHOOK_SECRET is not set.');
        }

        $url = route('webhooks.telegram-support.handle', ['secret' => $secret]);

        $response = Http::baseUrl('https://api.telegram.org')
            ->timeout(20)
            ->asJson()
            ->post('/bot'.$this->token().'/setWebhook', [
                'url' => $url,
                'secret_token' => $secret,
                'allowed_updates' => ['message'],
                'drop_pending_updates' => false,
            ])
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }
}
