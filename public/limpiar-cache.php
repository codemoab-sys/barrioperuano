<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$request = Request::capture();

$status = $kernel->handle($request);

ob_start();
$exitCode = Artisan::call('optimize:clear');
$output = Artisan::output();
ob_end_clean();

$kernel->terminate($request, $status);

@unlink(__FILE__);

header('Content-Type: text/plain; charset=UTF-8');
echo "Cache limpiada.\n\n";
echo $output;
echo "\nCodigo: {$exitCode}\n";
echo "Este archivo se elimino automaticamente.\n";
