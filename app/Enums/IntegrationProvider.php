<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Wappi = 'wappi';
    case Instagram = 'instagram';
    case Telegram = 'telegram';
    case Facebook = 'facebook';
    case ChatGpt = 'chatgpt';
    case Shop = 'shop';
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Wappi => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::Telegram => 'Telegram',
            self::Facebook => 'Facebook',
            self::ChatGpt => 'ChatGPT',
            self::Shop => 'Магазин',
            self::Google => 'Google Contacts',
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
            self::Shop => 'Продажи из мессенджера: товары, склад и чек клиенту в чат.',
            self::Google => 'Сохраняет имя и телефон клиентов в Google Contacts — на телефоне и в WhatsApp.',
        };
    }

    public function isMessagingChannel(): bool
    {
        return match ($this) {
            self::ChatGpt, self::Shop, self::Google => false,
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
