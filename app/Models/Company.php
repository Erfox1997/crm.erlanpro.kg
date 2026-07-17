<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Company extends Model
{
    protected $fillable = [
        'name',
        'tariff_id',
        'subscription_ends_at',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'subscription_ends_at' => 'datetime',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('company_role', 'owner');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class)->orderBy('name');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class)->orderBy('sort_order');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(CompanyIntegration::class);
    }

    public function effectiveSubscriptionEndsAt(): ?Carbon
    {
        if ($this->subscription_ends_at !== null) {
            return $this->subscription_ends_at;
        }

        if ($this->tariff === null || $this->created_at === null) {
            return null;
        }

        return $this->created_at->copy()->addDays($this->tariff->duration_days);
    }

    public function subscriptionIsActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $endsAt = $this->effectiveSubscriptionEndsAt();

        if ($endsAt === null) {
            return true;
        }

        return $endsAt->isFuture();
    }

    public function subscriptionStatusLabel(): string
    {
        return $this->subscriptionIsActive() ? 'Активен' : 'Истёк';
    }

    public function employeesCount(): int
    {
        return $this->users()
            ->whereNull('dismissed_at')
            ->where(function ($query) {
                $query->whereNull('company_role')
                    ->orWhere('company_role', '!=', 'owner');
            })
            ->where(function ($query) {
                $query->whereNull('is_platform_admin')
                    ->orWhere('is_platform_admin', false);
            })
            ->count();
    }

    public function maxEmployees(): ?int
    {
        return $this->tariff?->max_employees;
    }

    public function messageRetentionDays(): ?int
    {
        return $this->tariff?->message_retention_days;
    }

    public function canAddEmployees(int $count = 1): bool
    {
        $max = $this->maxEmployees();

        if ($max === null) {
            return true;
        }

        return ($this->employeesCount() + $count) <= $max;
    }

    public function remainingEmployeeSlots(): ?int
    {
        $max = $this->maxEmployees();

        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->employeesCount());
    }
}
