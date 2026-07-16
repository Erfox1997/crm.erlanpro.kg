<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const AUDIENCE_FUNNEL = 'funnel';

    public const AUDIENCE_CLIENT_FIELDS = 'client_fields';

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'channel',
        'audience_type',
        'pipeline_id',
        'stage_id',
        'field_filters',
        'body',
        'delay_seconds',
        'scheduled_at',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'skipped_count',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'field_filters' => 'array',
            'delay_seconds' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_SCHEDULED,
            self::STATUS_QUEUED,
            self::STATUS_RUNNING,
        ], true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_FAILED,
        ], true);
    }

    public function refreshProgressCounters(): void
    {
        $this->forceFill([
            'sent_count' => $this->recipients()->where('status', BroadcastRecipient::STATUS_SENT)->count(),
            'failed_count' => $this->recipients()->where('status', BroadcastRecipient::STATUS_FAILED)->count(),
            'skipped_count' => $this->recipients()->where('status', BroadcastRecipient::STATUS_SKIPPED)->count(),
        ])->save();
    }

    public function markCompletedIfDone(): void
    {
        if ($this->isFinished()) {
            return;
        }

        $pending = $this->recipients()
            ->where('status', BroadcastRecipient::STATUS_PENDING)
            ->exists();

        if ($pending) {
            if ($this->status !== self::STATUS_RUNNING) {
                $this->forceFill([
                    'status' => self::STATUS_RUNNING,
                    'started_at' => $this->started_at ?? now(),
                ])->save();
            }

            return;
        }

        $this->refreshProgressCounters();

        $this->forceFill([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();
    }
}
