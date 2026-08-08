<?php

namespace App\Http\Controllers;

use App\Services\Facebook\FacebookMessengerService;
use App\Services\Instagram\InstagramCommentsService;
use App\Services\Instagram\InstagramMessengerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    public function __construct(
        private InstagramMessengerService $instagram,
        private InstagramCommentsService $instagramComments,
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
            $instagramMessages = $this->instagram->handleWebhookPayload($payload);
            $instagramComments = $this->instagramComments->handleWebhookPayload($payload);
            $facebookMessages = $this->facebook->handleWebhookPayload($payload);

            Log::info('Meta webhook processed', [
                'object' => $payload['object'] ?? null,
                'entry_ids' => collect($payload['entry'] ?? [])
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all(),
                'messaging_events' => collect($payload['entry'] ?? [])
                    ->sum(fn ($entry) => is_array($entry['messaging'] ?? null)
                        ? count($entry['messaging'])
                        : 0),
                'change_fields' => collect($payload['entry'] ?? [])
                    ->flatMap(fn ($entry) => collect($entry['changes'] ?? [])->pluck('field'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'processed' => [
                    'instagram_messages' => $instagramMessages,
                    'instagram_comments' => $instagramComments,
                    'facebook_messages' => $facebookMessages,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Meta webhook processing failed', [
                'message' => $e->getMessage(),
                'object' => $payload['object'] ?? null,
            ]);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
