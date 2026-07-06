<?php

namespace App\Http\Controllers;

use App\Actions\CreateDefaultPipelineForCompany;
use App\Models\Client;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FunnelController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        CreateDefaultPipelineForCompany::ensure(Company::query()->findOrFail($companyId));

        $pipelines = Pipeline::query()
            ->where('company_id', $companyId)
            ->withCount(['stages', 'deals'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Pipeline $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'is_default' => $p->is_default,
                'stages_count' => $p->stages_count,
                'deals_count' => $p->deals_count,
            ]);

        $requestedId = $request->query('pipeline');
        $selectedPipeline = null;

        if ($requestedId !== null && $requestedId !== '') {
            $selectedPipeline = Pipeline::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $requestedId)
                ->first();
        }

        if (! $selectedPipeline) {
            $selectedPipeline = Pipeline::query()
                ->where('company_id', $companyId)
                ->where('is_default', true)
                ->first()
                ?? Pipeline::query()->where('company_id', $companyId)->orderBy('sort_order')->first();
        }

        if (! $selectedPipeline) {
            return Inertia::render('Funnels/Index', [
                'pipelines' => $pipelines,
                'selectedPipelineId' => null,
                'pipeline' => null,
                'stages' => [],
                'clients' => [],
                'linkablePipelines' => [],
                'pageTitle' => 'Воронки',
            ]);
        }

        $stages = $selectedPipeline->stages()
            ->with([
                'outboundTunnel.toStage.pipeline',
                'deals' => fn ($q) => $q
                    ->with(['client', 'assignee'])
                    ->orderByDesc('position')
                    ->orderByDesc('id'),
            ])
            ->orderBy('sort_order')
            ->get();

        $mappedStages = $stages->map(function (Stage $stage) {
            $tunnel = $stage->outboundTunnel;

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'outcome' => $stage->outcome,
                'tunnel' => $tunnel && $tunnel->toStage ? [
                    'id' => $tunnel->id,
                    'to_stage_id' => $tunnel->to_stage_id,
                    'to_stage_name' => $tunnel->toStage->name,
                    'to_pipeline_id' => $tunnel->toStage->pipeline_id,
                    'to_pipeline_name' => $tunnel->toStage->pipeline?->name,
                ] : null,
                'deals' => $stage->deals->map(fn (Deal $deal) => $this->dealPayload($deal))->values(),
            ];
        });

        $linkablePipelines = Pipeline::query()
            ->where('company_id', $companyId)
            ->where('id', '!=', $selectedPipeline->id)
            ->with(['stages' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Pipeline $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'stages' => $p->stages->map(fn (Stage $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                ])->values(),
            ]);

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        return Inertia::render('Funnels/Index', [
            'pipelines' => $pipelines,
            'selectedPipelineId' => $selectedPipeline->id,
            'pipeline' => [
                'id' => $selectedPipeline->id,
                'name' => $selectedPipeline->name,
            ],
            'stages' => $mappedStages,
            'clients' => $clients,
            'linkablePipelines' => $linkablePipelines,
            'pageTitle' => 'Воронки',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dealPayload(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'amount' => (float) $deal->amount,
            'client' => $deal->client ? [
                'id' => $deal->client->id,
                'name' => $deal->client->name,
            ] : null,
            'assignee' => $deal->assignee ? [
                'id' => $deal->assignee->id,
                'name' => $deal->assignee->name,
            ] : null,
        ];
    }
}
