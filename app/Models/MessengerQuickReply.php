<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerQuickReply extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'type',
        'body',
        'attachment_path',
        'attachment_mime',
        'attachment_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
