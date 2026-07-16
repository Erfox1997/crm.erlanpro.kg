<?php

namespace App\Services\Shop;

use App\Enums\IntegrationProvider;
use App\Models\MessengerConversation;
use App\Models\ShopSale;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use App\Services\Telegram\TelegramMessengerService;
use App\Services\Wappi\WappiMessengerService;

class ShopReceiptService
{
    public function __construct(
        private WappiMessengerService $wappi,
        private FacebookMessengerService $facebook,
        private InstagramMessengerService $instagram,
        private TelegramMessengerService $telegram,
    ) {}

    /**
     * @param  array<string, mixed>  $salePayload  shop API sale payload
     */
    public function formatReceipt(array $salePayload, string $mode = 'new'): string
    {
        $number = $salePayload['number'] ?? '?';
        $currency = 'сом';

        $header = match ($mode) {
            'updated' => "Изменённый чек #{$number}",
            'cancelled' => "Продажа #{$number} отменена",
            default => "Чек #{$number}",
        };

        if ($mode === 'cancelled') {
            return $header;
        }

        $lines = [$header, ''];

        foreach ($salePayload['items'] ?? [] as $item) {
            $name = $item['name'] ?? 'Товар';
            $qty = $item['quantity'] ?? 0;
            $amount = number_format((float) ($item['amount'] ?? 0), 2, '.', ' ');
            $lines[] = "{$name} x{$qty} — {$amount} {$currency}";
        }

        $lines[] = '';
        $lines[] = 'Итого: '.number_format((float) ($salePayload['total_amount'] ?? 0), 2, '.', ' ').' '.$currency;

        $payments = collect($salePayload['payments'] ?? [])
            ->map(fn ($p) => ($p['account_name'] ?? 'Оплата').': '.number_format((float) ($p['amount'] ?? 0), 2, '.', ' '))
            ->filter()
            ->implode(', ');

        if ($payments !== '') {
            $lines[] = 'Оплата: '.$payments;
        }

        if (($salePayload['credit'] ?? 0) > 0.001) {
            $lines[] = 'В долг: '.number_format((float) $salePayload['credit'], 2, '.', ' ').' '.$currency;
        }

        return implode("\n", $lines);
    }

    public function sendToConversation(MessengerConversation $conversation, string $text): void
    {
        $companyId = (int) $conversation->company_id;
        $channel = $conversation->channel;

        if ($channel === IntegrationProvider::Wappi->value) {
            $integration = $this->wappi->integrationForCompany($companyId);
            abort_unless($integration, 422, __('WhatsApp (Wappi) не подключён.'));
            $this->wappi->sendMessage($integration, $conversation, $text);

            return;
        }

        if ($channel === IntegrationProvider::Facebook->value) {
            $integration = $this->facebook->integrationForCompany($companyId);
            abort_unless($integration, 422, __('Facebook не подключён.'));
            $this->facebook->sendMessage($integration, $conversation, $text);

            return;
        }

        if ($channel === IntegrationProvider::Instagram->value) {
            $integration = $this->instagram->integrationForCompany($companyId);
            abort_unless($integration, 422, __('Instagram не подключён.'));
            $this->instagram->sendMessage($integration, $conversation, $text);

            return;
        }

        if ($channel === IntegrationProvider::Telegram->value) {
            $integration = $this->telegram->integrationForCompany($companyId);
            abort_unless($integration, 422, __('Telegram не подключён.'));
            $this->telegram->sendMessage($integration, $conversation, $text);

            return;
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

        return $this->formatReceipt($salePayload, $mode);
    }
}
