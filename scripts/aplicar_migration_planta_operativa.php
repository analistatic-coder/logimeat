<?php
/**
 * Añade Planta_Operativa (BENEFICIO|DESPOSTE|CELFRIO) a Programacion.
 * php scripts/aplicar_migration_planta_operativa.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conexion.php';

$c = $pdo->query("SHOW COLUMNS FROM Programacion LIKE 'Planta_Operativa'")->fetch();
if (!$c) {
    $pdo->exec("ALTER TABLE Programacion ADD COLUMN Planta_Operativa VARCHAR(16) NULL COMMENT 'BENEFICIO|DESPOSTE|CELFRIO' AFTER Planta");
    echo "OK: Planta_Operativa agregada.\n";
} else {
    echo "Planta_Operativa ya existe.\n";
}
