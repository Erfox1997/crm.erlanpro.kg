<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSupportRelay extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_chat_id',
        'owner_message_id',
        'client_chat_id',
        'client_message_id',
        'client_username',
        'client_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_chat_id' => 'integer',
            'owner_message_id' => 'integer',
            'client_chat_id' => 'integer',
            'client_message_id' => 'integer',
        ];
    }
}
