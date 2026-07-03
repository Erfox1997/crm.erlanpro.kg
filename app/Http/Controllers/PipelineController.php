<?php

namespace App\Http\Controllers;

use App\Actions\CreateDefaultPipelineForCompany;
use App\Models\Pipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PipelineController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $maxOrder = (int) Pipeline::query()->where('company_id', $companyId)->max('sort_order');

        $pipeline = Pipeline::query()->create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'is_default' => false,
            'sort_order' => $maxOrder + 1,
        ]);

        CreateDefaultPipelineForCompany::seedStandardStages($pipeline);

        return redirect()
            ->route('funnels.index', ['pipeline' => $pipeline->id])
            ->with('success', __('Воронка создана.'));
    }

    public function update(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($pipeline->company_id === $companyId, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $pipeline->update(['name' => $validated['name']]);

        return back()->with('success', __('Название воронки обновлено.'));
    }

    public function setDefault(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($pipeline->company_id === $companyId, 403);

        DB::transaction(function () use ($companyId, $pipeline) {
            Pipeline::query()->where('company_id', $companyId)->update(['is_default' => false]);
            $pipeline->update(['is_default' => true]);
        });

        return back()->with('success', __('Воронка по умолчанию обновлена.'));
    }

    public function destroy(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($pipeline->company_id === $companyId, 403);

        if ($pipeline->deals()->exists()) {
            return back()->withErrors(['pipeline' => __('Нельзя удалить воронку со сделками. Перенесите или удалите сделки.')]);
        }

        $wasDefault = $pipeline->is_default;

        $pipeline->delete();

        if ($wasDefault) {
            $next = Pipeline::query()
                ->where('company_id', $companyId)
                ->orderBy('sort_order')
                ->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return back()->with('success', __('Воронка удалена.'));
    }
}
