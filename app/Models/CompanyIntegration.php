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
        'external_account_id',
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

    public static function resolveExternalAccountId(string $provider, array $metadata): string
    {
        return match ($provider) {
            IntegrationProvider::Facebook->value => (string) ($metadata['page_id'] ?? ''),
            IntegrationProvider::Instagram->value => (string) (
                $metadata['instagram_user_id']
                    ?? $metadata['page_id']
                    ?? ''
            ),
            default => '',
        };
    }

    /**
     * Upsert Meta (IG/FB) connection by external account, or single-row providers by company+provider.
     *
     * @param  array{api_token?: string, metadata?: array<string, mixed>}  $attributes
     */
    public static function upsertForCompany(
        int $companyId,
        string $provider,
        array $attributes,
    ): self {
        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];
        $externalAccountId = self::resolveExternalAccountId($provider, $metadata);

        return self::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'provider' => $provider,
                'external_account_id' => $externalAccountId,
            ],
            [
                'api_token' => $attributes['api_token'] ?? null,
                'metadata' => $metadata,
            ],
        );
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
