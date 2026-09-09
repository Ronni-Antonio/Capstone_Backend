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



    'prophet' => [
        'url' => env('PROPHET_API_URL', 'http://127.0.0.1:5001'),
    ],

    'iot' => [
        'controller_1_key' => env('IOT_CONTROLLER_1_KEY'),
        'controller_2_key' => env('IOT_CONTROLLER_2_KEY'),
        'controller_1_smart_bin_id' => env('IOT_CONTROLLER_1_SMART_BIN_ID', 1),
        'deposit_session_timeout_seconds' => env('IOT_DEPOSIT_SESSION_TIMEOUT_SECONDS', 180),
        'deposit_claim_timeout_seconds' => env('IOT_DEPOSIT_CLAIM_TIMEOUT_SECONDS', 300),
    ],

    'cnn' => [
        'url' => env('CNN_API_URL', 'http://127.0.0.1:5002'),
        'min_confidence' => env('CNN_MIN_CONFIDENCE', 0.70),
        'timeout_seconds' => env('CNN_TIMEOUT_SECONDS', 30),
        'max_image_bytes' => env('CNN_MAX_IMAGE_BYTES', 5242880),
        'model_name' => env('CNN_MODEL_NAME', 'Recyclable Classification CNN'),
        'model_version' => env('CNN_MODEL_VERSION', '1.0.0'),
    ],

];
