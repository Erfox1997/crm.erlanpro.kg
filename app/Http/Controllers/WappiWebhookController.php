<?php

namespace App\Http\Controllers;

use App\Services\Wappi\WappiMessengerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WappiWebhookController extends Controller
{
    public function __construct(
        private WappiMessengerService $wappi,
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->all();

        try {
            $this->wappi->handleWebhookPayload($payload);
        } catch (\Throwable $e) {
            Log::warning('Wappi webhook processing failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return response('OK', 200);
    }
}
