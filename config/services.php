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

    // ponytail: OTP goes out by mail until an SMS provider is bought.
    // Set OTP_CHANNEL=sms + OTP_SMS_URL to switch it on; no provider SDK needed.
    'otp' => [
        'channel' => env('OTP_CHANNEL', 'mail'),
        'sms' => [
            'url' => env('OTP_SMS_URL'),
            'token' => env('OTP_SMS_TOKEN'),
            'sender' => env('OTP_SMS_SENDER'),
            'template' => env('OTP_SMS_TEMPLATE', 'Markin login code: :code'),
        ],
    ],

    /*
     * ponytail: push is wired but silent until a Firebase project exists.
     * Point FCM_CREDENTIALS at the service-account JSON and flip PUSH_ENABLED.
     */
    'fcm' => [
        'enabled' => (bool) env('PUSH_ENABLED', false),
        'credentials' => env('FCM_CREDENTIALS'),
    ],

    'demo' => [
        'notify' => env('DEMO_NOTIFY_EMAIL'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
