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
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'success_url' => env('STRIPE_SUCCESS_URL', env('APP_URL').'/billing/success'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('APP_URL').'/billing/cancel'),
        'return_url' => env('STRIPE_RETURN_URL', env('APP_URL').'/billing/account'),
    ],

    'cloudpayments' => [
        'public_id' => env('CLOUDPAYMENTS_PUBLIC_ID'),
        'api_secret' => env('CLOUDPAYMENTS_API_SECRET'),
        'base_url' => env('CLOUDPAYMENTS_BASE_URL', 'https://api.cloudpayments.ru'),
    ],

    // Used only to verify provider tokens issued to the mobile apps (Socialite's
    // userFromToken), never for a server-side OAuth redirect flow — so
    // client_secret/redirect are irrelevant and left blank.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect' => env('GOOGLE_REDIRECT_URI', ''),
    ],

    // client_id must match the "aud" claim of the identity token issued to the
    // mobile apps (the app's bundle id for native Sign in with Apple). No
    // private_key is configured since we only verify tokens, never exchange an
    // authorization code for one.
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET', ''),
        'redirect' => env('APPLE_REDIRECT_URI', ''),
    ],

];
