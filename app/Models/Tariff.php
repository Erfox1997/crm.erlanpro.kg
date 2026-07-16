<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tariff extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'price',
        'original_price',
        'duration_days',
        'is_free',
        'is_active',
        'sort_order',
        'max_employees',
        'message_retention_days',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'duration_days' => 'integer',
            'is_free' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'max_employees' => 'integer',
            'message_retention_days' => 'integer',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public static function free(): self
    {
        return self::query()->firstOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Бесплатный пробный',
                'description' => 'Пробный доступ на 3 дня со всеми функциями',
                'price' => 0,
                'original_price' => null,
                'duration_days' => 3,
                'is_free' => true,
                'is_active' => true,
                'sort_order' => 1,
                'max_employees' => 2,
                'message_retention_days' => 30,
            ],
        );
    }
}
