<?php

namespace App\Services\Messenger;

use App\Enums\IntegrationProvider;
use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;

class MessengerSyncService
{
    public function __construct(
        private InstagramMessengerService $instagram,
        private FacebookMessengerService $facebook,
    ) {}

    /**
     * @return array{synced: int, errors: list<string>, company_id: int, company_name: ?string}
     */
    public function syncForCompany(int $companyId, int $days = 1): array
    {
        $company = Company::query()->find($companyId);
        $instagramIntegration = $this->instagram->integrationForCompany($companyId);
        $facebookIntegration = $this->facebook->integrationForCompany($companyId);

        if (! $instagramIntegration && ! $facebookIntegration) {
            return [
                'synced' => 0,
                'errors' => [__('Подключите Instagram или Facebook в разделе «Интеграции».')],
                'company_id' => $companyId,
                'company_name' => $company?->name,
            ];
        }

        $errors = [];
        $synced = 0;

        if ($instagramIntegration) {
            if (! ($instagramIntegration->metadata['instagram_user_id'] ?? null)) {
                $instagramIntegration = $this->instagram->refreshIntegrationMetadata($instagramIntegration);
            }

            $result = $this->instagram->syncConversations($instagramIntegration, $days);
            $synced += $result['synced'];
            $errors = array_merge($errors, $result['errors']);
        }

        if ($facebookIntegration) {
            if (! ($facebookIntegration->metadata['page_id'] ?? null)) {
                $facebookIntegration = $this->facebook->refreshIntegrationMetadata($facebookIntegration);
            }

            $result = $this->facebook->syncConversations($facebookIntegration, $days);
            $synced += $result['synced'];
            $errors = array_merge($errors, $result['errors']);
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
            'company_id' => $companyId,
            'company_name' => $company?->name,
        ];
    }

    /**
     * @return list<int>
     */
    public function companyIdsWithMessengerIntegrations(): array
    {
        return CompanyIntegration::query()
            ->whereIn('provider', [
                IntegrationProvider::Instagram->value,
                IntegrationProvider::Facebook->value,
            ])
            ->whereNotNull('api_token')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: ?string}>
     */
    public function companiesWithMessengerIntegrations(): array
    {
        $companyIds = $this->companyIdsWithMessengerIntegrations();

        if ($companyIds === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', $companyIds)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
            ])
            ->all();
    }
}
