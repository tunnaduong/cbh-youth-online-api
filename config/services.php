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

  'mailgun' => [
    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    'scheme' => 'https',
  ],

  'postmark' => [
    'token' => env('POSTMARK_TOKEN'),
  ],

  'ses' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
  ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

  'recaptcha' => [
    'site_key' => env('VITE_RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
  ],

  'vapid' => [
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:cbhyouthonline@gmail.com'),
  ],

  'chat_api' => [
    // The Chat with AI feature (AiChatService) uses this key/proxy. Key
    // lives in .env as CYO_AI_API - never commit it or return it from any
    // endpoint. Quiz generation (QuizGenerationService) no longer uses this -
    // see 'gemini' below.
    'key' => env('CYO_AI_API'),
  ],

  'gemini' => [
    // Quiz question generation (QuizGenerationService) calls the Google AI
    // Studio API directly with one of these keys, picked at random each
    // request and rotated through on failure - spreads load/rate limits
    // across all 5 instead of hammering a single key. Keys live in .env as
    // GEMINI_1..GEMINI_5 - never commit them or return them from any
    // endpoint. Missing/blank entries are dropped, so this works fine with
    // fewer than 5 configured.
    'keys' => array_values(array_filter([
      env('GEMINI_1'),
      env('GEMINI_2'),
      env('GEMINI_3'),
      env('GEMINI_4'),
      env('GEMINI_5'),
    ])),
  ],

];
