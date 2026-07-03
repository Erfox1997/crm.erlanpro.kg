<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StageController extends Controller
{
    public function store(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($pipeline->company_id === $companyId, 403);

        if ($request->has('stages')) {
            $validated = $request->validate([
                'stages' => 'required|array|min:1|max:20',
                'stages.*.name' => 'required|string|max:255',
                'stages.*.color' => 'nullable|string|max:32',
            ]);

            $maxOrder = (int) $pipeline->stages()->max('sort_order');

            foreach ($validated['stages'] as $stageData) {
                $maxOrder++;
                Stage::query()->create([
                    'company_id' => $companyId,
                    'pipeline_id' => $pipeline->id,
                    'name' => $stageData['name'],
                    'sort_order' => $maxOrder,
                    'color' => $stageData['color'] ?? '#94a3b8',
                    'outcome' => null,
                ]);
            }

            $count = count($validated['stages']);

            return redirect()
                ->route('funnels.index', ['pipeline' => $pipeline->id])
                ->with('success', $count === 1
                    ? __('Этап добавлен.')
                    : __('Добавлено этапов: :count', ['count' => $count]));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:32',
        ]);

        $maxOrder = (int) $pipeline->stages()->max('sort_order');

        Stage::query()->create([
            'company_id' => $companyId,
            'pipeline_id' => $pipeline->id,
            'name' => $validated['name'],
            'sort_order' => $maxOrder + 1,
            'color' => $validated['color'] ?? '#94a3b8',
            'outcome' => null,
        ]);

        return redirect()
            ->route('funnels.index', ['pipeline' => $pipeline->id])
            ->with('success', __('Этап добавлен.'));
    }

    public function update(Request $request, Stage $stage): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($stage->company_id === $companyId, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $pipelineId = $stage->pipeline_id;

        $stage->update(['name' => $validated['name']]);

        return redirect()
            ->route('funnels.index', ['pipeline' => $pipelineId])
            ->with('success', __('Название этапа обновлено.'));
    }

    public function destroy(Request $request, Stage $stage): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($stage->company_id === $companyId, 403);

        $pipelineId = $stage->pipeline_id;

        if ($stage->deals()->exists()) {
            return back()->withErrors([
                'stage' => __('Нельзя удалить этап со сделками. Перенесите или удалите сделки.'),
            ]);
        }

        $stagesCount = Stage::query()
            ->where('pipeline_id', $pipelineId)
            ->count();

        if ($stagesCount <= 1) {
            return back()->withErrors([
                'stage' => __('В воронке должен остаться хотя бы один этап.'),
            ]);
        }

        $stage->delete();

        return redirect()
            ->route('funnels.index', ['pipeline' => $pipelineId])
            ->with('success', __('Этап удалён.'));
    }

    public function reorder(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($pipeline->company_id === $companyId, 403);

        $validated = $request->validate([
            'stage_ids' => 'required|array|min:1',
            'stage_ids.*' => 'integer|exists:stages,id',
        ]);

        $expectedIds = $pipeline->stages()->orderBy('sort_order')->pluck('id')->all();
        $submittedIds = array_map('intval', $validated['stage_ids']);

        if (count($expectedIds) !== count($submittedIds)) {
            return back()->withErrors([
                'stage' => __('Некорректный список этапов.'),
            ]);
        }

        sort($expectedIds);
        $sortedSubmitted = $submittedIds;
        sort($sortedSubmitted);

        if ($expectedIds !== $sortedSubmitted) {
            return back()->withErrors([
                'stage' => __('Некорректный список этапов.'),
            ]);
        }

        foreach ($submittedIds as $order => $stageId) {
            Stage::query()
                ->where('pipeline_id', $pipeline->id)
                ->where('company_id', $companyId)
                ->whereKey($stageId)
                ->update(['sort_order' => $order]);
        }

        return redirect()
            ->route('funnels.index', ['pipeline' => $pipeline->id])
            ->with('success', __('Порядок этапов сохранён.'));
    }
}
