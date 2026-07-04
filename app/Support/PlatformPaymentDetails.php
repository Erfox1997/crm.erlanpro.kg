<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Storage;

class PlatformPaymentDetails
{
    /**
     * @return array{text: string, whatsapp: string, qr_url: string|null, whatsapp_url: string|null}
     */
    public static function forFrontend(): array
    {
        $details = PlatformSetting::getValue('payment_details', [
            'text' => '',
            'whatsapp' => '',
            'qr_path' => null,
        ]);

        $whatsapp = trim((string) ($details['whatsapp'] ?? ''));

        return [
            'text' => trim((string) ($details['text'] ?? '')),
            'whatsapp' => $whatsapp,
            'qr_url' => isset($details['qr_path'])
                ? Storage::disk('public')->url($details['qr_path'])
                : null,
            'whatsapp_url' => self::whatsappUrl($whatsapp),
        ];
    }

    public static function whatsappUrl(string $phone, string $message = ''): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        $url = 'https://wa.me/'.$digits;

        if ($message !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }
}
