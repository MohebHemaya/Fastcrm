<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    */
    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => array_filter([
                'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                'useTLS' => true,
                'host' => env('PUSHER_HOST') ?: null,
                'port' => env('PUSHER_PORT') ?: null,
                'scheme' => env('PUSHER_SCHEME') ?: null,
            ], fn ($val) => $val !== null && $val !== ''),
            'client_options' => [
                'verify' => env('APP_ENV') === 'production',
                'timeout' => 3.0,
                'connect_timeout' => 2.0,
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
