<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $now = now();
        $soon = $now->copy()->addDays(7);
        $monthAgo = $now->copy()->subDays(30);

        $companiesQuery = Company::query()->with('tariff');

        $stats = [
            'companies_count' => (clone $companiesQuery)->count(),
            'active_subscriptions' => (clone $companiesQuery)
                ->where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('subscription_ends_at')
                        ->orWhere('subscription_ends_at', '>', $now);
                })
                ->count(),
            'expiring_soon' => (clone $companiesQuery)
                ->where('is_active', true)
                ->whereNotNull('subscription_ends_at')
                ->whereBetween('subscription_ends_at', [$now, $soon])
                ->count(),
            'new_registrations' => (clone $companiesQuery)
                ->where('created_at', '>=', $monthAgo)
                ->count(),
            'users_count' => User::query()->whereNotNull('company_id')->count(),
            'revenue' => number_format(
                (float) Company::query()
                    ->join('tariffs', 'companies.tariff_id', '=', 'tariffs.id')
                    ->where('tariffs.is_free', false)
                    ->sum('tariffs.price'),
                2,
                '.',
                '',
            ),
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'pageTitle' => 'Главная — программист',
        ]);
    }
}
