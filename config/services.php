<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
    ],

    'meta' => [
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', 'crm-ulan-meta-webhook'),
        'oauth_provider' => env('META_OAUTH_PROVIDER', 'facebook'),
        'oauth_scopes' => env(
            'META_OAUTH_SCOPES',
            'public_profile,pages_show_list,pages_read_engagement,pages_manage_metadata,pages_messaging,instagram_basic,instagram_manage_messages',
        ),
        'oauth_scopes_instagram' => env(
            'META_OAUTH_SCOPES_INSTAGRAM',
            'public_profile,pages_show_list,pages_read_engagement,pages_manage_metadata,pages_messaging,instagram_basic,instagram_manage_messages',
        ),
        'oauth_scopes_facebook' => env(
            'META_OAUTH_SCOPES_FACEBOOK',
            'public_profile,pages_show_list,pages_read_engagement,pages_manage_metadata,pages_messaging',
        ),
        'oauth_redirect_uri' => env('META_OAUTH_REDIRECT_URI'),
    ],

    'wappi' => [
        'base_url' => env('WAPPI_BASE_URL', 'https://wappi.pro'),
        'timeout' => (int) env('WAPPI_TIMEOUT', 60),
    ],

];
