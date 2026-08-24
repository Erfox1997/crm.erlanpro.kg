<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TelegramSupportMessage extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'telegram_support_client_id',
        'telegram_support_project_id',
        'body',
        'media_type',
        'media_path',
        'media_mime',
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

    public function hasPlayableMedia(): bool
    {
        return in_array($this->media_type, ['voice', 'photo'], true)
            && filled($this->media_path);
    }

    public function purgeMedia(): void
    {
        if (filled($this->media_path) && Storage::disk('local')->exists($this->media_path)) {
            Storage::disk('local')->delete($this->media_path);
        }

        $this->forceFill([
            'media_type' => null,
            'media_path' => null,
            'media_mime' => null,
        ])->save();
    }
}
