<?php

// InfinityFree serves htdocs/ directly, so the public files sit here and the
// rest of Laravel sits in core/ (closed to the web by core/.htaccess).

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/core/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/core/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/core/bootstrap/app.php';

// public_path() is this folder, not core/public: Vite's build/, Filament's css/ and js/.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
