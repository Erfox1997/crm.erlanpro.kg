<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerMessage extends Model
{
    protected $fillable = [
        'company_id',
        'messenger_conversation_id',
        'direction',
        'external_id',
        'body',
        'attachments',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    /**
     * @return list<array{type: string, url: string, name: ?string, mime_type: ?string}>
     */
    public function normalizedAttachments(): array
    {
        $attachments = $this->attachments ?? [];

        return is_array($attachments) ? $attachments : [];
    }

    public function previewLabel(): string
    {
        $body = trim((string) ($this->body ?? ''));
        if ($body !== '') {
            return $body;
        }

        $first = $this->normalizedAttachments()[0] ?? null;
        if (! is_array($first)) {
            return '';
        }

        return match ($first['type'] ?? '') {
            'audio' => __('Голосовое сообщение'),
            'image' => __('Фото'),
            'video' => __('Видео'),
            'file' => __('Файл'),
            default => __('Вложение'),
        };
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessengerConversation::class, 'messenger_conversation_id');
    }
}
