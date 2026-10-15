<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Kitchen\KitchenCategory;
use App\Models\Kitchen\KitchenRecipe;
use App\Models\Kitchen\KitchenRecipeItem;
use App\Models\Kitchen\KitchenRecipeVersion;
use App\Models\Kitchen\KitchenSupply;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class KitchenInventoryConcurrencyTest extends TestCase
{
    private const TEST_DATABASE = 'restaurante_kitchen_test';

    private const CONNECTION = 'kitchen_test';

    private PDO $server;

    protected function setUp(): void
    {
        parent::setUp();

        $flag = (string) getenv('RUN_KITCHEN_CONCURRENCY_TESTS');
        if (! in_array(strtolower($flag), ['1', 'true', 'yes'], true)) {
            $this->markTestSkipped('Concurrencia no ejecutada: requiere RUN_KITCHEN_CONCURRENCY_TESTS=true apuntando a la base MySQL de pruebas autorizada restaurante_kitchen_test en el servidor local.');
        }

        $this->server = $this->connectServer();
        $this->recreateTestDatabase();

        config(['database.connections.'.self::CONNECTION => $this->connectionConfig()]);
        DB::purge(self::CONNECTION);

        $exitCode = Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true]);
        $this->assertSame(0, $exitCode, 'Fallo al migrar la base de concurrencia: '.Artisan::output());
    }

    public function test_concurrent_sales_with_same_key_and_payload_converge_to_one_idempotent_operation(): void
    {
        [$supply, $version] = $this->seedSupply('Harina-Same', 10, 2);
        $lines = [['supply_id' => $supply->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1]];

        $results = $this->runWorkers([
            ['event_key' => 'concurrent-same', 'lines' => $lines, 'control_stock' => false],
            ['event_key' => 'concurrent-same', 'lines' => $lines, 'control_stock' => false],
        ]);

        $successful = array_values(array_filter($results, fn (array $result): bool => (bool) ($result['payload']['ok'] ?? false)));
        $this->assertNotEmpty($successful, 'Ningún proceso terminó correctamente: '.json_encode($results));

        $operationIds = array_values(array_unique(array_map(fn (array $result): int => (int) $result['payload']['operation_id'], $successful)));
        $this->assertCount(1, $operationIds, 'Se esperaba una única operación por ambas conexiones: '.json_encode($results));

        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_operations', ['event_key' => 'concurrent-same']));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_movements', []));
        $this->assertSame(1, $this->countRows('bape_kitchen_order_consumptions', []));
        $this->assertSame('8.0000', $this->stock($supply->id));
    }

    public function test_concurrent_same_key_with_different_payloads_produces_a_single_winner_and_a_controlled_conflict(): void
    {
        [$supply, $version] = $this->seedSupply('Harina-Conflict', 10, 2);

        $results = $this->runWorkers([
            ['event_key' => 'concurrent-conflict', 'lines' => [['supply_id' => $supply->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1]], 'control_stock' => false],
            ['event_key' => 'concurrent-conflict', 'lines' => [['supply_id' => $supply->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 2]], 'control_stock' => false],
        ]);

        $successful = array_values(array_filter($results, fn (array $result): bool => (bool) ($result['payload']['ok'] ?? false)));
        $conflicts = array_values(array_filter($results, fn (array $result): bool => ($result['payload']['error'] ?? null) === 'conflict'));

        $this->assertCount(1, $successful, 'Se esperaba exactamente un ganador: '.json_encode($results));
        $this->assertCount(1, $conflicts, 'Se esperaba exactamente un conflicto controlado: '.json_encode($results));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_operations', ['event_key' => 'concurrent-conflict']));
        $this->assertSame(1, $this->countRows('bape_kitchen_inventory_movements', []));

        $stock = $this->stock($supply->id);
        $this->assertContains($stock, ['8.0000', '6.0000'], 'El stock debe corresponder al ganador de la carrera: '.json_encode($results));
    }

    public function test_concurrent_sales_with_limited_stock_never_go_negative_and_cover_at_most_available_stock(): void
    {
        [$supply, $version] = $this->seedSupply('Harina-Limited', 1, 2);

        $results = $this->runWorkers([
            ['event_key' => 'concurrent-stock-a', 'lines' => [['supply_id' => $supply->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1]], 'control_stock' => false],
            ['event_key' => 'concurrent-stock-b', 'lines' => [['supply_id' => $supply->id, 'recipe_version_id' => $version->id, 'sold_quantity' => 1]], 'control_stock' => false],
        ]);

        $successful = array_values(array_filter($results, fn (array $result): bool => (bool) ($result['payload']['ok'] ?? false)));
        $this->assertCount(2, $successful, 'Ambas ventas sin control deberían registrarse: '.json_encode($results));

        $connection = DB::connection(self::CONNECTION);
        $this->assertSame('0.0000', $this->stock($supply->id));

        $covered = (float) $connection->table('bape_kitchen_order_consumptions')->sum('covered_quantity');
        $shortage = (float) $connection->table('bape_kitchen_order_consumptions')->sum('shortage_quantity');
        $moved = (float) $connection->table('bape_kitchen_inventory_movements')->where('type', 'sale')->sum('quantity');
        $negative = $connection->table('bape_kitchen_inventory_movements')->where('stock_after', '<', 0)->count();

        $this->assertSame(1.0, $covered, 'La cobertura total no puede superar el stock disponible: '.json_encode($results));
        $this->assertSame(1.0, $moved, 'El movimiento físico total debe limitarse al stock disponible: '.json_encode($results));
        $this->assertSame(3.0, $shortage, 'El faltante teórico debe quedar registrado: '.json_encode($results));
        $this->assertSame(0, $negative);
    }

    public function test_mysql_schema_rejects_negative_stock_via_check_constraint(): void
    {
        [$supply] = $this->seedSupply('Harina-Check', 10, 2);

        try {
            DB::connection(self::CONNECTION)->table('bape_kitchen_supplies')->where('id', $supply->id)->update(['stock_current' => -1]);
            $this->fail('Se esperaba violación de la restricción CHECK de stock no negativo.');
        } catch (QueryException) {
            $this->assertSame('10.0000', $this->stock($supply->id));
        }

        $constraint = DB::connection(self::CONNECTION)->selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_NAME = ?',
            [self::TEST_DATABASE, 'chk_kitchen_supplies_stock_non_negative']
        );
        $this->assertSame(1, (int) ($constraint->total ?? 0), 'La restricción CHECK debe existir en el motor de pruebas.');
    }

    private function runWorkers(array $jobs): array
    {
        $script = base_path('tests/Support/kitchen_sale_worker.php');
        $inherited = getenv();
        if (! is_array($inherited)) {
            $inherited = [];
        }
        $inherited = array_filter($inherited, 'is_scalar');
        $config = $this->connectionConfig();
        $env = array_merge($inherited, [
            'DB_CONNECTION' => 'mysql',
            'KITCHEN_WORKER_DB_HOST' => (string) $config['host'],
            'KITCHEN_WORKER_DB_PORT' => (string) $config['port'],
            'KITCHEN_WORKER_DB_DATABASE' => self::TEST_DATABASE,
            'KITCHEN_WORKER_DB_USERNAME' => (string) $config['username'],
            'KITCHEN_WORKER_DB_PASSWORD' => (string) $config['password'],
        ]);

        $processes = [];
        foreach (array_values($jobs) as $index => $job) {
            $command = [
                PHP_BINARY,
                $script,
                (string) $job['event_key'],
                json_encode($job['lines'], JSON_THROW_ON_ERROR),
                ! empty($job['control_stock']) ? '1' : '0',
            ];
            $pipes = [];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            if (! is_resource($process)) {
                $this->fail('No se pudo lanzar el proceso trabajador de concurrencia.');
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

    private function seedSupply(string $name, float $stock, float $recipeQuantity): array
    {
        $category = KitchenCategory::on(self::CONNECTION)->firstOrCreate(['name' => 'Concurrencia']);
        $supply = KitchenSupply::on(self::CONNECTION)->create([
            'category_id' => $category->id,
            'name' => $name,
            'unit' => 'kg',
            'stock_current' => $stock,
        ]);
        $productCategory = Category::on(self::CONNECTION)->firstOrCreate(['name' => 'Platos Concurrencia']);
        $product = Product::on(self::CONNECTION)->create([
            'category_id' => $productCategory->id,
            'name' => 'Plato '.$name,
            'price' => 10,
            'stock' => null,
            'is_active' => true,
        ]);
        $recipe = KitchenRecipe::on(self::CONNECTION)->create(['product_id' => $product->id, 'name' => 'Receta '.$name]);
        $version = KitchenRecipeVersion::on(self::CONNECTION)->create(['recipe_id' => $recipe->id, 'version' => 1, 'status' => 'published']);
        KitchenRecipeItem::on(self::CONNECTION)->create([
            'recipe_version_id' => $version->id,
            'kitchen_supply_id' => $supply->id,
            'quantity' => $recipeQuantity,
        ]);

        return [$supply, $version];
    }

    private function countRows(string $table, array $where): int
    {
        return (int) DB::connection(self::CONNECTION)->table($table)->where($where)->count();
    }

    private function stock(int $supplyId): string
    {
        $value = DB::connection(self::CONNECTION)->table('bape_kitchen_supplies')->where('id', $supplyId)->value('stock_current');

        return number_format((float) $value, 4, '.', '');
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
}
