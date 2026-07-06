<?php

namespace App\Services\Messenger;

use App\Actions\CreateDefaultPipelineForCompany;
use App\Models\Client;
use App\Models\Company;
use App\Models\Deal;
use App\Models\MessengerConversation;
use App\Models\Pipeline;
use App\Models\Stage;

class MessengerFunnelService
{
    public function ensureClientAndDeal(MessengerConversation $conversation): ?Deal
    {
        $companyId = (int) $conversation->company_id;

        $company = Company::query()->find($companyId);
        if (! $company) {
            return null;
        }

        CreateDefaultPipelineForCompany::ensure($company);

        $client = $this->ensureClient($conversation);
        if (! $client) {
            return null;
        }

        return $this->ensureDeal($client, $companyId);
    }

    public function resolveDeal(MessengerConversation $conversation): ?Deal
    {
        if (! $conversation->client_id) {
            return null;
        }

        return Deal::query()
            ->where('company_id', $conversation->company_id)
            ->where('client_id', $conversation->client_id)
            ->with(['stage', 'pipeline'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function dealPayloadForConversation(MessengerConversation $conversation): ?array
    {
        $deal = $this->resolveDeal($conversation);

        if (! $deal) {
            $deal = $this->ensureClientAndDeal($conversation);
        }

        if (! $deal) {
            return null;
        }

        $deal->loadMissing(['stage', 'pipeline']);

        $stages = Stage::query()
            ->where('pipeline_id', $deal->pipeline_id)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color']);

        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'pipeline_id' => $deal->pipeline_id,
            'pipeline_name' => $deal->pipeline?->name,
            'stage_id' => $deal->stage_id,
            'stage_name' => $deal->stage?->name,
            'stage_color' => $deal->stage?->color,
            'stages' => $stages->map(fn (Stage $stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
            ])->values()->all(),
        ];
    }

    private function ensureClient(MessengerConversation $conversation): ?Client
    {
        if ($conversation->client_id) {
            return Client::query()
                ->where('company_id', $conversation->company_id)
                ->whereKey($conversation->client_id)
                ->first();
        }

        $name = trim((string) (
            $conversation->participant_name
            ?? $conversation->participant_username
            ?? ''
        ));

        if ($name === '') {
            $name = 'Клиент';
        }

        $username = ltrim((string) ($conversation->participant_username ?? ''), '@');
        $phone = null;

        if ($username !== '' && preg_match('/^\+?\d[\d\s()-]{6,}$/', $username)) {
            $phone = $username;
        }

        $client = Client::query()->create([
            'company_id' => $conversation->company_id,
            'name' => $name,
            'phone' => $phone,
            'email' => null,
            'custom_fields' => $username !== '' ? ['messenger_contact' => $username] : [],
        ]);

        $conversation->update(['client_id' => $client->id]);

        return $client;
    }

    private function ensureDeal(Client $client, int $companyId): ?Deal
    {
        $pipeline = $this->defaultPipeline($companyId);
        if (! $pipeline) {
            return null;
        }

        $existingDeal = Deal::query()
            ->where('company_id', $companyId)
            ->where('client_id', $client->id)
            ->orderByDesc('id')
            ->first();

        if ($existingDeal) {
            return $existingDeal;
        }

        $stage = $pipeline->stages()->orderBy('sort_order')->first();
        if (! $stage) {
            return null;
        }

        $position = (int) Deal::query()
            ->where('company_id', $companyId)
            ->where('stage_id', $stage->id)
            ->max('position') + 1;

        return Deal::query()->create([
            'company_id' => $companyId,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'client_id' => $client->id,
            'user_id' => null,
            'title' => $client->name,
            'amount' => 0,
            'position' => $position,
            'closed_at' => null,
        ]);
    }

    private function defaultPipeline(int $companyId): ?Pipeline
    {
        return Pipeline::query()
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->first()
            ?? Pipeline::query()
                ->where('company_id', $companyId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
    }
}
