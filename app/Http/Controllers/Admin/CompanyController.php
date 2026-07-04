<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $companies = Company::query()
            ->with(['tariff', 'owner'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhereHas('owner', function ($ownerQuery) use ($q) {
                            $ownerQuery->where('name', 'like', '%'.$q.'%')
                                ->orWhere('email', 'like', '%'.$q.'%');
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Company $company) => $this->companyPayload($company));

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters' => ['q' => $q],
            'pageTitle' => 'Список клиентов',
        ]);
    }

    public function show(Company $company): Response
    {
        $company->load(['tariff', 'owner', 'users']);

        return Inertia::render('Admin/Companies/Show', [
            'company' => $this->companyPayload($company, detailed: true),
            'tariffs' => Tariff::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'duration_days', 'is_free']),
            'pageTitle' => $company->name,
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'tariff_id' => 'required|exists:tariffs,id',
            'subscription_ends_at' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $tariff = Tariff::query()->findOrFail($validated['tariff_id']);
        $endsAt = $validated['subscription_ends_at'] ?: null;

        if ($endsAt === null && ! $tariff->is_free) {
            $endsAt = now()->addDays($tariff->duration_days);
        }

        $company->update([
            'tariff_id' => $validated['tariff_id'],
            'subscription_ends_at' => $endsAt,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', __('Компания обновлена.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function companyPayload(Company $company, bool $detailed = false): array
    {
        $owner = $company->owner;

        $payload = [
            'id' => $company->id,
            'name' => $company->name,
            'owner_name' => $owner?->name,
            'owner_email' => $owner?->email,
            'owner_phone' => $owner?->phone ?? null,
            'tariff_id' => $company->tariff_id,
            'tariff_name' => $company->tariff?->name,
            'subscription_ends_at' => $company->subscription_ends_at?->toIso8601String(),
            'subscription_ends_at_formatted' => $company->subscription_ends_at?->format('d.m.Y'),
            'is_active' => $company->is_active,
            'status' => $company->subscriptionStatusLabel(),
            'status_is_active' => $company->subscriptionIsActive(),
            'created_at' => $company->created_at?->format('d.m.Y'),
            'users_count' => $detailed ? $company->users()->count() : null,
            'clients_count' => $detailed ? $company->clients()->count() : null,
            'deals_count' => $detailed ? $company->deals()->count() : null,
        ];

        return $payload;
    }
}
