<?php

use App\Http\Controllers\DeliveryController;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

$productId = (int) ($argv[1] ?? 0);
$qty = (int) ($argv[2] ?? 1);
$userId = (int) ($argv[3] ?? 0);
$startAt = (float) ($argv[4] ?? 0);

if ($startAt > 0) {
    while (microtime(true) < $startAt) {
        usleep(1000);
    }
}

$user = User::find($userId);
if ($user === null || $productId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'setup', 'message' => 'Falta el producto o el usuario de prueba.'], JSON_THROW_ON_ERROR);
    exit(1);
}

Auth::setUser($user);

try {
    $request = Request::create('/delivery', 'POST', [
        'client_name' => 'Cliente Concurrencia',
        'client_phone' => '999999999',
        'address' => 'Av. Concurrencia 123',
        'payment_method' => 'transfer',
        'products' => [
            ['id' => $productId, 'qty' => $qty],
        ],
    ]);

    $response = $app->make(DeliveryController::class)->store($request);

    $delivery = Delivery::query()->orderByDesc('id')->first();

    if ($response instanceof RedirectResponse) {
        echo json_encode([
            'ok' => $response->getStatusCode() < 400,
            'status' => $response->getStatusCode(),
            'kind' => 'redirect',
            'delivery_id' => $delivery?->id,
            'order_id' => $delivery?->order_id,
        ], JSON_THROW_ON_ERROR);
    } elseif ($response instanceof Response) {
        $status = $response->getStatusCode();
        echo json_encode(['ok' => $status < 400, 'status' => $status, 'kind' => 'response'], JSON_THROW_ON_ERROR);
    } else {
        echo json_encode(['ok' => false, 'error' => 'unexpected', 'message' => get_debug_type($response)], JSON_THROW_ON_ERROR);
    }
} catch (ValidationException $exception) {
    echo json_encode(['ok' => false, 'error' => 'validation', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['ok' => false, 'error' => 'http', 'status' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => get_class($exception).': '.$exception->getMessage()], JSON_THROW_ON_ERROR);
}
