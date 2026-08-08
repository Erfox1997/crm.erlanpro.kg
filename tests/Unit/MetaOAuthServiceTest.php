<?php

namespace Tests\Unit;

use App\Services\Meta\MetaOAuthService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaOAuthServiceTest extends TestCase
{
    public function test_it_subscribes_a_facebook_page_to_message_webhooks(): void
    {
        config()->set('services.meta.graph_version', 'v25.0');

        Http::fake([
            'https://graph.facebook.com/v25.0/1182102338322617/subscribed_apps' => Http::response([
                'success' => true,
            ]),
        ]);

        app(MetaOAuthService::class)->subscribePageWebhooks(
            '1182102338322617',
            'page-access-token',
        );

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://graph.facebook.com/v25.0/1182102338322617/subscribed_apps'
                && $request->hasHeader('Authorization', 'Bearer page-access-token')
                && $request['subscribed_fields'] === 'messages';
        });
    }
}
