<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\PipelineTunnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PipelineTunnelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'from_pipeline_id' => 'required|exists:pipelines,id',
            'to_pipeline_id' => 'required|exists:pipelines,id|different:from_pipeline_id',
            'name' => 'nullable|string|max:255',
        ]);

        $from = Pipeline::query()->findOrFail($validated['from_pipeline_id']);
        $to = Pipeline::query()->findOrFail($validated['to_pipeline_id']);
        abort_unless($from->company_id === $companyId && $to->company_id === $companyId, 403);

        PipelineTunnel::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'from_pipeline_id' => $from->id,
                'to_pipeline_id' => $to->id,
            ],
            [
                'name' => $validated['name'] ?? null,
            ]
        );

        return back()->with('success', __('Туннель сохранён.'));
    }

    public function destroy(Request $request, PipelineTunnel $pipeline_tunnel): RedirectResponse
    {
        abort_unless($pipeline_tunnel->company_id === (int) $request->user()->company_id, 403);

        $pipeline_tunnel->delete();

        return back()->with('success', __('Туннель удалён.'));
    }
}
