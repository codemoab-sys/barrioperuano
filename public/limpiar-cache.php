<?php

use Illuminate\Contracts\Console\Kernel;
define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$exitCode = $kernel->call('optimize:clear');
$output = $kernel->output();

@unlink(__FILE__);

header('Content-Type: text/plain; charset=UTF-8');
echo "Cache limpiada.\n\n";
echo $output;
echo "\nCodigo: {$exitCode}\n";
echo "Este archivo se elimino automaticamente.\n";
