<?php

namespace App\Http\Controllers;

use App\Actions\CreateDefaultPipelineForCompany;
use App\Models\Client;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Services\Deal\DealStageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function __construct(
        private DealStageService $dealStages,
    ) {}
    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        CreateDefaultPipelineForCompany::ensure(Company::query()->findOrFail($companyId));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'client_id' => 'nullable|exists:clients,id',
            'stage_id' => 'nullable|exists:stages,id',
            'pipeline_id' => 'nullable|exists:pipelines,id',
        ]);

        $validated['client_id'] = ! empty($validated['client_id'])
            ? (int) $validated['client_id']
            : null;

        $stage = null;
        if (! empty($validated['stage_id'])) {
            $stage = Stage::query()->find($validated['stage_id']);
            abort_unless($stage && $stage->company_id === $companyId, 403);
        } elseif (! empty($validated['pipeline_id'])) {
            $pipeline = Pipeline::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $validated['pipeline_id'])
                ->firstOrFail();
            $stage = $pipeline->stages()->orderBy('sort_order')->first();
            abort_unless($stage, 422);
        } else {
            $pipeline = Pipeline::query()
                ->where('company_id', $companyId)
                ->where('is_default', true)
                ->first()
                ?? Pipeline::query()->where('company_id', $companyId)->orderBy('sort_order')->first();
            abort_unless($pipeline, 422);
            $stage = $pipeline->stages()->orderBy('sort_order')->first();
            abort_unless($stage, 422);
        }

        if (! empty($validated['client_id'])) {
            $client = Client::query()->find($validated['client_id']);
            abort_unless($client && $client->company_id === $companyId, 403);
        }

        $position = (int) Deal::query()
            ->where('company_id', $companyId)
            ->where('stage_id', $stage->id)
            ->max('position') + 1;

        $deal = Deal::query()->create([
            'company_id' => $companyId,
            'pipeline_id' => $stage->pipeline_id,
            'stage_id' => $stage->id,
            'client_id' => $validated['client_id'] ?? null,
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'amount' => $validated['amount'] ?? 0,
            'position' => $position,
            'closed_at' => $this->closedAtForStage($stage),
        ]);

        return redirect()->route('funnels.index', ['pipeline' => $stage->pipeline_id])
            ->with('success', __('Сделка создана.'));
    }

    public function updateStage(Request $request, Deal $deal): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($deal->company_id === $companyId, 403);

        $validated = $request->validate([
            'stage_id' => 'required|exists:stages,id',
        ]);

        $stage = Stage::query()->findOrFail($validated['stage_id']);
        abort_unless($stage->company_id === $companyId, 403);
        abort_unless($stage->pipeline_id === $deal->pipeline_id, 422);

        $originalPipelineId = $deal->pipeline_id;

        $this->dealStages->moveToStage($deal, $stage);
        $deal->refresh()->load('pipeline');

        if ($deal->pipeline_id !== $originalPipelineId) {
            return redirect()
                ->route('funnels.index', ['pipeline' => $deal->pipeline_id])
                ->with('success', __('Сделка перенесена в воронку «:name».', ['name' => $deal->pipeline?->name ?? '']));
        }

        return back()->with('success', __('Этап обновлён.'));
    }

    public function destroy(Request $request, Deal $deal): RedirectResponse
    {
        abort_unless($deal->company_id === (int) $request->user()->company_id, 403);

        $pipelineId = $deal->pipeline_id;

        $deal->delete();

        return redirect()->route('funnels.index', ['pipeline' => $pipelineId])
            ->with('success', __('Сделка удалена.'));
    }

    private function closedAtForStage(Stage $stage): ?\DateTimeInterface
    {
        if ($stage->outcome === 'won' || $stage->outcome === 'lost') {
            return now();
        }

        return null;
    }
}
