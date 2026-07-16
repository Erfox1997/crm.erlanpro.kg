<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\ClientFieldDefinition;
use App\Models\CompanyIntegration;
use App\Models\Pipeline;
use App\Services\Broadcast\BroadcastAudienceService;
use App\Services\Broadcast\BroadcastCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function __construct(
        private BroadcastCampaignService $campaigns,
        private BroadcastAudienceService $audience,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $campaigns = BroadcastCampaign::query()
            ->where('company_id', $companyId)
            ->with(['pipeline:id,name', 'stage:id,name', 'user:id,name'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (BroadcastCampaign $campaign) => $this->campaignPayload($campaign));

        return Inertia::render('Broadcasts/Index', [
            'campaigns' => $campaigns,
            'pipelines' => $this->pipelinesPayload($companyId),
            'clientFields' => $this->clientFieldsPayload($companyId),
            'channels' => $this->channelsPayload($companyId),
            'pageTitle' => 'Рассылка',
        ]);
    }

    public function show(Request $request, BroadcastCampaign $broadcastCampaign): Response
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($broadcastCampaign->company_id === $companyId, 403);

        $broadcastCampaign->load(['pipeline:id,name', 'stage:id,name', 'user:id,name']);

        $recipients = $broadcastCampaign->recipients()
            ->with(['client:id,name,phone', 'conversation:id,channel,participant_name,participant_username'])
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (BroadcastRecipient $recipient) => [
                'id' => $recipient->id,
                'status' => $recipient->status,
                'error_message' => $recipient->error_message,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
                'client' => $recipient->client ? [
                    'id' => $recipient->client->id,
                    'name' => $recipient->client->name,
                    'phone' => $recipient->client->phone,
                ] : null,
                'conversation' => $recipient->conversation ? [
                    'id' => $recipient->conversation->id,
                    'participant_name' => $recipient->conversation->participant_name
                        ?: $recipient->conversation->participant_username,
                ] : null,
            ]);

        return Inertia::render('Broadcasts/Show', [
            'campaign' => $this->campaignPayload($broadcastCampaign, detailed: true),
            'recipients' => $recipients,
            'pageTitle' => 'Рассылка',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'name' => 'nullable|string|max:160',
            'channel' => 'required|string|in:'.implode(',', IntegrationProvider::values()),
            'audience_type' => 'required|in:funnel,client_fields',
            'pipeline_id' => 'nullable|integer',
            'stage_id' => 'nullable|integer',
            'field_filters' => 'nullable|array|max:10',
            'field_filters.*.key' => 'required_with:field_filters|string|max:64',
            'field_filters.*.value' => 'required_with:field_filters|string|max:255',
            'body' => 'required|string|max:2000',
            'delay_seconds' => 'required|integer|min:1|max:120',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validated['audience_type'] === BroadcastCampaign::AUDIENCE_FUNNEL) {
            $request->validate([
                'pipeline_id' => 'required|integer',
                'stage_id' => 'required|integer',
            ]);
        }

        if ($validated['audience_type'] === BroadcastCampaign::AUDIENCE_CLIENT_FIELDS) {
            $request->validate([
                'field_filters' => 'required|array|min:1',
            ]);
        }

        $campaign = $this->campaigns->create(
            $companyId,
            (int) $request->user()->id,
            $validated,
        );

        $message = $campaign->status === BroadcastCampaign::STATUS_SCHEDULED
            ? __('Рассылка запланирована.')
            : __('Рассылка поставлена в очередь и отправляется в фоне.');

        return redirect()
            ->route('broadcasts.show', $campaign)
            ->with('success', $message);
    }

    public function preview(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'channel' => 'required|string|in:'.implode(',', IntegrationProvider::values()),
            'audience_type' => 'required|in:funnel,client_fields',
            'pipeline_id' => 'nullable|integer',
            'stage_id' => 'nullable|integer',
            'field_filters' => 'nullable|array|max:10',
            'field_filters.*.key' => 'required_with:field_filters|string|max:64',
            'field_filters.*.value' => 'required_with:field_filters|string|max:255',
        ]);

        try {
            $stats = $this->audience->preview($companyId, $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($stats);
    }

    public function cancel(Request $request, BroadcastCampaign $broadcastCampaign): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless($broadcastCampaign->company_id === $companyId, 403);

        $this->campaigns->cancel($broadcastCampaign);

        return back()->with('success', __('Рассылка отменена.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignPayload(BroadcastCampaign $campaign, bool $detailed = false): array
    {
        $payload = [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'channel' => $campaign->channel,
            'channel_label' => IntegrationProvider::tryFrom($campaign->channel)?->label() ?? $campaign->channel,
            'audience_type' => $campaign->audience_type,
            'audience_label' => $campaign->audience_type === BroadcastCampaign::AUDIENCE_FUNNEL
                ? 'По воронке'
                : 'По данным клиента',
            'pipeline' => $campaign->pipeline ? [
                'id' => $campaign->pipeline->id,
                'name' => $campaign->pipeline->name,
            ] : null,
            'stage' => $campaign->stage ? [
                'id' => $campaign->stage->id,
                'name' => $campaign->stage->name,
            ] : null,
            'field_filters' => $campaign->field_filters ?? [],
            'body' => $campaign->body,
            'delay_seconds' => $campaign->delay_seconds,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'status' => $campaign->status,
            'total_recipients' => $campaign->total_recipients,
            'sent_count' => $campaign->sent_count,
            'failed_count' => $campaign->failed_count,
            'skipped_count' => $campaign->skipped_count,
            'started_at' => $campaign->started_at?->toIso8601String(),
            'completed_at' => $campaign->completed_at?->toIso8601String(),
            'created_at' => $campaign->created_at?->toIso8601String(),
            'cancellable' => $campaign->isCancellable(),
            'user' => $campaign->user ? [
                'id' => $campaign->user->id,
                'name' => $campaign->user->name,
            ] : null,
        ];

        if ($detailed) {
            $payload['error_message'] = $campaign->error_message;
        }

        return $payload;
    }

    /**
     * @return list<array{id: int, name: string, stages: list<array{id: int, name: string}>}>
     */
    protected function pipelinesPayload(int $companyId): array
    {
        return Pipeline::query()
            ->where('company_id', $companyId)
            ->with(['stages' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Pipeline $pipeline) => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'stages' => $pipeline->stages->map(fn ($stage) => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, type: string, options: list<string>}>
     */
    protected function clientFieldsPayload(int $companyId): array
    {
        return ClientFieldDefinition::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ClientFieldDefinition $field) => [
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'options' => $field->options ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string, connected: bool}>
     */
    protected function channelsPayload(int $companyId): array
    {
        $stored = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('provider');

        return collect(IntegrationProvider::cases())
            ->map(function (IntegrationProvider $provider) use ($stored) {
                $record = $stored->get($provider->value);
                $connected = match ($provider) {
                    IntegrationProvider::Wappi => $record !== null
                        && filled($record->api_token)
                        && filled($record->metadata['profile_id'] ?? null),
                    IntegrationProvider::Telegram => $record !== null
                        && filled($record->api_token)
                        && filled($record->metadata['bot_id'] ?? null),
                    default => $record !== null && filled($record->api_token),
                };

                return [
                    'value' => $provider->value,
                    'label' => $provider->label(),
                    'connected' => $connected,
                ];
            })
            ->values()
            ->all();
    }
}
