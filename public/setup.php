<?php
/**
 * SETUP COMPLETO SIN SSH - Laravel 12 en Hosting Compartido
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a la carpeta public/ del hosting
 * 2. Ábrelo en el navegador: https://barrioperuano.moabcode.com/setup.php
 * 3. Ejecuta cada paso en orden
 * 4. ELIMÍNALO después
 */

$basePath = __DIR__ . '/..';
$publicPath = __DIR__;
$results = [];

// ============================================
// FUNCIONES
// ============================================

function setPermsRecursive($dir, $perm = 0755) {
    if (!is_dir($dir)) return 0;
    $count = 0;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($items as $item) {
        @chmod($item->getPathname(), $perm);
        $count++;
    }
    @chmod($dir, $perm);
    return $count;
}

function createStorageLink($basePath, $publicPath) {
    $target = $basePath . '/storage/app/public';
    $link = $publicPath . '/storage';
    
    if (file_exists($link) || is_link($link)) {
        return 'Link ya existe: ' . readlink($link);
    }
    
    if (!is_dir($target)) {
        @mkdir($target, 0755, true);
    }
    
    // Para shared hosting sin symlink(): usar .htaccess + PHP fallback
    return 'SKIP_SYMLINK';
}

// ============================================
// EJECUTAR PASOS
// ============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Laravel - Hosting Compartido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; color: #eee; font-family: 'Inter', sans-serif; padding: 20px; }
        .card { background: #16213e; border: 1px solid #0f3460; }
        .step { border-left: 3px solid #0f3460; padding: 15px 20px; margin-bottom: 15px; }
        .step.ok { border-left-color: #22c55e; }
        .step.fail { border-left-color: #ef4444; }
        .step.warn { border-left-color: #f59e0b; }
        .badge-ok { background: #22c55e; }
        .badge-fail { background: #ef4444; }
        .badge-warn { background: #f59e0b; }
    </style>
</head>
<body>
<div class="container" style="max-width: 800px;">
    <h2 class="text-center mb-4">Setup Laravel - Hosting Compartido</h2>

<?php
// ── PASO 1: Verificar estructura ──
echo '<div class="step" id="step1">';
echo '<h5>Paso 1: Verificar estructura de archivos</h5>';
$checks = [
    '.env' => file_exists($basePath . '/.env'),
    'vendor/autoload.php' => file_exists($basePath . '/vendor/autoload.php'),
    'bootstrap/app.php' => file_exists($basePath . '/bootstrap/app.php'),
    'artisan' => file_exists($basePath . '/artisan'),
    'public/index.php' => file_exists($publicPath . '/index.php'),
    'app/Http/Kernel.php' => file_exists($basePath . '/app/Http/Kernel.php'),
];
$allOk = true;
foreach ($checks as $file => $exists) {
    $icon = $exists ? '✅' : '❌';
    if (!$exists) $allOk = false;
    echo "<div>{$icon} <code>{$file}</code></div>";
}
if ($allOk) {
    echo '<span class="badge badge-ok text-white rounded-pill px-2">OK</span>';
} else {
    echo '<span class="badge badge-fail text-white rounded-pill px-2">FALTAN ARCHIVOS</span>';
    echo '<p class="text-danger mt-2">Sube los archivos faltantes al hosting.</p>';
}
echo '</div>';

// ── PASO 2: Permisos ──
echo '<div class="step" id="step2">';
echo '<h5>Paso 2: Arreglar permisos (carpetas escribibles)</h5>';
$dirs = [
    $basePath . '/storage',
    $basePath . '/bootstrap/cache',
];
foreach ($dirs as $dir) {
    $short = str_replace($basePath . '/', '', $dir);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $writable = is_writable($dir);
    $icon = $writable ? '✅' : '🔧';
    echo "<div>{$icon} <code>{$short}/</code> — " . ($writable ? 'OK (ya es escribible)' : 'Intentando chmod 755...') . "</div>";
    if (!$writable) {
        setPermsRecursive($dir, 0755);
        $writable2 = is_writable($dir);
        echo "<div>" . ($writable2 ? '✅ Ahora funciona!' : '❌ No se pudo. Contacta soporte del hosting.') . "</div>";
    }
}
echo '</div>';

// ── PASO 3: Crear subdirectorios de storage ──
echo '<div class="step" id="step3">';
echo '<h5>Paso 3: Crear carpetas necesarias en storage</h5>';
$subdirs = [
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/framework/testing',
    'storage/logs',
    'storage/app/public',
];
foreach ($subdirs as $sub) {
    $fullPath = $basePath . '/' . $sub;
    if (!is_dir($fullPath)) {
        @mkdir($fullPath, 0755, true);
        echo "<div>📁 <code>{$sub}/</code> — Creada</div>";
    } else {
        echo "<div>✅ <code>{$sub}/</code> — Ya existe</div>";
    }
}
echo '</div>';

// ── PASO 4: Storage symlink ──
echo '<div class="step" id="step4">';
echo '<h5>Paso 4: Storage link (assets públicos)</h5>';
$storageLink = $publicPath . '/storage';
$storageTarget = $basePath . '/storage/app/public';

if (is_link($storageLink)) {
    echo '<div>✅ Symlink ya existe: ' . readlink($storageLink) . '</div>';
} elseif (is_dir($storageLink)) {
    echo '<div>✅ Carpeta storage/ ya existe en public/</div>';
} else {
    // Intentar symlink nativo
    $linked = @symlink($storageTarget, $storageLink);
    if ($linked) {
        echo '<div>✅ Symlink creado exitosamente</div>';
    } else {
        // Fallback: crear .htaccess para servir archivos manualmente
        echo '<div>⚠️ No se pudo crear symlink. Creando fallback con .htaccess...</div>';
        @mkdir($storageLink, 0755, true);
        // Copiar archivos de storage/app/public a public/storage
        if (is_dir($storageTarget)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageTarget, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                $dest = $storageLink . '/' . str_replace($storageTarget . '/', '', $item->getPathname());
                $destDir = dirname($dest);
                if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                if ($item->isFile()) @copy($item->getPathname(), $dest);
            }
        }
        echo '<div>✅ Archivos copiados a public/storage/ (manual, no symlink)</div>';
    }
}
echo '</div>';

// ── PASO 5: Limpiar caché manualmente ──
echo '<div class="step" id="step5">';
echo '<h5>Paso 5: Limpiar archivos de caché</h5>';
$cacheDirs = [
    $basePath . '/bootstrap/cache',
    $basePath . '/storage/framework/cache/data',
    $basePath . '/storage/framework/views',
    $basePath . '/storage/framework/sessions',
];
foreach ($cacheDirs as $cacheDir) {
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        $count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
                $count++;
            } elseif (is_dir($file)) {
                @array_map('unlink', glob($file . '/*'));
                @rmdir($file);
                $count++;
            }
        }
        $short = str_replace($basePath . '/', '', $cacheDir);
        echo "<div>🧹 <code>{$short}/</code> — {$count} archivos eliminados</div>";
    }
}
echo '</div>';

// ── PASO 6: Verificar .env ──
echo '<div class="step" id="step6">';
echo '<h5>Paso 6: Verificar configuración .env</h5>';
if (file_exists($basePath . '/.env')) {
    $env = parse_ini_file($basePath . '/.env', false, INI_SCANNER_RAW);
    // parse_ini_file no funciona bien con .env, usar另一个方法
    $envContent = file_get_contents($basePath . '/.env');
    
    $checks = [
        'APP_KEY' => preg_match('/^APP_KEY=base64:/', $envContent),
        'DB_HOST' => preg_match('/^DB_HOST=.+/m', $envContent),
        'DB_DATABASE' => preg_match('/^DB_DATABASE=.+/m', $envContent),
        'DB_USERNAME' => preg_match('/^DB_USERNAME=.+/m', $envContent),
        'DB_PASSWORD' => preg_match('/^DB_PASSWORD=.+/m', $envContent),
        'SESSION_DRIVER' => preg_match('/^SESSION_DRIVER=.+/m', $envContent),
    ];
    foreach ($checks as $key => $found) {
        echo "<div>" . ($found ? '✅' : '❌') . " <code>{$key}</code> " . ($found ? 'configurado' : 'FALTA') . "</div>";
    }
    
    // APP_URL check + auto-fix
    if (preg_match('/^APP_URL=(.+)/m', $envContent, $m)) {
        $url = trim($m[1]);
        $isLocal = in_array($url, ['http://localhost', 'http://localhost:8000', '']);
        echo "<div>" . ($isLocal ? '⚠️' : '✅') . " <code>APP_URL={$url}</code> " . ($isLocal ? '— Debería ser https://barrioperuano.moabcode.com' : '') . "</div>";
        if ($isLocal) {
            $envContent = preg_replace('/^APP_URL=.*/m', 'APP_URL=https://barrioperuano.moabcode.com', $envContent);
            @file_put_contents($basePath . '/.env', $envContent);
            echo '<div style="color:#22c55e">✅ <b>APP_URL corregido automáticamente</b></div>';
            $envContent = file_get_contents($basePath . '/.env');
        }
    }
    
    // MAIL check + auto-fix
    $mailFixes = [
        '/^MAIL_USERNAME=.*/m' => ['MAIL_USERNAME=barrioperuano@moabcode.com', 'MAIL_USERNAME (Villar → Barrio Peruano)'],
        '/^MAIL_FROM_ADDRESS=.*/m' => ['MAIL_FROM_ADDRESS=barrioperuano@moabcode.com', 'MAIL_FROM_ADDRESS (Villar → Barrio Peruano)'],
        '/^MAIL_FROM_NAME=.*/m' => ['MAIL_FROM_NAME="Restaurante Barrio Peruano"', 'MAIL_FROM_NAME (Villar → Barrio Peruano)'],
    ];
    foreach ($mailFixes as $pattern => [$replacement, $label]) {
        if (preg_match($pattern, $envContent, $m)) {
            $current = trim(str_replace(['MAIL_USERNAME=', 'MAIL_FROM_ADDRESS=', 'MAIL_FROM_NAME='], '', $m[0]));
            if (strpos($current, 'Villar') !== false || strpos($current, 'villar') !== false) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                @file_put_contents($basePath . '/.env', $envContent);
                echo '<div style="color:#22c55e">✅ <b>' . $label . ' corregido automáticamente</b></div>';
                $envContent = file_get_contents($basePath . '/.env');
            }
        }
    }
} else {
    echo '<div>❌ No se encontró .env</div>';
}
echo '</div>';

// ── PASO 7: Intentar cargar Laravel ──
echo '<div class="step" id="step7">';
echo '<h5>Paso 7: Probar si Laravel carga</h5>';
try {
    require $basePath . '/vendor/autoload.php';
    $app = require $basePath . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request = Illuminate\Http\Request::capture());
    $status = $response->getStatusCode();
    echo "<div>✅ Laravel funciona correctamente! Status: <b>{$status}</b></div>";
    echo '<div class="mt-2"><a href="/" class="btn btn-success">Ir al sitio →</a></div>';
} catch (Throwable $e) {
    echo '<div>❌ Error al cargar Laravel:</div>';
    echo '<pre class="bg-dark text-danger p-3 rounded mt-2" style="font-size:0.8rem;overflow:auto;">';
    echo htmlspecialchars($e->getMessage());
    echo "\n\nArchivo: " . $e->getFile() . ":" . $e->getLine();
    echo '</pre>';
    
    // Guardar el error también en un archivo
    file_put_contents($publicPath . '/setup_error.log', 
        date('Y-m-d H:i:s') . "\n" . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n\n"
    );
}
echo '</div>';

// ── INSTRUCCIONES FINALES ──
echo '<div class="card p-4 mt-4">';
echo '<h4>Instrucciones manuales (si algo falla)</h4>';
echo '<p>Si el Paso 7 falla, estos son los pasos manuales en el File Manager del cPanel:</p>';
echo '<ol>';
echo '<li><b>Permisos:</b> Click derecho en <code>storage/</code> → Change Permissions → 755 (Recurse)</li>';
echo '<li><b>Permisos:</b> Click derecho en <code>bootstrap/cache/</code> → Change Permissions → 755 (Recurse)</li>';
echo '<li><b>APP_URL:</b> Edita <code>.env</code> y cambia <code>APP_URL=http://localhost</code> por <code>APP_URL=https://barrioperuano.moabcode.com</code></li>';
echo '<li><b>MAIL_USERNAME:</b> Cambia <code>restaurantevillar@moabcode.com</code> por <code>barrioperuano@moabcode.com</code></li>';
echo '<li><b>MAIL_FROM_ADDRESS:</b> Cambia <code>restaurantevillar@moabcode.com</code> por <code>barrioperuano@moabcode.com</code></li>';
echo '<li><b>MAIL_FROM_NAME:</b> Cambia <code>"Restaurante Villar"</code> por <code>"Restaurante Barrio Peruano"</code></li>';
echo '</ol>';
echo '</div>';

echo '<p class="text-center text-muted mt-4">⚠️ ELIMINA este archivo después de usarlo: <code>setup.php</code></p>';
?>
</div>
</body>
</html>
