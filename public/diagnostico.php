<?php
/**
 * DIAGNÓSTICO - Sube este archivo a public/ y ábrelo en el navegador.
 * ELIMÍNALO después de usarlo por seguridad.
 */

echo "<h2>Diagnóstico Laravel</h2>";

// 1. PHP Version
echo "<p><b>PHP Version:</b> " . phpversion() . "</p>";

// 2. Required extensions
$required = ['pdo_mysql','mbstring','openssl','tokenizer','xml','ctype','json','bcmath','fileinfo','gd','curl','intl','dom'];
$missing = [];
foreach ($required as $ext) {
    if (!extension_loaded($ext)) $missing[] = $ext;
}
if ($missing) {
    echo "<p style='color:red'><b>Faltan extensiones PHP:</b> " . implode(', ', $missing) . "</p>";
} else {
    echo "<p style='color:green'><b>Todas las extensiones PHP OK</b></p>";
}

// 3. .env exists?
$envPath = __DIR__ . '/../.env';
echo "<p><b>.env existe:</b> " . (file_exists($envPath) ? 'SI (' . filesize($envPath) . ' bytes)' : '<span style="color:red">NO - ARCHIVO NO ENCONTRADO en ' . $envPath . '</span>') . "</p>";

// 4. vendor/autoload.php
echo "<p><b>vendor/autoload.php:</b> " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'SI' : '<span style="color:red">NO</span>') . "</p>";

// 5. storage writable?
echo "<p><b>storage/ es escribible:</b> " . (is_writable(__DIR__ . '/../storage') ? 'SI' : '<span style="color:red">NO - Necesita permisos 755</span>') . "</p>";
echo "<p><b>bootstrap/cache/ es escribible:</b> " . (is_writable(__DIR__ . '/../bootstrap/cache') ? 'SI' : '<span style="color:red">NO - Necesita permisos 755</span>') . "</p>";

// 6. Try to load Laravel
echo "<hr><h3>Cargando Laravel...</h3>";
try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request = Illuminate\Http\Request::capture());
    echo "<p style='color:green'><b>Laravel funciona OK!</b> Status: " . $response->getStatusCode() . "</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'><b>Error de Laravel:</b></p>";
    echo "<pre style='background:#f8d7da;padding:15px;border-radius:8px;overflow:auto;'>" . htmlspecialchars($e->getMessage()) . "\n\n" . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</pre>";
}

echo "<hr><p><b>ELIMINA ESTE ARCHIVO después del diagnóstico.</b></p>";
