<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstagramMedia extends Model
{
    protected $table = 'instagram_media';

    protected $fillable = [
        'company_id',
        'external_id',
        'caption',
        'media_type',
        'media_url',
        'thumbnail_url',
        'permalink',
        'comment_count',
        'published_at',
        'last_comment_at',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'last_comment_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(InstagramComment::class)->orderBy('sent_at')->orderBy('id');
    }

    public function topLevelComments(): HasMany
    {
        return $this->hasMany(InstagramComment::class)
            ->whereNull('parent_id')
            ->orderBy('sent_at')
            ->orderBy('id');
    }
}
