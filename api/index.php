<?php

/**
 * Vercel PHP Serverless Entry Point — Japa Treinador Backend
 *
 * Na Vercel, apenas /tmp é gravável. O AppServiceProvider
 * redireciona o storage para /tmp quando VERCEL=1.
 */

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

(require_once __DIR__ . '/../bootstrap/app.php')
    ->handleRequest(Request::capture());
