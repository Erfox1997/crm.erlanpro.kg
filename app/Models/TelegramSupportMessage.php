<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramSupportMessage extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'telegram_support_client_id',
        'telegram_support_project_id',
        'body',
        'status',
        'client_telegram_message_id',
        'done_at',
    ];

    protected function casts(): array
    {
        return [
            'client_telegram_message_id' => 'integer',
            'done_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(TelegramSupportClient::class, 'telegram_support_client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TelegramSupportProject::class, 'telegram_support_project_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
