<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Bepaal of de applicatie in onderhoudsmodus staat...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Registreer de Composer auto-loader...
require __DIR__.'/../vendor/autoload.php';

// Start Laravel en handel het verzoek af...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
