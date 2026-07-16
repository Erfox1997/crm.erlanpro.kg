<?php

namespace App\Support;

use App\Models\PlatformSetting;

class PlatformLegalDetails
{
    public const SETTING_KEY = 'legal_details';

    /**
     * @return array{
     *     legal_name: string,
     *     pin: string,
     *     activity: string,
     *     address: string,
     *     about: string,
     *     contact_email: string,
     *     contact_phone: string,
     *     site_url: string,
     *     updated_at: string|null
     * }
     */
    public static function defaults(): array
    {
        return [
            'legal_name' => 'ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ',
            'pin' => '21706199700221',
            'activity' => 'Разработка программного обеспечения (ОКУД 62.01.0)',
            'address' => 'Кыргызская Республика, Иссык-Кульская область, Ак-Суйский район, Кыдыр Аке айылный аймак, село Новоконстантиновка (Жергез), улица Оторбая, дом 20, 722348',
            'about' => "Сервис CRM ErlanPro, доступный по адресу https://crm.erlanpro.kg, предоставляется индивидуальным предпринимателем АСАНАЛИЕВЫМ ЭРЛАНОМ МАЛИКОВИЧЕМ.\n\nErlanPro — коммерческое обозначение сервиса CRM. Юридическим лицом, оказывающим услуги, является ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ.",
            'contact_email' => 'support@erlanpro.kg',
            'contact_phone' => '+996 702 300 339',
            'site_url' => 'https://crm.erlanpro.kg',
            'updated_at' => '2026-07-11',
        ];
    }

    /**
     * @return array{
     *     legal_name: string,
     *     pin: string,
     *     activity: string,
     *     address: string,
     *     about: string,
     *     contact_email: string,
     *     contact_phone: string,
     *     site_url: string,
     *     updated_at: string|null,
     *     updated_at_label: string
     * }
     */
    public static function forFrontend(): array
    {
        $defaults = self::defaults();
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        $details = [];
        foreach ($defaults as $key => $default) {
            $value = $stored[$key] ?? null;
            $details[$key] = is_string($value) && trim($value) !== ''
                ? trim($value)
                : $default;
        }

        $details['updated_at_label'] = self::formatUpdatedAt($details['updated_at']);

        return $details;
    }

    private static function formatUpdatedAt(?string $value): string
    {
        $months = [
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];

        try {
            $date = $value !== null && trim($value) !== ''
                ? \Illuminate\Support\Carbon::parse($value)
                : now();
        } catch (\Throwable) {
            return (string) $value;
        }

        return $date->format('j').' '.$months[(int) $date->format('n')].' '.$date->format('Y').' г.';
    }
}
