<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TariffController extends Controller
{
    public function index(): Response
    {
        $tariffs = Tariff::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Tariff $tariff) => $this->tariffPayload($tariff));

        return Inertia::render('Admin/Tariffs/Index', [
            'tariffs' => $tariffs,
            'pageTitle' => 'Тарифы (Планы)',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tariffs/Form', [
            'tariff' => null,
            'pageTitle' => 'Создать тариф',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTariff($request);

        Tariff::query()->create([
            ...$validated,
            'slug' => $this->makeSlug($validated['name']),
        ]);

        return redirect()
            ->route('admin.tariffs.index')
            ->with('success', __('Тариф создан.'));
    }

    public function edit(Tariff $tariff): Response
    {
        return Inertia::render('Admin/Tariffs/Form', [
            'tariff' => $this->tariffPayload($tariff),
            'pageTitle' => 'Изменить тариф',
        ]);
    }

    public function update(Request $request, Tariff $tariff): RedirectResponse
    {
        $validated = $this->validateTariff($request);

        $tariff->update($validated);

        return redirect()
            ->route('admin.tariffs.index')
            ->with('success', __('Тариф обновлён.'));
    }

    public function destroy(Tariff $tariff): RedirectResponse
    {
        if ($tariff->companies()->exists()) {
            return back()->withErrors([
                'tariff' => __('Нельзя удалить тариф, к которому привязаны компании.'),
            ]);
        }

        $tariff->delete();

        return redirect()
            ->route('admin.tariffs.index')
            ->with('success', __('Тариф удалён.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTariff(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'duration_days' => 'required|integer|min:1|max:3650',
            'is_free' => 'required|boolean',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:65535',
            'max_employees' => 'nullable|integer|min:1',
            'message_retention_days' => 'nullable|integer|min:1|max:3650',
            'max_deals' => 'nullable|integer|min:1',
        ]);

        if ($validated['is_free']) {
            $validated['price'] = 0;
            $validated['original_price'] = null;
        }

        return $validated;
    }

    private function makeSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'tariff';
        $counter = 1;

        while (Tariff::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function tariffPayload(Tariff $tariff): array
    {
        return [
            'id' => $tariff->id,
            'slug' => $tariff->slug,
            'name' => $tariff->name,
            'description' => $tariff->description,
            'price' => (float) $tariff->price,
            'original_price' => $tariff->original_price !== null ? (float) $tariff->original_price : null,
            'duration_days' => $tariff->duration_days,
            'is_free' => $tariff->is_free,
            'is_active' => $tariff->is_active,
            'sort_order' => $tariff->sort_order,
            'max_employees' => $tariff->max_employees,
            'message_retention_days' => $tariff->message_retention_days,
            'max_deals' => $tariff->max_deals,
            'companies_count' => $tariff->companies()->count(),
        ];
    }
}
