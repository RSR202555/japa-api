<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| PRODUÇÃO: ajuste o caminho abaixo para onde os arquivos do backend
| estão no servidor. Exemplo real na HostGator:
| /home/riansa90/japa-backend
|--------------------------------------------------------------------------
*/
$backendPath = dirname(__DIR__);

if (file_exists($maintenance = $backendPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $backendPath.'/vendor/autoload.php';

(require_once $backendPath.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
