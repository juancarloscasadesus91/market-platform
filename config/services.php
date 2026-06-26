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

    'schwab' => [
        'app_key' => env('SCHWAB_APP_KEY'),
        'app_secret' => env('SCHWAB_APP_SECRET'),
        'callback_url' => env('SCHWAB_CALLBACK_URL'),
        'base_url' => env('SCHWAB_API_BASE_URL', 'https://api.schwabapi.com'),
    ],

    'alpaca' => [
        'key' => env('ALPACA_API_KEY', env('APCA_API_KEY_ID')),
        'secret' => env('ALPACA_API_SECRET', env('APCA_API_SECRET_KEY')),
        'paper_base_url' => env('ALPACA_PAPER_BASE_URL', 'https://paper-api.alpaca.markets'),
        'live_base_url' => env('ALPACA_LIVE_BASE_URL', 'https://api.alpaca.markets'),
        'data_base_url' => env('ALPACA_DATA_BASE_URL', 'https://data.alpaca.markets'),
        'market_data_feed' => env('ALPACA_MARKET_DATA_FEED', 'iex'),
        'options_feed' => env('ALPACA_OPTIONS_FEED', 'indicative'),
        'paper' => [
            'key' => env('ALPACA_PAPER_API_KEY', env('ALPACA_API_KEY', env('APCA_API_KEY_ID'))),
            'secret' => env('ALPACA_PAPER_API_SECRET', env('ALPACA_API_SECRET', env('APCA_API_SECRET_KEY'))),
            'base_url' => env('ALPACA_PAPER_BASE_URL', 'https://paper-api.alpaca.markets'),
        ],
        'live' => [
            'key' => env('ALPACA_LIVE_API_KEY'),
            'secret' => env('ALPACA_LIVE_API_SECRET'),
            'base_url' => env('ALPACA_LIVE_BASE_URL', 'https://api.alpaca.markets'),
        ],
    ],

    'schwab_trader' => [
        'app_key' => env('SCHWAB_TRADER_APP_KEY'),
        'app_secret' => env('SCHWAB_TRADER_APP_SECRET'),
        'callback_url' => env('SCHWAB_TRADER_CALLBACK_URL'),
        'base_url' => env('SCHWAB_API_BASE_URL', 'https://api.schwabapi.com'),
    ],

];
