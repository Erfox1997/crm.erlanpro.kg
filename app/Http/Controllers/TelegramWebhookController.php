<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramMessengerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private TelegramMessengerService $telegram,
    ) {}

    public function handle(Request $request, string $secret): Response
    {
        $integration = $this->telegram->findIntegrationByWebhookSecret($secret);

        if (! $integration) {
            return response('Not Found', 404);
        }

        $headerSecret = trim((string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''));
        if ($headerSecret !== '' && $headerSecret !== $secret) {
            return response('Forbidden', 403);
        }

        $payload = $request->all();

        try {
            $this->telegram->handleWebhookPayload($payload, $integration);
        } catch (\Throwable $e) {
            Log::warning('Telegram webhook processing failed', [
                'company_id' => $integration->company_id,
                'message' => $e->getMessage(),
            ]);
        }

        return response('OK', 200);
    }
}
