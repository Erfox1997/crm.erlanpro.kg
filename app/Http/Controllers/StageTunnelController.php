<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Models\StageTunnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StageTunnelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'from_stage_id' => 'required|exists:stages,id',
            'to_stage_id' => 'required|exists:stages,id|different:from_stage_id',
        ]);

        $fromStage = Stage::query()->findOrFail($validated['from_stage_id']);
        $toStage = Stage::query()->findOrFail($validated['to_stage_id']);

        abort_unless($fromStage->company_id === $companyId && $toStage->company_id === $companyId, 403);
        abort_unless($fromStage->pipeline_id !== $toStage->pipeline_id, 422);

        StageTunnel::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'from_stage_id' => $fromStage->id,
            ],
            [
                'to_stage_id' => $toStage->id,
            ]
        );

        return back()->with('success', __('Связка этапов сохранена.'));
    }

    public function destroy(Request $request, StageTunnel $stage_tunnel): RedirectResponse
    {
        abort_unless($stage_tunnel->company_id === (int) $request->user()->company_id, 403);

        $stage_tunnel->delete();

        return back()->with('success', __('Связка этапов удалена.'));
    }
}
