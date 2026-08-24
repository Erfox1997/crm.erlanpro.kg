<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TelegramSupportClient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'telegram_user_id',
        'client_chat_id',
        'username',
        'name',
        'phone',
        'company_name',
        'message',
        'status',
        'reviewed_at',
        'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
            'client_chat_id' => 'integer',
            'reviewed_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            TelegramSupportProject::class,
            'telegram_support_project_client',
            'telegram_support_client_id',
            'telegram_support_project_id',
        )->withTimestamps();
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(TelegramSupportMessage::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function markAccepted(): void
    {
        $this->forceFill([
            'status' => self::STATUS_ACCEPTED,
            'reviewed_at' => Carbon::now(),
            'blocked_at' => null,
        ])->save();
    }

    public function markRejected(): void
    {
        $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'reviewed_at' => Carbon::now(),
        ])->save();
    }

    public function markBlocked(): void
    {
        $this->forceFill([
            'blocked_at' => Carbon::now(),
        ])->save();
    }

    public function markUnblocked(): void
    {
        $this->forceFill([
            'blocked_at' => null,
        ])->save();
    }
}
