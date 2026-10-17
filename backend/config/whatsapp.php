<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Provider
    |--------------------------------------------------------------------------
    | Supported: "fake", "cloud", "ultramsg"
    */
    'provider' => env('WHATSAPP_PROVIDER', 'ultramsg'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Verification Token
    |--------------------------------------------------------------------------
    */
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'fastcrm_zimpact_2026'),

    /*
    |--------------------------------------------------------------------------
    | Meta Cloud API credentials
    |--------------------------------------------------------------------------
    */
    'cloud_api' => [
        'token' => env('WHATSAPP_TOKEN', ''),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'app_secret' => env('WHATSAPP_APP_SECRET', ''),
        'version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UltraMsg API credentials (https://ultramsg.com)
    |--------------------------------------------------------------------------
    */
    'ultramsg' => [
        'base_url' => env('ULTRAMSG_BASE_URL', 'https://api.ultramsg.com'),
        'token' => env('ULTRAMSG_TOKEN', ''),
        'instance_id' => env('ULTRAMSG_INSTANCE_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sending Rate Limit (messages per minute)
    |--------------------------------------------------------------------------
    */
    'send_rate_per_minute' => (int) env('WHATSAPP_SEND_RATE_PER_MINUTE', 30),

    /*
    |--------------------------------------------------------------------------
    | Anti-Ban Protection (Jitter & Intelligent Pacing)
    |--------------------------------------------------------------------------
    | Staggers bulk dispatches with humanized randomized delays to prevent
    | WhatsApp account bans and sudden traffic spikes.
    */
    'anti_ban' => [
        'enabled' => (bool) env('WHATSAPP_ANTI_BAN_ENABLED', true),
        'min_delay_seconds' => (int) env('WHATSAPP_ANTI_BAN_MIN_DELAY', 2),
        'max_delay_seconds' => (int) env('WHATSAPP_ANTI_BAN_MAX_DELAY', 5),
        'batch_size' => (int) env('WHATSAPP_ANTI_BAN_BATCH_SIZE', 20),
        'batch_pause_seconds' => (int) env('WHATSAPP_ANTI_BAN_BATCH_PAUSE', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invite Batch Size
    |--------------------------------------------------------------------------
    */
    'invite_batch_size' => (int) env('WHATSAPP_INVITE_BATCH_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Free-form window in hours (after last inbound message)
    |--------------------------------------------------------------------------
    */
    'free_form_window_hours' => (int) env('WHATSAPP_FREE_FORM_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | No-response timeout in days (after which driver → no_response)
    |--------------------------------------------------------------------------
    */
    'no_response_days' => (int) env('WHATSAPP_NO_RESPONSE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Invitation template name (registered in Meta)
    |--------------------------------------------------------------------------
    */
    'invite_template_name' => env('WHATSAPP_INVITE_TEMPLATE_NAME', 'driver_invitation'),

    /*
    |--------------------------------------------------------------------------
    | Opt-out keyword lists
    |--------------------------------------------------------------------------
    | Standard keywords can match the whole message, the first word, or start
    | with a multi-word phrase.
    | Exact-only keywords are everyday ambiguous words that ONLY trigger opt-out
    | if they constitute the entire message.
    */
    'optout_keywords' => [
        'ايقاف', 'إيقاف', 'stop', 'الغاء', 'إلغاء', 'unsubscribe',
        'اوقف', 'لا اريد', 'لا أريد',
    ],

    'optout_exact_only_keywords' => [
        'وقف', 'انهاء', 'إنهاء', 'قف', 'خروج',
    ],
];
