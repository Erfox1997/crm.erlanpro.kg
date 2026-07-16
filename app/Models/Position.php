<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return array_values(array_filter(
            array_map('strval', $this->permissions ?? []),
            fn (string $key) => $key !== '',
        ));
    }

    public function allows(string $pageKey): bool
    {
        return in_array($pageKey, $this->permissionKeys(), true);
    }
}
