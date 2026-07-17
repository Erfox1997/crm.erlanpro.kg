<?php

namespace App\Http\Controllers;

use App\Services\Telegram\SupportTelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramSupportWebhookController extends Controller
{
    public function __construct(
        private SupportTelegramBotService $supportBot,
    ) {}

    public function handle(Request $request, string $secret): Response
    {
        $expected = (string) config('services.telegram.support_webhook_secret', '');
        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        $headerSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if ($expected !== '' && $headerSecret !== '' && ! hash_equals($expected, $headerSecret)) {
            abort(403);
        }

        $payload = $request->all();
        if (is_array($payload)) {
            $this->supportBot->handleWebhookPayload($payload);
        }

        return response('ok');
    }
}
