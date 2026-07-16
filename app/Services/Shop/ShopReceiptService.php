<?php

namespace App\Services\Shop;

use App\Enums\IntegrationProvider;
use App\Models\CompanyIntegration;
use App\Models\MessengerConversation;
use App\Models\ShopSale;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use App\Services\Telegram\TelegramMessengerService;
use App\Services\Wappi\WappiMessengerService;
use Illuminate\Support\Str;

class ShopReceiptService
{
    public function __construct(
        private WappiMessengerService $wappi,
        private FacebookMessengerService $facebook,
        private InstagramMessengerService $instagram,
        private TelegramMessengerService $telegram,
    ) {}

    /**
     * Short text receipt — only amount summary for chat.
     *
     * @param  array<string, mixed>  $salePayload
     */
    public function formatShortText(array $salePayload, string $mode = 'new'): string
    {
        $number = $salePayload['number'] ?? '?';
        $total = number_format((float) ($salePayload['total_amount'] ?? 0), 0, '.', ' ');
        $currency = 'сом';

        return match ($mode) {
            'updated' => "Сумма обновлена по чеку #{$number}: {$total} {$currency}",
            'cancelled' => "Продажа #{$number} отменена",
            default => "Сумма к оплате по чеку #{$number}: {$total} {$currency}",
        };
    }

    /**
     * Full text receipt (used as image caption fallback / history).
     *
     * @param  array<string, mixed>  $salePayload
     */
    public function formatReceipt(array $salePayload, string $mode = 'new'): string
    {
        return $this->formatShortText($salePayload, $mode);
    }

