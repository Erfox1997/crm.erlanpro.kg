<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopSale extends Model
{
    public const STATUS_SOLD = 'sold';

    public const STATUS_UPDATED = 'updated';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'user_id',
        'conversation_id',
        'client_id',
        'shop_document_id',
        'shop_document_number',
        'status',
        'total_amount',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'payload' => 'array',
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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessengerConversation::class, 'conversation_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
