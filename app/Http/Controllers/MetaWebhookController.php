<?php

namespace App\Http\Controllers;

use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramMessengerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    public function __construct(
        private InstagramMessengerService $instagram,
        private FacebookMessengerService $facebook,
    ) {}

    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        $expected = (string) config('services.meta.webhook_verify_token');

        if ($mode === 'subscribe' && $token !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        abort(403);
    }

    public function handle(Request $request): Response
    {
        $payload = $request->all();

        try {
            $this->instagram->handleWebhookPayload($payload);
            $this->facebook->handleWebhookPayload($payload);
        } catch (\Throwable $e) {
            Log::warning('Meta webhook processing failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
