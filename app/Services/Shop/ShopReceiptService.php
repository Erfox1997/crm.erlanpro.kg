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
        $date = $this->formatReceiptDateTime($salePayload);

        $statusLabel = match ($mode) {
            'updated' => 'ИЗМЕНЁННЫЙ ЧЕК',
            'cancelled' => 'ОТМЕНЕНО',
            default => 'КАССОВЫЙ ЧЕК',
        };

        $width = 760;
        $pad = 36;
        // Columns: Товар | Кол-во | Цена | Сумма
        $colSumRight = $width - $pad;
        $colPriceRight = $colSumRight - 130;
        $colQtyRight = $colPriceRight - 110;
        $nameMaxWidth = $colQtyRight - $pad - 24;

        $font = $this->fontPath(false);
        $fontBold = $this->fontPath(true) ?: $font;

        $preparedItems = [];
        $itemRows = 0;
        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? 'Товар'));
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $lineAmount = (float) ($item['amount'] ?? round($qty * $price, 2));
            $nameLines = $this->wrapTextToWidth($name, 13, $font, $nameMaxWidth);
            $preparedItems[] = [
                'name_lines' => $nameLines,
                'qty' => rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.') ?: '0',
                'price' => $this->money($price),
                'sum' => $this->money($lineAmount),
            ];
            $itemRows += max(1, count($nameLines));
        }
        $itemRows = max(1, $itemRows);

        $height = 240 + ($itemRows * 28) + (count($payments) * 26) + 150;
        $height = max(480, min(2000, $height));

        $img = imagecreatetruecolor($width, $height);
        $paper = imagecolorallocate($img, 255, 255, 255);
        $ink = imagecolorallocate($img, 30, 30, 30);
        $muted = imagecolorallocate($img, 100, 100, 100);
        $line = imagecolorallocate($img, 200, 200, 200);
        $headerBg = imagecolorallocate($img, 245, 245, 245);
        $edge = imagecolorallocate($img, 210, 210, 210);

        imagefilledrectangle($img, 0, 0, $width, $height, $paper);
        imagerectangle($img, 0, 0, $width - 1, $height - 1, $edge);

        $y = 42;

        $y = $this->drawCenteredText($img, $shopName, $y, 20, $ink, $fontBold ?: $font, $width, $pad);
        $y += 4;
        $y = $this->drawCenteredText($img, $statusLabel, $y, 12, $muted, $font ?: $fontBold, $width, $pad);
        $y += 2;
        $y = $this->drawCenteredText($img, "Чек № {$number}  ·  {$date}", $y, 12, $muted, $font ?: $fontBold, $width, $pad);
        $y += 14;

        // Table header
        $headerTop = $y - 18;
        $headerBottom = $y + 10;
        imagefilledrectangle($img, $pad - 8, $headerTop, $width - $pad + 8, $headerBottom, $headerBg);
        imageline($img, $pad - 8, $headerBottom, $width - $pad + 8, $headerBottom, $line);

        $this->drawText($img, 'Товар', $pad, $y, 12, $muted, $fontBold ?: $font);
        $this->drawRightText($img, 'Кол-во', $colQtyRight, $y, 12, $muted, $fontBold ?: $font);
        $this->drawRightText($img, 'Цена', $colPriceRight, $y, 12, $muted, $fontBold ?: $font);
        $this->drawRightText($img, 'Сумма', $colSumRight, $y, 12, $muted, $fontBold ?: $font);
        $y += 28;

        foreach ($preparedItems as $row) {
            $nameLines = $row['name_lines'];
            $first = true;
            foreach ($nameLines as $nameLine) {
                $this->drawText($img, $nameLine, $pad, $y, 13, $ink, $font ?: $fontBold);
                if ($first) {
                    $this->drawRightText($img, $row['qty'], $colQtyRight, $y, 13, $ink, $font ?: $fontBold);
                    $this->drawRightText($img, $row['price'], $colPriceRight, $y, 13, $ink, $font ?: $fontBold);
                    $this->drawRightText($img, $row['sum'], $colSumRight, $y, 13, $ink, $fontBold ?: $font);
                    $first = false;
                }
                $y += 26;
            }
        }

        $y += 4;
        imageline($img, $pad - 8, $y, $width - $pad + 8, $y, $line);
        $y += 30;

        $totalLabel = 'ИТОГО';
        $totalValue = $this->money($total).' сом';
        $this->drawText($img, $totalLabel, $pad, $y, 16, $ink, $fontBold ?: $font);
        $this->drawRightText($img, $totalValue, $colSumRight, $y, 18, $ink, $fontBold ?: $font);
        $y += 30;

        foreach ($payments as $payment) {
            $label = trim((string) ($payment['account_name'] ?? $payment['name'] ?? 'Оплата'));
            $payValue = $this->money((float) ($payment['amount'] ?? 0)).' сом';
            $this->drawText($img, $label, $pad, $y, 12, $muted, $font ?: $fontBold);
            $this->drawRightText($img, $payValue, $colSumRight, $y, 12, $ink, $font ?: $fontBold);
            $y += 24;
        }

        if ($credit > 0.001) {
            $this->drawText($img, 'В долг', $pad, $y, 12, $ink, $fontBold ?: $font);
            $this->drawRightText($img, $this->money($credit).' сом', $colSumRight, $y, 12, $ink, $fontBold ?: $font);
            $y += 24;
        }

        $y += 10;
        imageline($img, $pad - 8, $y, $width - $pad + 8, $y, $line);
        $y += 30;
        $this->drawCenteredText($img, 'Спасибо за покупку!', $y, 13, $ink, $fontBold ?: $font, $width, $pad);

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
     * Receipt datetime in Kyrgyzstan local time.
     * Prefer full timestamps; document_date is often date-only (shows 00:00).
     *
     * @param  array<string, mixed>  $salePayload
     */
    protected function formatReceiptDateTime(array $salePayload): string
    {
        $tz = 'Asia/Bishkek';

        foreach (['created_at', 'sold_at', 'updated_at', 'datetime'] as $key) {
            $raw = $salePayload[$key] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }

            try {
                return \Carbon\Carbon::parse($raw)->timezone($tz)->format('d.m.Y H:i');
            } catch (\Throwable) {
                // try next candidate
            }
        }

        // Fall back to "now" — receipt image is generated at sale/send time.
        // Avoid document_date alone: shop often sends Y-m-d without a clock time.
        return now()->timezone($tz)->format('d.m.Y H:i');
    }

    /**
     * @return list<string>
     */
    protected function wrapTextToWidth(string $text, int $size, ?string $font, int $maxWidth): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return ['Товар'];
        }

        if (! $font || $maxWidth < 40) {
            return $this->wrapText($text, 32);
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";
            $box = imagettfbbox($size, 0, $font, $candidate);
            $w = abs(($box[2] ?? 0) - ($box[0] ?? 0));

            if ($w <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            $boxWord = imagettfbbox($size, 0, $font, $word);
            $wordW = abs(($boxWord[2] ?? 0) - ($boxWord[0] ?? 0));
            if ($wordW <= $maxWidth) {
                $current = $word;
                continue;
            }

            // Hard-cut very long tokens.
            $chunk = '';
            $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($chars as $ch) {
                $try = $chunk.$ch;
                $boxTry = imagettfbbox($size, 0, $font, $try.'…');
                $tryW = abs(($boxTry[2] ?? 0) - ($boxTry[0] ?? 0));
                if ($tryW > $maxWidth && $chunk !== '') {
                    $lines[] = $chunk.'…';
                    $chunk = $ch;
                } else {
                    $chunk = $try;
                }
            }
            $current = $chunk;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: ['Товар'];
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

    protected function drawText($img, string $text, int $x, int $y, int $size, int $color, ?string $font): void
    {
        if ($font) {
            imagettftext($img, $size, 0, $x, $y, $color, $font, $text);

            return;
        }

        imagestring($img, 3, $x, $y - 12, substr($text, 0, 60), $color);
    }

    protected function drawRightText($img, string $text, int $rightX, int $y, int $size, int $color, ?string $font): void
    {
        if ($font) {
            $box = imagettfbbox($size, 0, $font, $text);
            $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            imagettftext($img, $size, 0, $rightX - $tw, $y, $color, $font, $text);

            return;
        }

        imagestring($img, 3, max(0, $rightX - (strlen($text) * 7)), $y - 12, $text, $color);
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
