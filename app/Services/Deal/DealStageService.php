<?php

namespace App\Services\Deal;

use App\Models\Deal;
use App\Models\Stage;

class DealStageService
{
    public function moveToStage(Deal $deal, Stage $stage): Deal
    {
        $companyId = (int) $deal->company_id;
        abort_unless($stage->company_id === $companyId, 403);
        abort_unless($stage->pipeline_id === $deal->pipeline_id, 422);

        $stage->loadMissing('outboundTunnel.toStage.pipeline');

        if ($stage->outboundTunnel?->toStage) {
            $stage = $stage->outboundTunnel->toStage;
            abort_unless($stage->company_id === $companyId, 403);
        }

        $position = (int) Deal::query()
            ->where('company_id', $companyId)
            ->where('stage_id', $stage->id)
            ->where('id', '!=', $deal->id)
            ->max('position') + 1;

        $deal->update([
            'pipeline_id' => $stage->pipeline_id,
            'stage_id' => $stage->id,
            'position' => $position,
            'closed_at' => $this->closedAtForStage($stage),
        ]);

        return $deal->refresh();
    }

    private function closedAtForStage(Stage $stage): ?\DateTimeInterface
    {
        if ($stage->outcome === 'won' || $stage->outcome === 'lost') {
            return now();
        }

        return null;
    }
}
