<?php
/**
 * CLI: importa CSV desde logimeat_datos/manejo de empleados
 * Uso: php scripts/import_empleados_ejecutar.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conexion.php';
require_once $root . '/config/import_empleados_csv.php';

$baseDir = dirname($root) . DIRECTORY_SEPARATOR . 'logimeat_datos' . DIRECTORY_SEPARATOR . 'manejo de empleados';

$result = importarEmpleadosDesdeCarpeta($pdo, $baseDir);
$r2 = importarDescansosDashboardDesdeCarpeta($pdo, $baseDir);
$result['mensajes'] = array_merge($result['mensajes'], $r2['mensajes']);
$result['errores'] = array_merge($result['errores'], $r2['errores']);
$r3 = importarArchivosProgSemanaDesdeCarpeta($pdo, $baseDir, 2026);
$result['mensajes'] = array_merge($result['mensajes'], $r3['mensajes']);
$result['errores'] = array_merge($result['errores'], $r3['errores']);

foreach ($result['mensajes'] as $m) {
    echo $m . PHP_EOL;
}
foreach ($result['errores'] as $e) {
    fwrite(STDERR, 'ERROR: ' . $e . PHP_EOL);
}

exit($result['errores'] ? 1 : 0);
