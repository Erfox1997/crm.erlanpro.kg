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
}
