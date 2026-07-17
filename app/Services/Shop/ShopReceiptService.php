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
     * Quote / calculate-only message (not a sale). Text only, no image.
     *
     * @param  list<array{name?: string, quantity?: float|int, price?: float|int}>  $items
     */
    public function formatQuoteText(array $items, float $totalAmount): string
    {
        $lines = ['Расчёт'];

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? 'Товар'));
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $lineTotal = round($qty * $price, 2);
            $qtyStr = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.') ?: '0';
            $lines[] = "• {$name} × {$qtyStr} = ".number_format($lineTotal, 0, '.', ' ').' сом';
        }

        $lines[] = 'Итого: '.number_format($totalAmount, 0, '.', ' ').' сом';

        return implode("\n", $lines);
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
        $payments = array_values(array_filter(
            $salePayload['payments'] ?? [],
            fn ($payment) => is_array($payment) && (float) ($payment['amount'] ?? 0) > 0.001,
        ));
        $dateRaw = (string) ($salePayload['document_date'] ?? $salePayload['created_at'] ?? now()->toDateTimeString());
        try {
            $date = \Carbon\Carbon::parse($dateRaw)->format('d.m.Y H:i');
        } catch (\Throwable) {
            $date = $dateRaw;
        }

        $statusLabel = match ($mode) {
            'updated' => 'ИЗМЕНЁННЫЙ ЧЕК',
            'cancelled' => 'ОТМЕНЕНО',
            default => 'КАССОВЫЙ ЧЕК',
        };

        // Estimate wrapped item lines for height.
        $estimatedItemRows = 0;
        foreach ($items as $item) {
            $name = (string) ($item['name'] ?? 'Товар');
            $estimatedItemRows += max(1, (int) ceil(mb_strlen($name) / 28));
            $estimatedItemRows += 1; // qty × price line
        }
        $estimatedItemRows = max(1, $estimatedItemRows);

        $width = 420;
        $pad = 28;
        $height = 220 + ($estimatedItemRows * 26) + (count($payments) * 24) + 160;
        $height = max(520, min(1800, $height));

        $img = imagecreatetruecolor($width, $height);
        $paper = imagecolorallocate($img, 252, 252, 248);
        $ink = imagecolorallocate($img, 28, 28, 28);
        $muted = imagecolorallocate($img, 90, 90, 90);
        $edge = imagecolorallocate($img, 220, 220, 214);

        imagefilledrectangle($img, 0, 0, $width, $height, $paper);
        // Soft paper edge
        imagerectangle($img, 0, 0, $width - 1, $height - 1, $edge);

        $font = $this->fontPath(false);
        $fontBold = $this->fontPath(true) ?: $font;
        $y = 36;

        $y = $this->drawCenteredText($img, $shopName, $y, 17, $ink, $fontBold ?: $font, $width, $pad);
        $y += 6;
        $y = $this->drawCenteredText($img, $statusLabel, $y, 11, $muted, $font ?: $fontBold, $width, $pad);
        $y += 4;
        $y = $this->drawCenteredText($img, "№ {$number}", $y, 12, $ink, $fontBold ?: $font, $width, $pad);
        $y += 2;
        $y = $this->drawCenteredText($img, $date, $y, 11, $muted, $font ?: $fontBold, $width, $pad);
        $y += 10;
        $y = $this->drawDashedLine($img, $pad, $y, $width - $pad, $muted);
        $y += 22;

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? 'Товар'));
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $lineAmount = (float) ($item['amount'] ?? round($qty * $price, 2));
            $qtyStr = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.') ?: '0';
            $priceStr = $this->money($price);
            $amountStr = $this->money($lineAmount).' сом';

            $nameLines = $this->wrapText($name, 30);
            foreach ($nameLines as $nameLine) {
                if ($font) {
                    imagettftext($img, 12, 0, $pad, $y, $ink, $font, $nameLine);
                } else {
                    imagestring($img, 3, $pad, $y - 12, substr($nameLine, 0, 40), $ink);
                }
                $y += 20;
            }

            $meta = "{$qtyStr} × {$priceStr}";
            if ($font) {
                imagettftext($img, 11, 0, $pad + 8, $y, $muted, $font, $meta);
                $box = imagettfbbox(12, 0, $fontBold ?: $font, $amountStr);
                $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
                imagettftext($img, 12, 0, $width - $pad - $tw, $y, $ink, $fontBold ?: $font, $amountStr);
            } else {
                imagestring($img, 2, $pad + 8, $y - 10, $meta, $muted);
                imagestring($img, 3, $width - $pad - 90, $y - 12, $amountStr, $ink);
            }
            $y += 24;
        }

        $y += 4;
        $y = $this->drawDashedLine($img, $pad, $y, $width - $pad, $muted);
        $y += 28;

        $totalLabel = 'ИТОГО';
        $totalValue = $this->money($total).' сом';
        if ($fontBold) {
            imagettftext($img, 14, 0, $pad, $y, $ink, $fontBold, $totalLabel);
            $box = imagettfbbox(16, 0, $fontBold, $totalValue);
            $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            imagettftext($img, 16, 0, $width - $pad - $tw, $y, $ink, $fontBold, $totalValue);
        } else {
            imagestring($img, 4, $pad, $y - 12, $totalLabel, $ink);
            imagestring($img, 4, $width - $pad - 120, $y - 12, $totalValue, $ink);
        }

        $y += 28;
        foreach ($payments as $payment) {
            $label = trim((string) ($payment['account_name'] ?? $payment['name'] ?? 'Оплата'));
            $payValue = $this->money((float) ($payment['amount'] ?? 0)).' сом';
            if ($font) {
                imagettftext($img, 11, 0, $pad, $y, $muted, $font, $label);
                $box = imagettfbbox(11, 0, $font, $payValue);
                $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
                imagettftext($img, 11, 0, $width - $pad - $tw, $y, $ink, $font, $payValue);
            } else {
                imagestring($img, 2, $pad, $y - 10, substr($label, 0, 24), $muted);
                imagestring($img, 2, $width - $pad - 90, $y - 10, $payValue, $ink);
            }
            $y += 22;
        }

        if ($credit > 0.001) {
            $debt = 'В долг: '.$this->money($credit).' сом';
            if ($font) {
                imagettftext($img, 11, 0, $pad, $y, $ink, $fontBold ?: $font, $debt);
            } else {
                imagestring($img, 3, $pad, $y - 10, $debt, $ink);
            }
            $y += 24;
        }

        $y += 8;
        $y = $this->drawDashedLine($img, $pad, $y, $width - $pad, $muted);
        $y += 28;
        $this->drawCenteredText($img, 'Спасибо за покупку!', $y, 12, $ink, $fontBold ?: $font, $width, $pad);
        $y += 22;
        $this->drawCenteredText($img, '***', $y, 12, $muted, $font ?: $fontBold, $width, $pad);

        $dir = storage_path('app/shop-receipts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.'receipt_'.Str::lower(Str::random(12)).'.png';
        imagepng($img, $path, 6);
        imagedestroy($img);

        return $path;
    }

    protected function money(float $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }

    /**
     * @return list<string>
     */
    protected function wrapText(string $text, int $maxChars): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return ['Товар'];
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            if (mb_strlen($word) > $maxChars) {
                $lines[] = mb_substr($word, 0, $maxChars - 1).'…';
                $current = '';
            } else {
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: ['Товар'];
    }

    protected function drawCenteredText(
        $img,
        string $text,
        int $y,
        int $size,
        int $color,
        ?string $font,
        int $width,
        int $pad,
    ): int {
        $text = $this->fitText($text, 34);

        if ($font) {
            $box = imagettfbbox($size, 0, $font, $text);
            $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            $x = (int) max($pad, ($width - $tw) / 2);
            imagettftext($img, $size, 0, $x, $y, $color, $font, $text);

            return $y + (int) round($size * 1.55);
        }

        $x = (int) max(4, ($width / 2) - (strlen($text) * 3.5));
        imagestring($img, 3, $x, $y - 10, substr($text, 0, 48), $color);

        return $y + 18;
    }

    protected function drawDashedLine($img, int $x1, int $y, int $x2, int $color): int
    {
        for ($x = $x1; $x < $x2; $x += 8) {
            imageline($img, $x, $y, min($x + 4, $x2), $y, $color);
        }

        return $y;
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
