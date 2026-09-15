<?php

use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$output = '';
$exitCode = 1;

try {
    $exitCode = $kernel->call('migrate', ['--force' => true]);
    $output .= $kernel->output();

    if ($exitCode === 0) {
        $exitCode = $kernel->call('optimize:clear');
        $output .= $kernel->output();
    }
} catch (Throwable $exception) {
    $output .= get_class($exception) . ': ' . $exception->getMessage();
}

@unlink(__FILE__);

header('Content-Type: text/plain; charset=UTF-8');
echo $output;
echo "\nCodigo: {$exitCode}\n";
echo "El archivo se elimino automaticamente.\n";