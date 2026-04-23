<?php
/**
 * Aplica migración: empleado PK = ID_Empleado (sin id_interno).
 * Uso: php scripts/aplicar_migrar_empleado_pk.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conexion.php';

try {
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec(
        'ALTER TABLE empleado
  DROP PRIMARY KEY,
  DROP INDEX uk_empleado_negocio,
  ADD PRIMARY KEY (ID_Empleado),
  DROP COLUMN id_interno'
    );
    echo "Migración aplicada: PRIMARY KEY (ID_Empleado), columna id_interno eliminada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
