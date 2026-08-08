<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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

    /**
     * Safe for UI: a bad/rotated APP_KEY must not 500 the integrations page.
     */
    public function hasUsableApiToken(): bool
    {
        try {
            return filled($this->api_token);
        } catch (\Throwable $e) {
            Log::warning('CompanyIntegration api_token decrypt failed', [
                'integration_id' => $this->id,
                'provider' => $this->provider,
                'company_id' => $this->company_id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function safeMetadata(): array
    {
        try {
            $metadata = $this->metadata;

            return is_array($metadata) ? $metadata : [];
        } catch (\Throwable $e) {
            Log::warning('CompanyIntegration metadata read failed', [
                'integration_id' => $this->id,
                'provider' => $this->provider,
                'company_id' => $this->company_id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
