<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstagramComment extends Model
{
    protected $fillable = [
        'company_id',
        'instagram_media_id',
        'external_id',
        'parent_id',
        'author_id',
        'author_username',
        'author_name',
        'body',
        'direction',
        'is_hidden',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'is_hidden' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(InstagramMedia::class, 'instagram_media_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sent_at')->orderBy('id');
    }
}
