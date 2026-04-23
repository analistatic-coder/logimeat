<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conexion.php';
require_once $root . '/config/import_empleados_csv.php';

$base = dirname($root) . DIRECTORY_SEPARATOR . 'logimeat_datos' . DIRECTORY_SEPARATOR . 'manejo de empleados';

$r = importarDescansosDashboardDesdeCarpeta($pdo, $base);
foreach ($r['mensajes'] as $m) {
    echo $m . PHP_EOL;
}
foreach ($r['errores'] as $e) {
    fwrite(STDERR, 'ERROR: ' . $e . PHP_EOL);
}
exit($r['errores'] ? 1 : 0);
