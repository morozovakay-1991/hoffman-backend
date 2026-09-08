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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'database_url' => env('FIREBASE_DATABASE_URL'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'success_url' => env('STRIPE_SUCCESS_URL', env('APP_URL').'/billing/success'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('APP_URL').'/billing/cancel'),
        'return_url' => env('STRIPE_RETURN_URL', env('APP_URL').'/billing/account'),
    ],

    'cloudpayments' => [
        'public_id' => env('CLOUDPAYMENTS_PUBLIC_ID'),
        'api_secret' => env('CLOUDPAYMENTS_API_SECRET'),
        'base_url' => env('CLOUDPAYMENTS_BASE_URL', 'https://api.cloudpayments.ru'),
    ],

];
