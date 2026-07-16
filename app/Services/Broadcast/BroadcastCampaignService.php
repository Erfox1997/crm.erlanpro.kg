<?php

namespace App\Services\Broadcast;

use App\Enums\IntegrationProvider;
use App\Jobs\ProcessBroadcastCampaignJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\CompanyIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BroadcastCampaignService
{
    public function __construct(
        private BroadcastAudienceService $audience,
    ) {}

    /**
     * @param  array{
     *     name?: string|null,
     *     channel: string,
     *     audience_type: string,
     *     pipeline_id?: int|null,
     *     stage_id?: int|null,
     *     field_filters?: list<array{key: string, value: string}>|null,
     *     body: string,
     *     delay_seconds: int,
     *     scheduled_at?: string|\DateTimeInterface|null
     * }  $data
     */
    public function create(int $companyId, int $userId, array $data): BroadcastCampaign
    {
        $this->assertChannelConnected($companyId, (string) $data['channel']);

        $criteria = [
            'audience_type' => $data['audience_type'],
            'channel' => $data['channel'],
            'pipeline_id' => $data['pipeline_id'] ?? null,
            'stage_id' => $data['stage_id'] ?? null,
            'field_filters' => $data['field_filters'] ?? [],
        ];

        $rows = $this->audience->resolve($companyId, $criteria);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'audience_type' => __('По выбранным фильтрам нет получателей.'),
            ]);
        }

        $sendable = $rows->whereNotNull('conversation_id')->count();
        if ($sendable < 1) {
            throw ValidationException::withMessages([
                'channel' => __('У выбранных клиентов нет диалогов в этом канале.'),
            ]);
        }

        $scheduledAt = ! empty($data['scheduled_at'])
            ? \Illuminate\Support\Carbon::parse($data['scheduled_at'])
            : null;

        $isFuture = $scheduledAt !== null && $scheduledAt->isFuture();

        $campaign = DB::transaction(function () use ($companyId, $userId, $data, $rows, $scheduledAt, $isFuture, $criteria) {
            $campaign = BroadcastCampaign::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'name' => $data['name'] ?? null,
                'channel' => $data['channel'],
                'audience_type' => $criteria['audience_type'],
                'pipeline_id' => $criteria['audience_type'] === BroadcastCampaign::AUDIENCE_FUNNEL
                    ? ($criteria['pipeline_id'] ?? null)
                    : null,
                'stage_id' => $criteria['audience_type'] === BroadcastCampaign::AUDIENCE_FUNNEL
                    ? ($criteria['stage_id'] ?? null)
                    : null,
                'field_filters' => $criteria['audience_type'] === BroadcastCampaign::AUDIENCE_CLIENT_FIELDS
                    ? array_values($criteria['field_filters'] ?? [])
                    : null,
                'body' => $data['body'],
                'delay_seconds' => max(1, min(120, (int) $data['delay_seconds'])),
                'scheduled_at' => $scheduledAt,
                'status' => $isFuture
                    ? BroadcastCampaign::STATUS_SCHEDULED
                    : BroadcastCampaign::STATUS_QUEUED,
                'total_recipients' => $rows->count(),
            ]);

            $now = now();
            $payload = $rows->map(function (array $row) use ($campaign, $now) {
                $hasConversation = $row['conversation_id'] !== null;

                return [
                    'broadcast_campaign_id' => $campaign->id,
                    'client_id' => $row['client_id'],
                    'messenger_conversation_id' => $row['conversation_id'],
                    'status' => $hasConversation
                        ? BroadcastRecipient::STATUS_PENDING
                        : BroadcastRecipient::STATUS_SKIPPED,
                    'error_message' => $hasConversation ? null : $row['skip_reason'],
                    'sent_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            foreach (array_chunk($payload, 200) as $chunk) {
                BroadcastRecipient::query()->insert($chunk);
            }

            $campaign->forceFill([
                'skipped_count' => $rows->whereNull('conversation_id')->count(),
            ])->save();

            return $campaign->fresh();
        });

        if (! $isFuture) {
            ProcessBroadcastCampaignJob::dispatch($campaign->id);
        }

        return $campaign;
    }

    public function dispatchScheduled(BroadcastCampaign $campaign): void
    {
        if ($campaign->status !== BroadcastCampaign::STATUS_SCHEDULED) {
            return;
        }

        $campaign->forceFill([
            'status' => BroadcastCampaign::STATUS_QUEUED,
        ])->save();

        ProcessBroadcastCampaignJob::dispatch($campaign->id);
    }

    public function cancel(BroadcastCampaign $campaign): void
    {
        if (! $campaign->isCancellable()) {
            throw ValidationException::withMessages([
                'campaign' => __('Эту рассылку нельзя отменить.'),
            ]);
        }

        $campaign->forceFill([
            'status' => BroadcastCampaign::STATUS_CANCELLED,
            'completed_at' => now(),
        ])->save();

        BroadcastRecipient::query()
            ->where('broadcast_campaign_id', $campaign->id)
            ->where('status', BroadcastRecipient::STATUS_PENDING)
            ->update([
                'status' => BroadcastRecipient::STATUS_SKIPPED,
                'error_message' => __('Рассылка отменена.'),
                'updated_at' => now(),
            ]);

        $campaign->refreshProgressCounters();
    }

    protected function assertChannelConnected(int $companyId, string $channel): void
    {
        $provider = IntegrationProvider::tryFrom($channel);
        if (! $provider) {
            throw ValidationException::withMessages([
                'channel' => __('Канал не поддерживается.'),
            ]);
        }

        $record = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', $provider->value)
            ->first();

        $connected = match ($provider) {
            IntegrationProvider::Wappi => $record !== null
                && filled($record->api_token)
                && filled($record->metadata['profile_id'] ?? null),
            IntegrationProvider::Telegram => $record !== null
                && filled($record->api_token)
                && filled($record->metadata['bot_id'] ?? null),
            default => $record !== null && filled($record->api_token),
        };

        if (! $connected) {
            throw ValidationException::withMessages([
                'channel' => __('Канал «:channel» не подключён.', ['channel' => $provider->label()]),
            ]);
        }
    }
}
