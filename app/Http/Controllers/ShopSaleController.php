<?php

namespace App\Http\Controllers;

use App\Models\MessengerConversation;
use App\Models\ShopSale;
use App\Models\User;
use App\Services\Messenger\ChatDistributionService;
use App\Services\Shop\ShopIntegrationService;
use App\Services\Shop\ShopReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShopSaleController extends Controller
{
    public function __construct(
        private ShopIntegrationService $shop,
        private ShopReceiptService $receipts,
        private ChatDistributionService $chatDistribution,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $sales = ShopSale::query()
            ->where('company_id', $companyId)
            ->with(['user:id,name', 'client:id,name,phone', 'conversation:id,participant_name,channel'])
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (ShopSale $sale) => $this->serializeSale($sale));

        return Inertia::render('ShopSales/Index', [
            'sales' => $sales,
            'shopConnected' => $this->shop->isConnected($companyId),
            'pageTitle' => 'История продаж',
        ]);
    }

    public function report(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');
        $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rows = ShopSale::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', ShopSale::STATUS_CANCELLED)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('user_id, COUNT(*) as sales_count, SUM(total_amount) as total_amount')
            ->groupBy('user_id')
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id'))
            ->get(['id', 'name'])
            ->keyBy('id');

        $managers = $rows->map(fn ($row) => [
            'user_id' => $row->user_id,
            'name' => $users->get($row->user_id)?->name ?? '—',
            'sales_count' => (int) $row->sales_count,
            'total_amount' => (float) $row->total_amount,
        ])->sortByDesc('total_amount')->values();

        return Inertia::render('ShopSales/Report', [
            'month' => $month,
            'managers' => $managers,
            'totals' => [
                'sales_count' => $managers->sum('sales_count'),
                'total_amount' => round($managers->sum('total_amount'), 2),
            ],
            'pageTitle' => 'Отчёт продаж',
        ]);
    }

    public function catalog(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $integration = $this->shop->integrationForCompany($companyId);

        if (! $integration || ! $this->shop->isConnected($companyId)) {
            return response()->json(['message' => __('Магазин не подключён.')], 422);
        }

        try {
            $catalog = $this->shop->fetchCatalog($integration);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json($catalog);
    }

    public function store(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        abort_unless($conversation->company_id === $companyId, 403);
        abort_unless($this->chatDistribution->userCanViewConversation($user, $conversation), 403);

        $integration = $this->shop->integrationForCompany($companyId);
        abort_unless($integration && $this->shop->isConnected($companyId), 422, __('Магазин не подключён.'));

        $data = $this->validatedSalePayload($request);
        $clientMeta = $this->clientMeta($conversation, $data);

        try {
            $response = $this->shop->createSale($integration, array_merge($data, $clientMeta));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $saleData = $response['sale'] ?? [];
        $shopSale = ShopSale::query()->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'client_id' => $conversation->client_id,
            'shop_document_id' => (int) ($saleData['id'] ?? 0),
            'shop_document_number' => $saleData['number'] ?? null,
            'status' => ShopSale::STATUS_SOLD,
            'total_amount' => (float) ($saleData['total_amount'] ?? 0),
            'payload' => $this->receipts->snapshotPayload($saleData, array_merge($data, $clientMeta)),
        ]);

        try {
            $this->receipts->sendToConversation(
                $conversation,
                $this->receipts->formatReceipt($saleData, 'new'),
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('messenger.index', ['conversation' => $conversation->id])
                ->with('success', __('Продажа создана, но чек не отправлен: :msg', ['msg' => $e->getMessage()]));
        }

        return redirect()
            ->route('messenger.index', ['conversation' => $conversation->id])
            ->with('success', __('Продажа #:num оформлена, чек отправлен.', [
                'num' => $shopSale->shop_document_number ?? $shopSale->shop_document_id,
            ]));
    }

    public function update(Request $request, ShopSale $shopSale): RedirectResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        abort_unless($shopSale->company_id === $companyId, 403);
        abort_if($shopSale->isCancelled(), 422, __('Продажа уже отменена.'));

        $integration = $this->shop->integrationForCompany($companyId);
        abort_unless($integration && $this->shop->isConnected($companyId), 422, __('Магазин не подключён.'));

        $data = $this->validatedSalePayload($request);
        $clientMeta = [
            'client_name' => $data['client_name'] ?? ($shopSale->payload['client_name'] ?? $shopSale->client?->name),
            'client_phone' => $data['client_phone'] ?? ($shopSale->payload['client_phone'] ?? $shopSale->client?->phone),
        ];

        try {
            $response = $this->shop->updateSale(
                $integration,
                (int) $shopSale->shop_document_id,
                array_merge($data, $clientMeta),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $saleData = $response['sale'] ?? [];
        $shopSale->update([
            'shop_document_number' => $saleData['number'] ?? $shopSale->shop_document_number,
            'status' => ShopSale::STATUS_UPDATED,
            'total_amount' => (float) ($saleData['total_amount'] ?? 0),
            'payload' => $this->receipts->snapshotPayload($saleData, array_merge($data, $clientMeta)),
        ]);

        if ($shopSale->conversation) {
            try {
                $this->receipts->sendToConversation(
                    $shopSale->conversation,
                    $this->receipts->formatReceipt($saleData, 'updated'),
                );
            } catch (\Throwable $e) {
                return back()->with('success', __('Продажа изменена, но чек не отправлен: :msg', ['msg' => $e->getMessage()]));
            }
        }

        return back()->with('success', __('Продажа изменена, чек переотправлен.'));
    }

    public function destroy(Request $request, ShopSale $shopSale): RedirectResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        abort_unless($shopSale->company_id === $companyId, 403);
        abort_if($shopSale->isCancelled(), 422, __('Продажа уже отменена.'));

        $integration = $this->shop->integrationForCompany($companyId);
        abort_unless($integration && $this->shop->isConnected($companyId), 422, __('Магазин не подключён.'));

        try {
            $this->shop->deleteSale($integration, (int) $shopSale->shop_document_id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $shopSale->update(['status' => ShopSale::STATUS_CANCELLED]);

        if ($shopSale->conversation) {
            try {
                $this->receipts->sendToConversation(
                    $shopSale->conversation,
                    $this->receipts->receiptTextForSale($shopSale->fresh(), 'cancelled'),
                );
            } catch (\Throwable $e) {
                return back()->with('success', __('Продажа отменена, но сообщение не отправлено: :msg', ['msg' => $e->getMessage()]));
            }
        }

        return back()->with('success', __('Продажа отменена, клиенту отправлено уведомление.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedSalePayload(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_type' => ['nullable', 'in:primary,secondary'],
            'payments' => ['nullable', 'array'],
            'payments.*' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{client_name: ?string, client_phone: ?string}
     */
    protected function clientMeta(MessengerConversation $conversation, array $data): array
    {
        $client = $conversation->client;
        $phone = $data['client_phone']
            ?? $client?->phone
            ?? (preg_match('/^\+?\d[\d\s\-()]{6,}$/', (string) $conversation->participant_id)
                ? $conversation->participant_id
                : null);

        return [
            'client_name' => $data['client_name'] ?? $client?->name ?? $conversation->participant_name,
            'client_phone' => $phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeSale(ShopSale $sale): array
    {
        return [
            'id' => $sale->id,
            'shop_document_id' => $sale->shop_document_id,
            'number' => $sale->shop_document_number,
            'status' => $sale->status,
            'status_label' => match ($sale->status) {
                ShopSale::STATUS_UPDATED => 'Изменена',
                ShopSale::STATUS_CANCELLED => 'Отменена',
                default => 'Продана',
            },
            'total_amount' => (float) $sale->total_amount,
            'created_at' => $sale->created_at?->format('d.m.Y H:i'),
            'manager' => $sale->user?->name,
            'client' => $sale->client?->name ?? $sale->conversation?->participant_name,
            'payload' => $sale->payload,
            'can_edit' => ! $sale->isCancelled(),
        ];
    }
}
