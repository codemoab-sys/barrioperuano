<?php

use App\Exceptions\KitchenIdempotencyConflict;
use App\Services\KitchenInventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.connections.mysql' => array_merge($app['config']->get('database.connections.mysql', []), [
    'host' => getenv('KITCHEN_WORKER_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('KITCHEN_WORKER_DB_PORT') ?: '3306',
    'database' => getenv('KITCHEN_WORKER_DB_DATABASE') ?: 'restaurante_kitchen_test',
    'username' => getenv('KITCHEN_WORKER_DB_USERNAME') ?: 'root',
    'password' => getenv('KITCHEN_WORKER_DB_PASSWORD') ?: '',
    'prefix' => '',
    'strict' => true,
    'engine' => 'InnoDB',
])]);
$connection = config('database.connections.mysql');
if (! is_string($connection['database'] ?? null) || ! str_ends_with($connection['database'], '_test')) {
    echo json_encode(['ok' => false, 'error' => 'safety', 'message' => 'Base de datos no autorizada para el trabajador de concurrencia.'], JSON_THROW_ON_ERROR);
    exit(1);
}
DB::purge('mysql');

$eventKey = (string) ($argv[1] ?? '');
$lines = json_decode((string) ($argv[2] ?? '[]'), true);
$controlStock = ($argv[3] ?? '0') === '1';

try {
    $operation = $app->make(KitchenInventoryService::class)->recordSale($eventKey, is_array($lines) ? $lines : [], $controlStock);
    echo json_encode(['ok' => true, 'operation_id' => $operation->id, 'movements' => $operation->movements->count()], JSON_THROW_ON_ERROR);
} catch (KitchenIdempotencyConflict $exception) {
    echo json_encode(['ok' => false, 'error' => 'conflict', 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => get_class($exception).': '.$exception->getMessage()], JSON_THROW_ON_ERROR);
}
