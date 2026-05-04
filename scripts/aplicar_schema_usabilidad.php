<?php
/**
 * Crea tabla app_usabilidad_evento si no existe.
 * php scripts/aplicar_schema_usabilidad.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conexion.php';
require_once $root . '/config/usabilidad_log.php';

if (lm_usabilidad_ensure_table($pdo) && lm_usabilidad_tabla_exist($pdo)) {
    echo "OK: app_usabilidad_evento disponible.\n";
} else {
    echo "Error: no se pudo crear o verificar app_usabilidad_evento.\n";
    exit(1);
}
