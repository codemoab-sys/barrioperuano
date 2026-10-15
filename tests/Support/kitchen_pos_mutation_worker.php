<?php

use App\Http\Controllers\PosController;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\JsonResponse;
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

$action = (string) ($argv[1] ?? '');
$targetType = (string) ($argv[2] ?? '');
$targetId = (int) ($argv[3] ?? 0);
$value = (string) ($argv[4] ?? '');
$userId = (int) ($argv[5] ?? 0);
$startAt = (float) ($argv[6] ?? 0);

if ($startAt > 0) {
    while (microtime(true) < $startAt) {
        usleep(1000);
    }
}

$inputs = match ($action) {
    'remove' => [],
    'discount' => ['discount' => $value],
    'price' => ['price' => $value],
    'note' => ['note' => $value],
    'move' => ['target_table_id' => (int) $value],
    default => null,
};

$user = User::find($userId);
$model = $targetType === 'detail' ? OrderDetail::find($targetId) : Order::find($targetId);
if ($user === null || $model === null || $inputs === null) {
    echo json_encode(['ok' => false, 'error' => 'setup', 'message' => 'Falta el modelo, la acción o el usuario de prueba.'], JSON_THROW_ON_ERROR);
    exit(1);
}

Auth::setUser($user);

try {
    $controller = $app->make(PosController::class);
    $httpMethod = $action === 'remove' ? 'DELETE' : 'POST';
    $request = Request::create('/pos/mutation', $httpMethod, $inputs);

    $response = match ($action) {
        'remove' => $controller->removeItem($model),
        'discount' => $controller->applyDiscount($request, $model),
        'price' => $controller->updatePrice($request, $model),
        'note' => $controller->updateNote($request, $model),
        'move' => $controller->moveTable($request, $model),
    };

    if (is_string($response)) {
        echo json_encode(['ok' => true, 'status' => 200, 'kind' => 'html'], JSON_THROW_ON_ERROR);
    } elseif ($response instanceof RedirectResponse) {
        echo json_encode([
            'ok' => $response->getStatusCode() < 400,
            'status' => $response->getStatusCode(),
            'kind' => 'redirect',
            'location' => (string) $response->headers->get('Location'),
        ], JSON_THROW_ON_ERROR);
    } elseif ($response instanceof JsonResponse) {
        $status = $response->getStatusCode();
        echo json_encode([
            'ok' => $status < 400,
            'status' => $status,
            'kind' => 'json',
            'json' => json_decode($response->getContent(), true),
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
} catch (ModelNotFoundException $exception) {
    echo json_encode(['ok' => false, 'error' => 'not_found', 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => get_class($exception).': '.$exception->getMessage()], JSON_THROW_ON_ERROR);
}
