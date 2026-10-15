<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class KitchenPosCheckoutConcurrencyTest extends TestCase
{
    private const TEST_DATABASE = 'restaurante_kitchen_test';

    private const CONNECTION = 'kitchen_test';

    private PDO $server;

    protected function setUp(): void
    {
        parent::setUp();

        $flag = (string) getenv('RUN_KITCHEN_CONCURRENCY_TESTS');
        if (! in_array(strtolower($flag), ['1', 'true', 'yes'], true)) {
            $this->markTestSkipped('Concurrencia POS no ejecutada: requiere RUN_KITCHEN_CONCURRENCY_TESTS=true apuntando a la base MySQL de pruebas autorizada restaurante_kitchen_test en el servidor local.');
        }

        $this->server = $this->connectServer();
        $this->recreateTestDatabase();

        config(['database.connections.'.self::CONNECTION => $this->connectionConfig()]);
        DB::purge(self::CONNECTION);

        $exitCode = Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true]);
        $this->assertSame(0, $exitCode, 'Fallo al migrar la base de concurrencia: '.Artisan::output());
    }

    public function test_concurrent_checkouts_of_the_same_order_record_kitchen_consumption_only_once(): void
    {
        $fixture = $this->seedCheckoutFixture(2.0, 1);
        $order = $fixture['orders'][0];

        $results = $this->runCheckoutWorkers([$order->id, $order->id], $fixture['user']->id);

        $successful = array_values(array_filter($results, fn (array $result): bool => (bool) ($result['payload']['ok'] ?? false)));
        $this->assertNotEmpty($successful, 'Ningún proceso terminó correctamente: '.json_encode($results));

        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_operations', ['event_key' => 'sale:order:'.$order->id]));
        $this->assertSame(1, $this->countRowsWhereIn('bape_kitchen_order_consumptions', 'order_id', [$order->id]));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_movements', ['kitchen_supply_id' => $fixture['supply']->id]));
        $this->assertSame('0.0000', $this->stock($fixture['supply']->id));
        $this->assertSame('completed', $this->orderStatus($order->id));
    }

    public function test_concurrent_checkouts_never_consume_more_kitchen_stock_than_available(): void
    {
        $fixture = $this->seedCheckoutFixture(2.0, 2);
        [$orderA, $orderB] = $fixture['orders'];

        $results = $this->runCheckoutWorkers([$orderA->id, $orderB->id], $fixture['user']->id);

        $successful = array_values(array_filter($results, fn (array $result): bool => (bool) ($result['payload']['ok'] ?? false)));
        $rejected = array_values(array_filter($results, fn (array $result): bool => ! ($result['payload']['ok'] ?? false)));

        $this->assertCount(1, $successful, 'Exactamente un checkout debe ganar la carrera: '.json_encode($results));
        $this->assertCount(1, $rejected, 'Exactamente un checkout debe abortar: '.json_encode($results));

        $failure = $rejected[0]['payload'];
        $this->assertSame('validation', $failure['error'] ?? null, json_encode($results));
        $this->assertArrayHasKey('stock', $failure['errors'] ?? [], json_encode($results));

        $this->assertSame(1, $this->countRowsWhereIn('bape_kitchen_inventory_operations', 'event_key', [
            'sale:order:'.$orderA->id,
            'sale:order:'.$orderB->id,
        ]));
        $this->assertSame(1, $this->countRowsWhereIn('bape_kitchen_order_consumptions', 'order_id', [$orderA->id, $orderB->id]));
        $this->assertSame('0.0000', $this->stock($fixture['supply']->id));

        $statuses = DB::connection(self::CONNECTION)
            ->table('bape_orders')
            ->whereIn('id', [$orderA->id, $orderB->id])
            ->pluck('status')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['completed', 'pending'], $statuses, json_encode($results));
    }

    public function test_parallel_stale_double_checkout_with_control_disabled_reduces_commercial_stock_only_once(): void
    {
        $fixture = $this->seedCheckoutFixture(2.0, 1, enableFlag: false, productStock: 100.0);
        $order = $fixture['orders'][0];

        $results = $this->runCheckoutWorkers([$order->id, $order->id], $fixture['user']->id);

        [$successful, $rejected] = $this->splitCheckoutResults($results);
        $this->assertCount(1, $successful, 'Exactamente un checkout debe completarse: '.json_encode($results));
        $this->assertCount(1, $rejected, 'Exactamente un checkout debe abortar: '.json_encode($results));
        $this->assertCheckoutRejectedAsClosed($rejected[0], $results);

        $this->assertSame('99.0000', $this->productStock($fixture['product']->id));
        $this->assertSame(1, $this->commercialLogCount($order->id));
        $this->assertSame('completed', $this->orderStatus($order->id));
        $this->assertSame(0, $this->countRowsWhereIn('bape_kitchen_inventory_operations', 'event_key', ['sale:order:'.$order->id]));
    }

    public function test_parallel_stale_double_checkout_with_control_enabled_reduces_stock_and_consumes_kitchen_only_once(): void
    {
        $fixture = $this->seedCheckoutFixture(2.0, 1, enableFlag: true, productStock: 100.0);
        $order = $fixture['orders'][0];

        $results = $this->runCheckoutWorkers([$order->id, $order->id], $fixture['user']->id);

        [$successful, $rejected] = $this->splitCheckoutResults($results);
        $this->assertCount(1, $successful, 'Exactamente un checkout debe completarse: '.json_encode($results));
        $this->assertCount(1, $rejected, 'Exactamente un checkout debe abortar: '.json_encode($results));
        $this->assertCheckoutRejectedAsClosed($rejected[0], $results);

        $this->assertSame('99.0000', $this->productStock($fixture['product']->id));
        $this->assertSame(1, $this->commercialLogCount($order->id));
        $this->assertSame('completed', $this->orderStatus($order->id));

        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_operations', ['event_key' => 'sale:order:'.$order->id]));
        $this->assertSame(1, $this->countRowsWhereIn('bape_kitchen_order_consumptions', 'order_id', [$order->id]));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_movements', ['kitchen_supply_id' => $fixture['supply']->id]));
        $this->assertSame('0.0000', $this->stock($fixture['supply']->id));
    }

    public function test_update_quantity_never_modifies_an_order_already_completed_by_checkout(): void
    {
        $fixture = $this->seedCheckoutFixture(6.0, 1, enableFlag: true, productStock: 100.0);
        $order = $fixture['orders'][0];
        $detailId = (int) DB::connection(self::CONNECTION)
            ->table('bape_order_details')
            ->where('order_id', $order->id)
            ->value('id');

        $results = $this->runCheckoutAndQuantityWorkers($order->id, $detailId, $fixture['user']->id);

        $this->assertTrue(
            (bool) ($results['checkout']['payload']['ok'] ?? false),
            'El checkout con stock suficiente debe completarse: '.json_encode($results)
        );

        $update = $results['update']['payload'];
        $updateBlocked = ($update['error'] ?? null) === 'http'
            && (int) ($update['status'] ?? 0) === 403;
        $updateClosed = (int) ($update['status'] ?? 0) === 422
            && str_contains((string) ($update['json']['error'] ?? ''), 'Orden cerrada');
        $this->assertTrue(
            ($update['ok'] ?? false) === true || $updateBlocked || $updateClosed,
            'Resultado inesperado del update concurrente (sólo 200, 403 u "Orden cerrada"): '.json_encode($results)
        );

        $finalQuantity = (int) DB::connection(self::CONNECTION)
            ->table('bape_order_details')
            ->where('order_id', $order->id)
            ->sum('quantity');
        $soldQuantity = (float) DB::connection(self::CONNECTION)
            ->table('bape_kitchen_order_consumptions')
            ->where('order_id', $order->id)
            ->sum('sold_quantity');

        $this->assertSame('completed', $this->orderStatus($order->id));
        $this->assertSame(number_format(100 - $finalQuantity, 4, '.', ''), $this->productStock($fixture['product']->id));
        $this->assertSame(number_format(6 - 2 * $finalQuantity, 4, '.', ''), $this->stock($fixture['supply']->id));
        $this->assertSame((float) (10 * $finalQuantity), (float) DB::connection(self::CONNECTION)->table('bape_orders')->where('id', $order->id)->value('total'));
        $this->assertSame((float) $finalQuantity, $soldQuantity, 'El consumo de cocina debe coincidir con las cantidades finales de la orden.');
        $this->assertSame(1, $this->commercialLogCount($order->id));
    }

    public function test_remove_item_race_with_checkout_never_leaves_partial_or_repeated_effects(): void
    {
        $fixture = $this->seedCheckoutFixture(2.0, 1, enableFlag: true, productStock: 100.0);
        $order = $fixture['orders'][0];
        $detailId = (int) DB::connection(self::CONNECTION)
            ->table('bape_order_details')
            ->where('order_id', $order->id)
            ->value('id');

        $results = $this->runCheckoutAndMutationWorkers($order->id, $fixture['user']->id, 'remove', 'detail', $detailId);

        $exists = DB::connection(self::CONNECTION)->table('bape_orders')->where('id', $order->id)->exists();
        $mutation = $results['mutation']['payload'];

        if (! $exists) {
            $this->assertTrue(
                (bool) ($mutation['ok'] ?? false),
                'Si la orden quedó vacía y fue eliminada, el remove debió completarse: '.json_encode($results)
            );
            $this->assertSame('100.0000', $this->productStock($fixture['product']->id));
            $this->assertSame(0, $this->commercialLogCount($order->id));
            $this->assertSame(0, $this->countRowsWhereIn('bape_kitchen_inventory_operations', 'event_key', ['sale:order:'.$order->id]));
            $this->assertSame(0, $this->countRowsWhereIn('bape_kitchen_order_consumptions', 'order_id', [$order->id]));

            return;
        }

        $this->assertSame('completed', $this->orderStatus($order->id));
        $removeBlocked = (($mutation['error'] ?? null) === 'http' && (int) ($mutation['status'] ?? 0) === 403)
            || ((int) ($mutation['status'] ?? 0) === 422 && str_contains((string) ($mutation['json']['error'] ?? ''), 'Orden cerrada'));
        $this->assertTrue(
            $removeBlocked,
            'El remove concurrente debe rechazarse sobre la orden completada: '.json_encode($results)
        );

        $remaining = (int) DB::connection(self::CONNECTION)
            ->table('bape_order_details')
            ->where('order_id', $order->id)
            ->count();
        $this->assertSame(1, $remaining, 'El detalle histórico no puede eliminarse tras el checkout: '.json_encode($results));
        $this->assertSame('99.0000', $this->productStock($fixture['product']->id));
        $this->assertSame(1, $this->commercialLogCount($order->id));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_operations', ['event_key' => 'sale:order:'.$order->id]));
        $this->assertSame(1, $this->countRowsWhereIn('bape_kitchen_order_consumptions', 'order_id', [$order->id]));
    }

    public function test_discount_race_with_checkout_applies_at_most_once_and_never_retroactively(): void
    {
        $fixture = $this->seedCheckoutFixture(2.0, 1, enableFlag: true, productStock: 100.0);
        $order = $fixture['orders'][0];

        $results = $this->runCheckoutAndMutationWorkers($order->id, $fixture['user']->id, 'discount', 'order', $order->id, '2');

        $this->assertTrue(
            (bool) ($results['checkout']['payload']['ok'] ?? false),
            'El checkout debe completarse: '.json_encode($results)
        );
        $this->assertSame('completed', $this->orderStatus($order->id));

        $discount = (float) DB::connection(self::CONNECTION)->table('bape_orders')->where('id', $order->id)->value('discount');
        $total = (float) DB::connection(self::CONNECTION)->table('bape_orders')->where('id', $order->id)->value('total');
        $mutation = $results['mutation']['payload'];

        $appliedBefore = $discount === 2.0 && $total === 8.0;
        $notApplied = $discount === 0.0 && $total === 10.0;
        $this->assertTrue(
            $appliedBefore || $notApplied,
            'El descuento debe aplicarse antes del checkout o no aplicarse nunca de forma retroactiva: '.json_encode($results)
        );

        $mutationOk = (bool) ($mutation['ok'] ?? false);
        if ($appliedBefore) {
            $this->assertTrue(
                $mutationOk,
                'Si el descuento quedó aplicado, su operación debió tener éxito: '.json_encode($results)
            );
        } else {
            $mutationBlocked = (($mutation['error'] ?? null) === 'http' && (int) ($mutation['status'] ?? 0) === 403)
                || ((int) ($mutation['status'] ?? 0) === 422 && str_contains((string) ($mutation['json']['error'] ?? ''), 'Orden cerrada'));
            $this->assertFalse(
                $mutationOk,
                'El descuento no puede aplicarse sobre la orden completada: '.json_encode($results)
            );
            $this->assertTrue(
                $mutationBlocked,
                'Resultado inesperado del descuento concurrente: '.json_encode($results)
            );
        }

        $this->assertSame('99.0000', $this->productStock($fixture['product']->id));
        $this->assertSame(1, $this->commercialLogCount($order->id));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_operations', ['event_key' => 'sale:order:'.$order->id]));
    }

    public function test_delivery_store_racing_table_checkout_with_limited_supply_consumes_exactly_once(): void
    {
        $fixture = $this->seedCheckoutFixture(2.0, 1, enableFlag: true, productStock: 100.0);
        $order = $fixture['orders'][0];

        $results = $this->runCheckoutAndDeliveryStoreWorkers($order->id, $fixture['product']->id, $fixture['user']->id);

        $checkoutOk = (bool) ($results['checkout']['payload']['ok'] ?? false);
        $storeOk = (bool) ($results['delivery']['payload']['ok'] ?? false);

        $this->assertNotSame(
            $checkoutOk,
            $storeOk,
            'Exactamente un ganador debe consumir el insumo limitado: '.json_encode($results)
        );

        if ($checkoutOk) {
            $this->assertSame('completed', $this->orderStatus($order->id));
            $this->assertSame(0, $this->countRows('bape_deliveries', []));
        } else {
            $this->assertSame('pending', $this->orderStatus($order->id));
            $this->assertSame(1, $this->countRows('bape_deliveries', []));
            $failure = $results['checkout']['payload'];
            $this->assertSame('validation', $failure['error'] ?? null, json_encode($results));
            $this->assertArrayHasKey('stock', $failure['errors'] ?? [], json_encode($results));
        }

        $this->assertSame('0.0000', $this->stock($fixture['supply']->id));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_operations', ['operation_type' => 'sale']));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_movements', ['type' => 'sale']));
        $this->assertSame('99.0000', $this->productStock($fixture['product']->id));
    }

    public function test_cancel_racing_delivery_checkout_never_mixes_states_or_double_restores(): void
    {
        $fixture = $this->seedCheckoutFixture(2.0, 1, enableFlag: true, productStock: 100.0);

        $store = $this->runDeliveryStoreWorker($fixture['product']->id, $fixture['user']->id);
        $this->assertTrue(
            (bool) ($store['payload']['ok'] ?? false),
            'La creación del delivery debe consumir el insumo: '.json_encode($store)
        );
        $deliveryId = (int) ($store['payload']['delivery_id'] ?? 0);
        $this->assertGreaterThan(0, $deliveryId, json_encode($store));
        $this->assertSame('0.0000', $this->stock($fixture['supply']->id));

        $results = $this->runCancelAndCheckoutWorkers($deliveryId, $fixture['user']->id);

        $deliveryRow = DB::connection(self::CONNECTION)->table('bape_deliveries')->where('id', $deliveryId)->first();
        $orderRow = DB::connection(self::CONNECTION)->table('bape_orders')->where('id', $deliveryRow->order_id)->first();

        $this->assertFalse(
            $deliveryRow->status === 'cancelled' && $orderRow->status === 'completed',
            'Una cancelación nunca puede ser resucitada por un checkout posterior: '.json_encode($results)
        );
        $this->assertFalse(
            $deliveryRow->status === 'delivered' && $orderRow->status === 'cancelled',
            'Estados contradictorios entre delivery y orden: '.json_encode($results)
        );

        if ($deliveryRow->status === 'cancelled') {
            $this->assertSame('cancelled', $orderRow->status);
            $this->assertSame('2.0000', $this->stock($fixture['supply']->id));
            $this->assertSame('100.0000', $this->productStock($fixture['product']->id));
            $this->assertSame(1, $this->countRows('bape_kitchen_inventory_operations', ['operation_type' => 'cancellation']));
            $checkoutLocation = (string) ($results['checkout']['payload']['location'] ?? '');
            $this->assertStringContainsString(
                '/delivery/'.$deliveryId,
                $checkoutLocation,
                'El checkout abortado debe redirigir al detalle del delivery sin cerrarlo: '.json_encode($results)
            );
        } else {
            $this->assertSame('delivered', $deliveryRow->status);
            $this->assertSame('completed', $orderRow->status);
            $this->assertSame('0.0000', $this->stock($fixture['supply']->id));
            $this->assertSame('99.0000', $this->productStock($fixture['product']->id));
            $this->assertSame(0, $this->countRows('bape_kitchen_inventory_operations', ['operation_type' => 'cancellation']));
        }
    }

    private function seedCheckoutFixture(float $supplyStock, int $orderCount, bool $enableFlag = true, ?float $productStock = null): array
    {
        $connection = self::CONNECTION;
        $suffix = str_replace('.', '', uniqid('', true));

        $user = User::on($connection)->create([
            'name' => 'Cajero POS '.$suffix,
            'email' => 'cajero.pos.'.$suffix.'@example.com',
            'password' => 'password-no-usado-en-consola',
            'role' => 'cashier',
            'all_areas' => true,
        ]);

        CashRegister::on($connection)->create([
            'user_id' => $user->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $category = Category::on($connection)->create(['name' => 'Platos POS '.$suffix]);
        $product = Product::on($connection)->create([
            'category_id' => $category->id,
            'name' => 'Plato POS '.$suffix,
            'price' => 10,
            'stock' => $productStock,
            'is_active' => true,
        ]);

        $area = Area::on($connection)->create(['name' => 'Salón POS '.$suffix]);
        $table = Table::on($connection)->create(['area_id' => $area->id, 'name' => 'Mesa POS '.$suffix]);

        $kitchenCategory = KitchenCategory::on($connection)->firstOrCreate(['name' => 'Concurrencia POS']);
        $supply = KitchenSupply::on($connection)->create([
            'category_id' => $kitchenCategory->id,
            'name' => 'Insumo POS '.$suffix,
            'unit' => 'kg',
            'stock_current' => $supplyStock,
        ]);

        $recipe = KitchenRecipe::on($connection)->create(['product_id' => $product->id, 'name' => 'Receta POS '.$suffix]);
        $version = KitchenRecipeVersion::on($connection)->create([
            'recipe_id' => $recipe->id,
            'version' => 1,
            'status' => 'published',
        ]);
        KitchenRecipeItem::on($connection)->create([
            'recipe_version_id' => $version->id,
            'kitchen_supply_id' => $supply->id,
            'quantity' => 2,
        ]);

        if ($enableFlag) {
            DB::connection($connection)->table('bape_settings')->insert([
                'key' => 'kitchen_stock_control',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $orders = [];
        for ($index = 0; $index < $orderCount; $index++) {
            $order = Order::on($connection)->create([
                'table_id' => $table->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'total' => 10,
            ]);
            $order->details()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 10,
                'status' => 'pending',
            ]);
            $orders[] = $order;
        }

        return compact('user', 'product', 'supply', 'orders');
    }

    private function splitCheckoutResults(array $results): array
    {
        $successful = array_values(array_filter($results, fn (array $result): bool => (bool) ($result['payload']['ok'] ?? false)));
        $rejected = array_values(array_filter($results, fn (array $result): bool => ! ($result['payload']['ok'] ?? false)));

        return [$successful, $rejected];
    }

    private function assertCheckoutRejectedAsClosed(array $rejectedResult, array $results): void
    {
        $payload = $rejectedResult['payload'];
        $this->assertSame('validation', $payload['error'] ?? null, json_encode($results));
        $this->assertStringContainsString(
            'Orden cerrada',
            (string) ($payload['errors']['general'][0] ?? ''),
            json_encode($results)
        );
    }

    private function productStock(int $productId): string
    {
        $value = DB::connection(self::CONNECTION)->table('bape_products')->where('id', $productId)->value('stock');

        return number_format((float) $value, 4, '.', '');
    }

    private function commercialLogCount(int $orderId): int
    {
        return (int) DB::connection(self::CONNECTION)
            ->table('bape_inventory_logs')
            ->where('note', 'Venta POS #'.$orderId)
            ->count();
    }

    private function runCheckoutAndQuantityWorkers(int $orderId, int $detailId, int $userId): array
    {
        $startAt = microtime(true) + 0.5;
        $commands = [
            'checkout' => [
                PHP_BINARY,
                base_path('tests/Support/kitchen_pos_checkout_worker.php'),
                (string) $orderId,
                (string) $userId,
                (string) $startAt,
            ],
            'update' => [
                PHP_BINARY,
                base_path('tests/Support/kitchen_update_quantity_worker.php'),
                (string) $detailId,
                (string) 2,
                (string) $userId,
                (string) $startAt,
            ],
        ];

        $processes = [];
        foreach ($commands as $key => $command) {
            $pipes = [];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->workerEnv());
            if (! is_resource($process)) {
                $this->fail('No se pudo lanzar el proceso trabajador de concurrencia: '.$key);
            }
            $processes[$key] = ['process' => $process, 'pipes' => $pipes];
        }

        $results = [];
        foreach ($processes as $key => $entry) {
            $stdout = (string) stream_get_contents($entry['pipes'][1]);
            $stderr = (string) stream_get_contents($entry['pipes'][2]);
            fclose($entry['pipes'][1]);
            fclose($entry['pipes'][2]);
            $exitCode = proc_close($entry['process']);
            $results[$key] = [
                'exit' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'payload' => json_decode($stdout, true),
            ];
        }

        return $results;
    }

    private function runCheckoutAndMutationWorkers(int $orderId, int $userId, string $action, string $targetType, int $targetId, string $value = ''): array
    {
        $startAt = microtime(true) + 0.5;
        $commands = [
            'checkout' => [
                PHP_BINARY,
                base_path('tests/Support/kitchen_pos_checkout_worker.php'),
                (string) $orderId,
                (string) $userId,
                (string) $startAt,
            ],
            'mutation' => [
                PHP_BINARY,
                base_path('tests/Support/kitchen_pos_mutation_worker.php'),
                $action,
                $targetType,
                (string) $targetId,
                $value,
                (string) $userId,
                (string) $startAt,
            ],
        ];

        $processes = [];
        foreach ($commands as $key => $command) {
            $pipes = [];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->workerEnv());
            if (! is_resource($process)) {
                $this->fail('No se pudo lanzar el proceso trabajador de concurrencia: '.$key);
            }
            $processes[$key] = ['process' => $process, 'pipes' => $pipes];
        }

        $results = [];
        foreach ($processes as $key => $entry) {
            $stdout = (string) stream_get_contents($entry['pipes'][1]);
            $stderr = (string) stream_get_contents($entry['pipes'][2]);
            fclose($entry['pipes'][1]);
            fclose($entry['pipes'][2]);
            $exitCode = proc_close($entry['process']);
            $results[$key] = [
                'exit' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'payload' => json_decode($stdout, true),
            ];
        }

        return $results;
    }

    private function runCheckoutAndDeliveryStoreWorkers(int $orderId, int $productId, int $userId): array
    {
        $startAt = microtime(true) + 0.5;
        $commands = [
            'checkout' => [
                PHP_BINARY,
                base_path('tests/Support/kitchen_pos_checkout_worker.php'),
                (string) $orderId,
                (string) $userId,
                (string) $startAt,
            ],
            'delivery' => [
                PHP_BINARY,
                base_path('tests/Support/kitchen_delivery_store_worker.php'),
                (string) $productId,
                (string) 1,
                (string) $userId,
                (string) $startAt,
            ],
        ];

        $processes = [];
        foreach ($commands as $key => $command) {
            $pipes = [];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->workerEnv());
            if (! is_resource($process)) {
                $this->fail('No se pudo lanzar el proceso trabajador de concurrencia: '.$key);
            }
            $processes[$key] = ['process' => $process, 'pipes' => $pipes];
        }

        $results = [];
        foreach ($processes as $key => $entry) {
            $stdout = (string) stream_get_contents($entry['pipes'][1]);
            $stderr = (string) stream_get_contents($entry['pipes'][2]);
            fclose($entry['pipes'][1]);
            fclose($entry['pipes'][2]);
            $exitCode = proc_close($entry['process']);
            $results[$key] = [
                'exit' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'payload' => json_decode($stdout, true),
            ];
        }

        return $results;
    }

    private function runDeliveryStoreWorker(int $productId, int $userId): array
    {
        $command = [
            PHP_BINARY,
            base_path('tests/Support/kitchen_delivery_store_worker.php'),
            (string) $productId,
            (string) 1,
            (string) $userId,
            (string) 0,
        ];

        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->workerEnv());
        if (! is_resource($process)) {
            $this->fail('No se pudo lanzar el proceso trabajador de creación de delivery.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exit' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'payload' => json_decode($stdout, true),
        ];
    }

    private function runCancelAndCheckoutWorkers(int $deliveryId, int $userId): array
    {
        $cancelStartAt = microtime(true) + 0.5;
        $checkoutStartAt = $cancelStartAt + 0.4;
        $commands = [
            'cancel' => [
                PHP_BINARY,
                base_path('tests/Support/kitchen_delivery_close_worker.php'),
                'cancel',
                (string) $deliveryId,
                (string) $userId,
                (string) $cancelStartAt,
            ],
            'checkout' => [
                PHP_BINARY,
                base_path('tests/Support/kitchen_delivery_close_worker.php'),
                'checkout',
                (string) $deliveryId,
                (string) $userId,
                (string) $checkoutStartAt,
            ],
        ];

        $processes = [];
        foreach ($commands as $key => $command) {
            $pipes = [];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->workerEnv());
            if (! is_resource($process)) {
                $this->fail('No se pudo lanzar el proceso trabajador de concurrencia: '.$key);
            }
            $processes[$key] = ['process' => $process, 'pipes' => $pipes];
        }

        $results = [];
        foreach ($processes as $key => $entry) {
            $stdout = (string) stream_get_contents($entry['pipes'][1]);
            $stderr = (string) stream_get_contents($entry['pipes'][2]);
            fclose($entry['pipes'][1]);
            fclose($entry['pipes'][2]);
            $exitCode = proc_close($entry['process']);
            $results[$key] = [
                'exit' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'payload' => json_decode($stdout, true),
            ];
        }

        return $results;
    }

    private function workerEnv(): array
    {
        $inherited = getenv();
        if (! is_array($inherited)) {
            $inherited = [];
        }
        $inherited = array_filter($inherited, 'is_scalar');
        $config = $this->connectionConfig();

        return array_merge($inherited, [
            'DB_CONNECTION' => 'mysql',
            'KITCHEN_WORKER_DB_HOST' => (string) $config['host'],
            'KITCHEN_WORKER_DB_PORT' => (string) $config['port'],
            'KITCHEN_WORKER_DB_DATABASE' => self::TEST_DATABASE,
            'KITCHEN_WORKER_DB_USERNAME' => (string) $config['username'],
            'KITCHEN_WORKER_DB_PASSWORD' => (string) $config['password'],
        ]);
    }

    private function runCheckoutWorkers(array $orderIds, int $userId): array
    {
        $script = base_path('tests/Support/kitchen_pos_checkout_worker.php');
        $startAt = microtime(true) + 0.5;
        $processes = [];
        foreach (array_values($orderIds) as $index => $orderId) {
            $command = [
                PHP_BINARY,
                $script,
                (string) $orderId,
                (string) $userId,
                (string) $startAt,
            ];
            $pipes = [];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->workerEnv());
            if (! is_resource($process)) {
                $this->fail('No se pudo lanzar el proceso trabajador de checkout.');
            }
            $processes[$index] = ['process' => $process, 'pipes' => $pipes];
        }

        $results = [];
        foreach ($processes as $index => $entry) {
            $stdout = (string) stream_get_contents($entry['pipes'][1]);
            $stderr = (string) stream_get_contents($entry['pipes'][2]);
            fclose($entry['pipes'][1]);
            fclose($entry['pipes'][2]);
            $exitCode = proc_close($entry['process']);
            $results[$index] = [
                'exit' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'payload' => json_decode($stdout, true),
            ];
        }

        return $results;
    }

    private function connectServer(): PDO
    {
        $config = $this->connectionConfig();

        try {
            return new PDO(
                "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Servidor MySQL de pruebas no disponible en '.$config['host'].':'.$config['port'].' — '.$exception->getMessage());
        }
    }

    private function recreateTestDatabase(): void
    {
        $name = self::TEST_DATABASE;

        if (! preg_match('/^[A-Za-z0-9_]+_test$/', $name)) {
            $this->fail('La base de pruebas debe tener un nombre terminado en _test para evitar tocar bases reales.');
        }

        $this->server->exec("DROP DATABASE IF EXISTS `{$name}`");
        $this->server->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function connectionConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => env('KITCHEN_TEST_DB_HOST', '127.0.0.1'),
            'port' => env('KITCHEN_TEST_DB_PORT', '3306'),
            'database' => self::TEST_DATABASE,
            'username' => env('KITCHEN_TEST_DB_USERNAME', 'root'),
            'password' => env('KITCHEN_TEST_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ];
    }

    private function countRows(string $table, array $where): int
    {
        return (int) DB::connection(self::CONNECTION)->table($table)->where($where)->count();
    }

    private function countRowsWhereIn(string $table, string $column, array $values): int
    {
        return (int) DB::connection(self::CONNECTION)->table($table)->whereIn($column, $values)->count();
    }

    private function stock(int $supplyId): string
    {
        $value = DB::connection(self::CONNECTION)->table('bape_kitchen_supplies')->where('id', $supplyId)->value('stock_current');

        return number_format((float) $value, 4, '.', '');
    }

    private function orderStatus(int $orderId): string
    {
        return (string) DB::connection(self::CONNECTION)->table('bape_orders')->where('id', $orderId)->value('status');
    }
}
