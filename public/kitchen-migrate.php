<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=UTF-8');

$token = 'e6829efd392dd1d4432511ddc7729079';

if (! hash_equals($token, (string) ($_GET['clave'] ?? ''))) {
    http_response_code(403);
    exit('403 Forbidden');
}

function abort_run(string $message): void
{
    echo 'ABORTADO: '.$message.PHP_EOL;
    exit(1);
}

$connection = DB::selectOne('SELECT DATABASE() AS db, VERSION() AS version');

if (($connection->db ?? null) !== 'avicola1_bdsistema') {
    abort_run('La base activa es "'.($connection->db ?? 'null').'", se esperaba "avicola1_bdsistema".');
}

$version = $connection->version ?? '';

if (stripos($version, 'mariadb') === false || stripos($version, '10.11') === false) {
    abort_run('El servidor no es MariaDB 10.11 (version actual: '.$version.').');
}

$tables = DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME REGEXP '^bape_kitchen_'");

if ($tables->total > 0) {
    abort_run('Ya existen '.$tables->total.' tablas bape_kitchen_*. Nada fue ejecutado.');
}

$registered = DB::selectOne("SELECT COUNT(*) AS total FROM migrations WHERE migration LIKE '2026_10_14_%'");

if ($registered->total > 0) {
    abort_run('Las migraciones de cocina ya estan registradas en la tabla migrations.');
}

echo 'Preflight correcto: '.$version.' | base '.$connection->db.' | 0 tablas kitchen'.PHP_EOL.PHP_EOL;
echo '--- migrate (solo 2026_10_14_000001 y 000002) ---'.PHP_EOL;

$exitCode = $kernel->call('migrate', [
    '--force' => true,
    '--path' => [
        'database/migrations/2026_10_14_000001_create_kitchen_inventory_tables.php',
        'database/migrations/2026_10_14_000002_harden_kitchen_inventory_integrity.php',
    ],
]);

echo $kernel->output();

if ($exitCode !== 0) {
    echo 'ERROR: el proceso termino con codigo '.$exitCode.PHP_EOL;
    exit(1);
}

$kernel->call('migrate:status');
echo PHP_EOL.$kernel->output();

$created = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME REGEXP '^bape_kitchen_' ORDER BY TABLE_NAME");

echo '--- tablas kitchen creadas ('.count($created).'/9) ---'.PHP_EOL;

foreach ($created as $table) {
    echo $table->TABLE_NAME.PHP_EOL;
}

if (count($created) !== 9) {
    echo 'ADVERTENCIA: se esperaban 9 tablas.'.PHP_EOL;
    exit(1);
}

@unlink(__FILE__);

echo PHP_EOL.'Migraciones ejecutadas con exito. Este archivo se elimino automaticamente.'.PHP_EOL;
