<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Support\CrmPageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $positions = Position::query()
            ->where('company_id', $companyId)
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Position $position) => [
                'id' => $position->id,
                'name' => $position->name,
                'permissions' => $position->permissionKeys(),
                'users_count' => $position->users_count,
            ]);

        return Inertia::render('Positions/Index', [
            'positions' => $positions,
            'pageOptions' => CrmPageCatalog::options(),
            'pageTitle' => 'Должности',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('positions', 'name')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(CrmPageCatalog::keys())],
        ]);

        Position::query()->create([
            'company_id' => $companyId,
            'name' => trim($validated['name']),
            'permissions' => array_values(array_unique($validated['permissions'] ?? [])),
        ]);

        return back()->with('success', __('Должность создана.'));
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($position->company_id === $companyId, 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('positions', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($position->id),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(CrmPageCatalog::keys())],
        ]);

        $position->update([
            'name' => trim($validated['name']),
            'permissions' => array_values(array_unique($validated['permissions'] ?? [])),
        ]);

        return back()->with('success', __('Должность обновлена.'));
    }

    public function destroy(Request $request, Position $position): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($position->company_id === $companyId, 403);

        if ($position->users()->exists()) {
            return back()->withErrors([
                'position' => __('Нельзя удалить должность, пока к ней привязаны сотрудники.'),
            ]);
        }

        $position->delete();

        return back()->with('success', __('Должность удалена.'));
    }
}
