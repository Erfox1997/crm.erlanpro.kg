<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Wappi = 'wappi';
    case Instagram = 'instagram';
    case Telegram = 'telegram';
    case Facebook = 'facebook';
    case ChatGpt = 'chatgpt';

    public function label(): string
    {
        return match ($this) {
            self::Wappi => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::Telegram => 'Telegram',
            self::Facebook => 'Facebook',
            self::ChatGpt => 'ChatGPT',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Wappi => 'WhatsApp через Wappi — входящие сообщения и чаты.',
            self::Instagram => 'Direct-сообщения Instagram.',
            self::Telegram => 'Telegram-бот для личных сообщений клиентов.',
            self::Facebook => 'Messenger страницы Facebook.',
            self::ChatGpt => 'ИИ-помощник в мессенджере: поправляет текст и добавляет эмодзи.',
        };
    }

    public function isMessagingChannel(): bool
    {
        return match ($this) {
            self::ChatGpt => false,
            default => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function messagingChannels(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $provider) => $provider->isMessagingChannel(),
        ));
    }

    public static function tryFromSlug(string $slug): ?self
    {
        return self::tryFrom($slug);
    }
}
