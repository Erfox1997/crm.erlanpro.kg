<?php

namespace App\Services\Broadcast;

use App\Enums\IntegrationProvider;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\MessengerConversation;
use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use App\Services\Telegram\TelegramMessengerService;
use App\Services\Wappi\WappiMessengerService;
use Illuminate\Http\Client\RequestException;
use Throwable;

class BroadcastSenderService
{
    public function __construct(
        private WappiMessengerService $wappi,
        private TelegramMessengerService $telegram,
        private InstagramMessengerService $instagram,
        private FacebookMessengerService $facebook,
    ) {}

    public function sendRecipient(BroadcastRecipient $recipient): void
    {
        $campaign = $recipient->campaign;
        if (! $campaign) {
            return;
        }

        if ($campaign->status === BroadcastCampaign::STATUS_CANCELLED) {
            $this->markSkipped($recipient, __('Рассылка отменена.'));
            $campaign->markCompletedIfDone();

            return;
        }

        if ($recipient->status !== BroadcastRecipient::STATUS_PENDING) {
            return;
        }

        if (! $recipient->messenger_conversation_id) {
            $this->markSkipped($recipient, __('Нет диалога в выбранном канале.'));
            $this->bumpCampaignRunning($campaign);
            $campaign->markCompletedIfDone();

            return;
        }

        $conversation = MessengerConversation::query()->find($recipient->messenger_conversation_id);
        if (! $conversation || $conversation->company_id !== $campaign->company_id) {
            $this->markFailed($recipient, __('Диалог не найден.'));
            $this->bumpCampaignRunning($campaign);
            $campaign->markCompletedIfDone();

            return;
        }

        try {
            $this->sendViaChannel($campaign, $conversation);
            $recipient->forceFill([
                'status' => BroadcastRecipient::STATUS_SENT,
                'error_message' => null,
                'sent_at' => now(),
            ])->save();
            $conversation->update(['last_message_at' => now()]);
        } catch (RequestException $e) {
            $this->markFailed($recipient, $e->getMessage());
        } catch (Throwable $e) {
            $this->markFailed($recipient, $e->getMessage());
        }

        $this->bumpCampaignRunning($campaign);
        $campaign->refreshProgressCounters();
        $campaign->markCompletedIfDone();
    }

    protected function sendViaChannel(BroadcastCampaign $campaign, MessengerConversation $conversation): void
    {
        $companyId = (int) $campaign->company_id;
        $body = (string) $campaign->body;

        match ($campaign->channel) {
            IntegrationProvider::Wappi->value => $this->sendWappi($companyId, $conversation, $body),
            IntegrationProvider::Telegram->value => $this->sendTelegram($companyId, $conversation, $body),
            IntegrationProvider::Instagram->value => $this->sendInstagram($companyId, $conversation, $body),
            IntegrationProvider::Facebook->value => $this->sendFacebook($companyId, $conversation, $body),
            default => throw new \RuntimeException(__('Канал не поддерживается.')),
        };
    }

    protected function sendWappi(int $companyId, MessengerConversation $conversation, string $body): void
    {
        $integration = $this->wappi->integrationForCompany($companyId);
        if (! $integration) {
            throw new \RuntimeException(__('WhatsApp (Wappi) не подключён.'));
        }

        $this->wappi->sendMessage($integration, $conversation, $body);
    }

    protected function sendTelegram(int $companyId, MessengerConversation $conversation, string $body): void
    {
        $integration = $this->telegram->integrationForCompany($companyId);
        if (! $integration) {
            throw new \RuntimeException(__('Telegram не подключён.'));
        }

        $this->telegram->sendMessage($integration, $conversation, $body);
    }

    protected function sendInstagram(int $companyId, MessengerConversation $conversation, string $body): void
    {
        $integration = $this->instagram->integrationForCompany($companyId);
        if (! $integration) {
            throw new \RuntimeException(__('Instagram не подключён.'));
        }

        $this->instagram->sendMessage($integration, $conversation, $body);
    }

    protected function sendFacebook(int $companyId, MessengerConversation $conversation, string $body): void
    {
        $integration = $this->facebook->integrationForCompany($companyId);
        if (! $integration) {
            throw new \RuntimeException(__('Facebook не подключён.'));
        }

        $this->facebook->sendMessage($integration, $conversation, $body);
    }

    protected function markFailed(BroadcastRecipient $recipient, string $message): void
    {
        $recipient->forceFill([
            'status' => BroadcastRecipient::STATUS_FAILED,
            'error_message' => mb_substr($message, 0, 1000),
        ])->save();
    }

    protected function markSkipped(BroadcastRecipient $recipient, string $message): void
    {
        $recipient->forceFill([
            'status' => BroadcastRecipient::STATUS_SKIPPED,
            'error_message' => mb_substr($message, 0, 1000),
        ])->save();
    }

    protected function bumpCampaignRunning(BroadcastCampaign $campaign): void
    {
        if (in_array($campaign->status, [
            BroadcastCampaign::STATUS_CANCELLED,
            BroadcastCampaign::STATUS_COMPLETED,
            BroadcastCampaign::STATUS_FAILED,
        ], true)) {
            return;
        }

        if ($campaign->status !== BroadcastCampaign::STATUS_RUNNING) {
            $campaign->forceFill([
                'status' => BroadcastCampaign::STATUS_RUNNING,
                'started_at' => $campaign->started_at ?? now(),
            ])->save();
        }
    }
}
