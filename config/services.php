<?php

return [
    'mailgun' => [
        'domain'    => env('MAILGUN_DOMAIN'),
        'secret'    => env('MAILGUN_SECRET'),
        'endpoint'  => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'    => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Infinity Pay — pagamentos
    'infinitypay' => [
        'secret_key'      => env('INFINITYPAY_SECRET_KEY'),
        'webhook_secret'  => env('INFINITYPAY_WEBHOOK_SECRET'),
        'api_url'         => env('INFINITYPAY_API_URL', 'https://api.infinitepay.io/v3'),
    ],

    // Cloudinary — imagens
    'cloudinary' => [
        'cloud_name'    => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'       => env('CLOUDINARY_API_KEY'),
        'api_secret'    => env('CLOUDINARY_API_SECRET'),
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET', 'japa_treinador_secure'),
        'max_file_size' => env('CLOUDINARY_MAX_FILE_SIZE', 5242880),
    ],
];
