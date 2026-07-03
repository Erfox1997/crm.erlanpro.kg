<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'provider',
        'api_token',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function providerEnum(): ?IntegrationProvider
    {
        return IntegrationProvider::tryFrom($this->provider);
    }
}
