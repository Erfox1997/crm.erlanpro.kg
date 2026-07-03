<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tariff extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'sort_order',
        'max_managers',
        'max_deals',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'max_managers' => 'integer',
            'max_deals' => 'integer',
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
                'name' => 'Бесплатный',
                'sort_order' => 1,
                'max_managers' => 2,
                'max_deals' => 100,
            ],
        );
    }
}
