<?php
/**
 * Añade Actividad, Planta, Producto y tabla programacion_actividad_extra.
 * Uso: php scripts/aplicar_schema_programacion_operacional.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conexion.php';

$sqls = [
    'CREATE TABLE IF NOT EXISTS programacion_actividad_extra (
  id_interno INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Nombre VARCHAR(128) NOT NULL,
  PRIMARY KEY (id_interno),
  UNIQUE KEY uk_prog_act_nom (Nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($sqls as $sql) {
    $pdo->exec($sql);
    echo "OK: " . substr($sql, 0, 50) . "...\n";
}

$cols = $pdo->query("SHOW COLUMNS FROM empleado_programacion LIKE 'Actividad'")->fetch();
if (!$cols) {
    $pdo->exec('ALTER TABLE empleado_programacion ADD COLUMN Actividad VARCHAR(128) DEFAULT NULL AFTER Turno');
    echo "OK: ADD Actividad\n";
}
$cols = $pdo->query("SHOW COLUMNS FROM empleado_programacion LIKE 'Planta'")->fetch();
if (!$cols) {
    $pdo->exec('ALTER TABLE empleado_programacion ADD COLUMN Planta VARCHAR(32) DEFAULT NULL AFTER Actividad');
    echo "OK: ADD Planta\n";
}
$cols = $pdo->query("SHOW COLUMNS FROM empleado_programacion LIKE 'Producto'")->fetch();
if (!$cols) {
    $pdo->exec('ALTER TABLE empleado_programacion ADD COLUMN Producto VARCHAR(128) DEFAULT NULL AFTER Planta');
    echo "OK: ADD Producto\n";
}

echo "Listo.\n";
