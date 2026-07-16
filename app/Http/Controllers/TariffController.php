<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Tariff;
use App\Support\PlatformPaymentDetails;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TariffController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Company $company */
        $company = Company::query()
            ->with('tariff')
            ->findOrFail($request->user()->company_id);

        $tariffs = Tariff::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Tariff $tariff) => [
                'id' => $tariff->id,
                'name' => $tariff->name,
                'description' => $tariff->description ?? $this->defaultDescription($tariff),
                'price' => (float) $tariff->price,
                'original_price' => $tariff->original_price !== null ? (float) $tariff->original_price : null,
                'duration_days' => $tariff->duration_days,
                'is_free' => $tariff->is_free,
                'max_employees' => $tariff->max_employees,
                'message_retention_days' => $tariff->message_retention_days,
                'is_current' => $tariff->id === $company->tariff_id,
            ]);

        $payment = PlatformPaymentDetails::forFrontend();

        return Inertia::render('Tariffs/Index', [
            'currentSubscription' => [
                'tariff_id' => $company->tariff_id,
                'tariff_name' => $company->tariff?->name,
                'ends_at' => $company->effectiveSubscriptionEndsAt()?->format('d.m.Y'),
                'is_active' => $company->subscriptionIsActive(),
            ],
            'tariffs' => $tariffs,
            'payment' => $payment,
        ]);
    }

    private function defaultDescription(Tariff $tariff): string
    {
        if ($tariff->is_free) {
            return __('Пробный доступ на :days дней со всеми функциями', [
                'days' => $tariff->duration_days,
            ]);
        }

        return __('Стандартный тариф на :days дней', [
            'days' => $tariff->duration_days,
        ]);
    }
}
