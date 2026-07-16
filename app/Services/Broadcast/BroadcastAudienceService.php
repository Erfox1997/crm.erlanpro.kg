<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastCampaign;
use App\Models\Client;
use App\Models\Deal;
use App\Models\MessengerConversation;
use App\Models\Stage;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BroadcastAudienceService
{
    /**
     * @param  array{
     *     audience_type: string,
     *     channel: string,
     *     pipeline_id?: int|null,
     *     stage_id?: int|null,
     *     field_filters?: list<array{key: string, value: string}>|null
     * }  $criteria
     * @return Collection<int, array{client_id: int|null, conversation_id: int|null, skip_reason: string|null}>
     */
    public function resolve(int $companyId, array $criteria): Collection
    {
        $clientIds = match ($criteria['audience_type']) {
            BroadcastCampaign::AUDIENCE_FUNNEL => $this->clientIdsByFunnel(
                $companyId,
                (int) ($criteria['pipeline_id'] ?? 0),
                (int) ($criteria['stage_id'] ?? 0),
            ),
            BroadcastCampaign::AUDIENCE_CLIENT_FIELDS => $this->clientIdsByFields(
                $companyId,
                $criteria['field_filters'] ?? [],
            ),
            default => throw ValidationException::withMessages([
                'audience_type' => __('Неизвестный тип аудитории.'),
            ]),
        };

        if ($clientIds->isEmpty()) {
            return collect();
        }

        $channel = (string) $criteria['channel'];

        $conversations = MessengerConversation::query()
            ->where('company_id', $companyId)
            ->where('channel', $channel)
            ->whereIn('client_id', $clientIds->all())
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get(['id', 'client_id']);

        $conversationByClient = [];
        foreach ($conversations as $conversation) {
            $clientId = (int) $conversation->client_id;
            if (! isset($conversationByClient[$clientId])) {
                $conversationByClient[$clientId] = (int) $conversation->id;
            }
        }

        return $clientIds->values()->map(function (int $clientId) use ($conversationByClient) {
            $conversationId = $conversationByClient[$clientId] ?? null;

            return [
                'client_id' => $clientId,
                'conversation_id' => $conversationId,
                'skip_reason' => $conversationId
                    ? null
                    : __('Нет диалога в выбранном канале.'),
            ];
        });
    }

    /**
     * @param  array{
     *     audience_type: string,
     *     channel: string,
     *     pipeline_id?: int|null,
     *     stage_id?: int|null,
     *     field_filters?: list<array{key: string, value: string}>|null
     * }  $criteria
     * @return array{total: int, sendable: int, skipped: int}
     */
    public function preview(int $companyId, array $criteria): array
    {
        $rows = $this->resolve($companyId, $criteria);
        $sendable = $rows->whereNotNull('conversation_id')->count();
        $skipped = $rows->whereNull('conversation_id')->count();

        return [
            'total' => $rows->count(),
            'sendable' => $sendable,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    protected function clientIdsByFunnel(int $companyId, int $pipelineId, int $stageId): Collection
    {
        if ($pipelineId < 1 || $stageId < 1) {
            throw ValidationException::withMessages([
                'stage_id' => __('Выберите воронку и этап.'),
            ]);
        }

        $stage = Stage::query()
            ->where('company_id', $companyId)
            ->where('pipeline_id', $pipelineId)
            ->whereKey($stageId)
            ->first();

        if (! $stage) {
            throw ValidationException::withMessages([
                'stage_id' => __('Этап не найден.'),
            ]);
        }

        return Deal::query()
            ->where('company_id', $companyId)
            ->where('pipeline_id', $pipelineId)
            ->where('stage_id', $stageId)
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  list<array{key: string, value: string}>  $filters
     * @return Collection<int, int>
     */
    protected function clientIdsByFields(int $companyId, array $filters): Collection
    {
        $filters = collect($filters)
            ->map(fn (array $filter) => [
                'key' => trim((string) ($filter['key'] ?? '')),
                'value' => trim((string) ($filter['value'] ?? '')),
            ])
            ->filter(fn (array $filter) => $filter['key'] !== '' && $filter['value'] !== '')
            ->values()
            ->all();

        if ($filters === []) {
            throw ValidationException::withMessages([
                'field_filters' => __('Добавьте хотя бы один фильтр по данным клиента.'),
            ]);
        }

        $query = Client::query()->where('company_id', $companyId);

        foreach ($filters as $filter) {
            $query->where("custom_fields->{$filter['key']}", $filter['value']);
        }

        return $query
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }
}
