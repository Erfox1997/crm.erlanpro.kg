<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramSupportProject extends Model
{
    protected $fillable = [
        'name',
    ];

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(
            TelegramSupportClient::class,
            'telegram_support_project_client',
            'telegram_support_project_id',
            'telegram_support_client_id',
        )->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramSupportMessage::class);
    }

    public function openMessagesCount(): int
    {
        return $this->messages()->where('status', TelegramSupportMessage::STATUS_OPEN)->count();
    }
}
