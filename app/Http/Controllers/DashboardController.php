<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Deal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('company.tariff');

        if ($user->is_platform_admin && $user->company_id === null) {
            return Inertia::render('Dashboard', [
                'isPlatformAdmin' => true,
                'company' => null,
                'stats' => null,
            ]);
        }

        $companyId = (int) $user->company_id;

        $openDealsQuery = Deal::query()->where('company_id', $companyId)->whereNull('closed_at');

        $stats = [
            'clients_count' => Client::query()->where('company_id', $companyId)->count(),
            'deals_count' => Deal::query()->where('company_id', $companyId)->count(),
            'open_deals_count' => (clone $openDealsQuery)->count(),
            'pipeline_value' => (float) (clone $openDealsQuery)->sum('amount'),
            'revenue' => (float) Deal::query()
                ->where('company_id', $companyId)
                ->whereHas('stage', fn ($q) => $q->where('outcome', 'won'))
                ->sum('amount'),
        ];

        return Inertia::render('Dashboard', [
            'isPlatformAdmin' => false,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'tariff' => $user->company->tariff?->only(['id', 'name', 'slug']),
            ] : null,
            'stats' => $stats,
        ]);
    }
}