    /**
     * Build a PNG receipt image. Returns absolute path.
     *
     * @param  array<string, mixed>  $salePayload
     * @param  array{shop_name?: string|null, mode?: string}  $options
     */
    public function generateImageReceipt(array $salePayload, array $options = []): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException(__('GD не установлен на сервере — нельзя создать картинку чека.'));
        }

        $mode = $options['mode'] ?? 'new';
        $shopName = trim((string) ($options['shop_name'] ?? '')) ?: 'Магазин';
        $number = (string) ($salePayload['number'] ?? '?');
        $items = array_values($salePayload['items'] ?? []);
        $total = (float) ($salePayload['total_amount'] ?? 0);
        $credit = (float) ($salePayload['credit'] ?? 0);
        $payments = $salePayload['payments'] ?? [];
        $date = $salePayload['document_date'] ?? now()->format('Y-m-d');

        $width = 720;
        $pad = 40;
        $lineH = 34;
        $itemLines = max(1, count($items));
        $paymentLines = max(0, count($payments));
        $height = 280 + ($itemLines * $lineH) + ($paymentLines * 28) + 180;
        $height = max(640, min(1600, $height));

        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);

        $bg = imagecolorallocate($img, 18, 22, 28);
        $card = imagecolorallocate($img, 28, 34, 42);
        $amber = imagecolorallocate($img, 245, 158, 11);
        $amberSoft = imagecolorallocate($img, 251, 191, 36);
        $white = imagecolorallocate($img, 248, 250, 252);
        $muted = imagecolorallocate($img, 148, 163, 184);
        $line = imagecolorallocate($img, 51, 65, 85);
        $green = imagecolorallocate($img, 52, 211, 153);

        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
        imagefilledrectangle($img, 24, 24, $width - 24, $height - 24, $card);

        // Top accent bar
        imagefilledrectangle($img, 24, 24, $width - 24, 32, $amber);

        $font = $this->fontPath(false);
        $fontBold = $this->fontPath(true) ?: $font;

        $y = 64;
        $badge = match ($mode) {
            'updated' => 'ИЗМЕНЁННЫЙ ЧЕК',
            'cancelled' => 'ОТМЕНЕНО',
            default => 'ОПЛАЧЕНО',
        };

        if ($fontBold) {
            imagettftext($img, 13, 0, $pad, $y, $amber, $fontBold, $badge);
            $y += 42;
            imagettftext($img, 26, 0, $pad, $y, $white, $fontBold, $this->fitText($shopName, 28));
            $y += 36;
            imagettftext($img, 14, 0, $pad, $y, $muted, $font ?: $fontBold, "Чек #{$number}  ·  {$date}");
        } else {
            imagestring($img, 3, $pad, $y, $badge, $amber);
            $y += 28;
            imagestring($img, 5, $pad, $y, substr($shopName, 0, 40), $white);
            $y += 28;
            imagestring($img, 3, $pad, $y, "Check #{$number}", $muted);
        }

        $y += 28;
        imageline($img, $pad, $y, $width - $pad, $y, $line);
        $y += 36;

        foreach ($items as $item) {
            $name = $this->fitText((string) ($item['name'] ?? 'Товар'), 26);
            $qty = $item['quantity'] ?? 0;
            $amount = number_format((float) ($item['amount'] ?? 0), 0, '.', ' ').' с';
            $left = "{$name}  × {$qty}";

            if ($font) {
                imagettftext($img, 15, 0, $pad, $y, $white, $font, $left);
                $box = imagettfbbox(15, 0, $fontBold ?: $font, $amount);
                $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
                imagettftext($img, 15, 0, $width - $pad - $tw, $y, $amberSoft, $fontBold ?: $font, $amount);
            } else {
                imagestring($img, 3, $pad, $y - 12, substr($left, 0, 40), $white);
                imagestring($img, 3, $width - $pad - 90, $y - 12, $amount, $amberSoft);
            }
            $y += $lineH;
        }

        $y += 8;
        imageline($img, $pad, $y, $width - $pad, $y, $line);
        $y += 44;

        $totalLabel = 'ИТОГО';
        $totalValue = number_format($total, 0, '.', ' ').' сом';
        if ($fontBold) {
            imagettftext($img, 18, 0, $pad, $y, $muted, $fontBold, $totalLabel);
            $box = imagettfbbox(28, 0, $fontBold, $totalValue);
            $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            imagettftext($img, 28, 0, $width - $pad - $tw, $y + 4, $white, $fontBold, $totalValue);
        } else {
            imagestring($img, 4, $pad, $y - 10, $totalLabel, $muted);
            imagestring($img, 5, $width - $pad - 140, $y - 14, $totalValue, $white);
        }

        $y += 48;
        foreach ($payments as $payment) {
            $label = ($payment['account_name'] ?? 'Оплата').': '
                .number_format((float) ($payment['amount'] ?? 0), 0, '.', ' ').' сом';
            if ($font) {
                imagettftext($img, 13, 0, $pad, $y, $green, $font, $label);
            } else {
                imagestring($img, 3, $pad, $y - 10, substr($label, 0, 50), $green);
            }
            $y += 28;
        }

        if ($credit > 0.001) {
            $debt = 'В долг: '.number_format($credit, 0, '.', ' ').' сом';
            if ($font) {
                imagettftext($img, 13, 0, $pad, $y, $amber, $font, $debt);
            }
            $y += 28;
        }

        $y = $height - 70;
        imageline($img, $pad, $y - 20, $width - $pad, $y - 20, $line);
        $footer = 'Спасибо за покупку!';
        if ($font) {
            $box = imagettfbbox(13, 0, $font, $footer);
            $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            imagettftext($img, 13, 0, (int) (($width - $tw) / 2), $y, $muted, $font, $footer);
        } else {
            imagestring($img, 3, (int) ($width / 2 - 70), $y - 10, $footer, $muted);
        }

        $dir = storage_path('app/shop-receipts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.'receipt_'.Str::lower(Str::random(12)).'.png';
        imagepng($img, $path, 6);
        imagedestroy($img);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $salePayload
     * @param  array{shop_name?: string|null}  $options
     */
    public function sendSaleReceipts(
        MessengerConversation $conversation,
        array $salePayload,
        string $mode = 'new',
        array $options = [],
    ): void {
        $short = $this->formatShortText($salePayload, $mode);
        $this->sendToConversation($conversation, $short);

        if ($mode === 'cancelled') {
            return;
        }

        $imagePath = $this->generateImageReceipt($salePayload, array_merge($options, ['mode' => $mode]));

        try {
            $caption = match ($mode) {
                'updated' => 'Изменённый чек #'.($salePayload['number'] ?? ''),
                default => 'Чек #'.($salePayload['number'] ?? ''),
            };
            $this->sendImageToConversation($conversation, $imagePath, $caption);
        } finally {
            if (is_file($imagePath)) {
                @unlink($imagePath);
            }
        }
    }

    public function sendToConversation(MessengerConversation $conversation, string $text): void
    {
        [$service, $integration] = $this->channelService($conversation);
        $service->sendMessage($integration, $conversation, $text);
    }

    public function sendImageToConversation(
        MessengerConversation $conversation,
        string $filePath,
        ?string $caption = null,
    ): void {
        [$service, $integration] = $this->channelService($conversation);
        $service->sendImageMessage(
            $integration,
            $conversation,
            $filePath,
            basename($filePath),
            'image/png',
            $caption,
        );
    }

    /**
     * @return array{0: WappiMessengerService|FacebookMessengerService|InstagramMessengerService|TelegramMessengerService, 1: CompanyIntegration}
     */
    protected function channelService(MessengerConversation $conversation): array
    {
        $companyId = (int) $conversation->company_id;
        $channel = $conversation->channel;

        if ($channel === IntegrationProvider::Wappi->value) {
            $integration = $this->wappi->integrationForCompany($companyId);
            abort_unless($integration, 422, __('WhatsApp (Wappi) не подключён.'));

            return [$this->wappi, $integration];
        }

        if ($channel === IntegrationProvider::Facebook->value) {
            $integration = $this->facebook->integrationForCompany($companyId);
            abort_unless($integration, 422, __('Facebook не подключён.'));

            return [$this->facebook, $integration];
        }

        if ($channel === IntegrationProvider::Instagram->value) {
            $integration = $this->instagram->integrationForCompany($companyId);
            abort_unless($integration, 422, __('Instagram не подключён.'));

            return [$this->instagram, $integration];
        }

        if ($channel === IntegrationProvider::Telegram->value) {
            $integration = $this->telegram->integrationForCompany($companyId);
            abort_unless($integration, 422, __('Telegram не подключён.'));

            return [$this->telegram, $integration];
        }

        abort(422, __('Канал чата не поддерживает отправку чека.'));
    }

    /**
     * @param  array<string, mixed>  $saleFromShop
     * @return array<string, mixed>
     */
    public function snapshotPayload(array $saleFromShop, array $requestMeta = []): array
    {
        return [
            'sale' => $saleFromShop,
            'warehouse_id' => $requestMeta['warehouse_id'] ?? $saleFromShop['warehouse']['id'] ?? null,
            'payments' => $requestMeta['payments'] ?? [],
            'client_name' => $requestMeta['client_name'] ?? null,
            'client_phone' => $requestMeta['client_phone'] ?? null,
            'shop_name' => $requestMeta['shop_name'] ?? null,
        ];
    }

    public function receiptTextForSale(ShopSale $sale, string $mode = 'new'): string
    {
        $salePayload = $sale->payload['sale'] ?? [
            'number' => $sale->shop_document_number,
            'total_amount' => $sale->total_amount,
            'items' => [],
            'payments' => [],
        ];

        return $this->formatShortText($salePayload, $mode);
    }

    protected function fontPath(bool $bold): ?string
    {
        $candidates = $bold
            ? [
                resource_path('fonts/DejaVuSans-Bold.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                'C:/Windows/Fonts/arialbd.ttf',
            ]
            : [
                resource_path('fonts/DejaVuSans.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                'C:/Windows/Fonts/arial.ttf',
            ];

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function fitText(string $text, int $maxChars): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars - 1).'…';
    }
}
