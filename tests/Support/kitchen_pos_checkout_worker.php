<?php

use App\Models\Order;
use App\Models\User;
use App\Services\PosCheckoutService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

$orderId = (int) ($argv[1] ?? 0);
$userId = (int) ($argv[2] ?? 0);
$startAt = (float) ($argv[3] ?? 0);

if ($startAt > 0) {
    while (microtime(true) < $startAt) {
        usleep(1000);
    }
}

$user = User::find($userId);
$order = Order::find($orderId);
if ($user === null || $order === null) {
    echo json_encode(['ok' => false, 'error' => 'setup', 'message' => 'Falta la orden o el usuario de prueba.'], JSON_THROW_ON_ERROR);
    exit(1);
}

Auth::setUser($user);

try {
    $completed = $app->make(PosCheckoutService::class)->completeCheckout($order, [
        'payment_method' => 'cash',
        'received' => (float) $order->total,
        'change' => 0.0,
        'client_name' => 'Público',
        'document_type' => 'Ticket',
    ]);

    echo json_encode(['ok' => true, 'order_id' => $completed->id, 'status' => $completed->status], JSON_THROW_ON_ERROR);
} catch (ValidationException $exception) {
    echo json_encode(['ok' => false, 'error' => 'validation', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => get_class($exception).': '.$exception->getMessage()], JSON_THROW_ON_ERROR);
}
