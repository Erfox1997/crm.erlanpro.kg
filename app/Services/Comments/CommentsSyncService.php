<?php

namespace App\Services\Comments;

use App\Models\CompanyIntegration;
use App\Services\Instagram\InstagramCommentsService;

class CommentsSyncService
{
    public function __construct(
        private InstagramCommentsService $comments,
    ) {}

    /**
     * @return array{synced_media: int, synced_comments: int, errors: list<string>}
     */
    public function syncForCompany(int $companyId): array
    {
        $integration = $this->comments->integrationForCompany($companyId);

        if (! $integration) {
            return [
                'synced_media' => 0,
                'synced_comments' => 0,
                'errors' => [__('Подключите Instagram в разделе «Интеграции».')],
            ];
        }

        return $this->comments->syncAll($integration);
    }
}
