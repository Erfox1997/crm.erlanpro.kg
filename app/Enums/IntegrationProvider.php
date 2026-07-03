<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Wappi = 'wappi';
    case Instagram = 'instagram';
    case Telegram = 'telegram';
    case Facebook = 'facebook';

    public function label(): string
    {
        return match ($this) {
            self::Wappi => 'Wappi',
            self::Instagram => 'Instagram',
            self::Telegram => 'Telegram',
            self::Facebook => 'Facebook',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Wappi => 'WhatsApp через Wappi — входящие сообщения и чаты.',
            self::Instagram => 'Подключите Instagram через Meta OAuth — входящие сообщения и чаты в CRM.',
            self::Telegram => 'Telegram-бот или личный аккаунт через API.',
            self::Facebook => 'Facebook Messenger и страница компании.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromSlug(string $slug): ?self
    {
        return self::tryFrom($slug);
    }
}
