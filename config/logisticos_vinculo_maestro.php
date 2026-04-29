<?php
declare(strict_types=1);

require_once __DIR__ . '/programacion_opl_logistica.php';

/**
 * Inserta un vínculo OPL + conductor + vehículo en la tabla puente `logisticos` (si existe y el mapa es compatible).
 *
 * @return bool true si se insertó (o ya existía equivalente), false si no aplica
 */
function logisticos_maestro_insertar_triple(PDO $pdo, string $idOpl, string $idConductor, string $idVehiculo): bool
{
    $idOpl = trim($idOpl);
    $idConductor = trim($idConductor);
    $idVehiculo = trim($idVehiculo);
    if ($idOpl === '' || $idConductor === '' || $idVehiculo === '') {
        return false;
    }

    static $cacheMap = null;
    if ($cacheMap === false) {
        return false;
    }
    if ($cacheMap === null) {
        $cacheMap = logisticos_maestro_resolver_mapa($pdo);
        if ($cacheMap === null) {
            $cacheMap = false;

            return false;
        }
    }

    $tabla = $cacheMap['tabla'];
    $cOpl = $cacheMap['col_opl'];
    $cCond = $cacheMap['col_cond'];
    $cVeh = $cacheMap['col_veh'];
    $colsDisponibles = $cacheMap['cols'];

    $nombreColInsensitive = static function (array $cols, string $buscado): ?string {
        $b = strtolower($buscado);
        foreach ($cols as $c) {
            $c = (string) $c;
            if (strtolower($c) === $b) {
                return $c;
            }
        }

        return null;
    };

    $insertCols = [];
    $insertVals = [];

    $colIdInterno = $nombreColInsensitive($colsDisponibles, 'id_interno');
    if ($colIdInterno !== null) {
        try {
            $colEsc = str_replace('`', '``', $colIdInterno);
            $next = (int) $pdo->query('SELECT COALESCE(MAX(`' . $colEsc . '`), 0) + 1 FROM `' . str_replace('`', '``', $tabla) . '`')->fetchColumn();
            $insertCols[] = '`' . $colEsc . '`';
            $insertVals[] = $next;
        } catch (Throwable) {
        }
    }

    $tieneIdInternoInsert = false;
    foreach ($insertCols as $ic) {
        if (strtolower(str_replace('`', '', $ic)) === 'id_interno') {
            $tieneIdInternoInsert = true;
            break;
        }
    }
    $colIdLogistico = $nombreColInsensitive($colsDisponibles, 'ID_Logistico');
    if ($colIdLogistico !== null && !$tieneIdInternoInsert) {
        try {
            $idLogEsc = str_replace('`', '``', $colIdLogistico);
            $mx = $pdo->query('SELECT COALESCE(MAX(CAST(`' . $idLogEsc . '` AS UNSIGNED)), 0) + 1 FROM `' . str_replace('`', '``', $tabla) . '` WHERE `' . $idLogEsc . '` REGEXP \'^[0-9]+$\'')->fetchColumn();
            $nid = ($mx !== null && $mx !== false && $mx !== '') ? (string) (int) $mx : substr(bin2hex(random_bytes(4)), 0, 8);
            $insertCols[] = '`' . $idLogEsc . '`';
            $insertVals[] = $nid;
        } catch (Throwable) {
        }
    }

    $colIdent = $nombreColInsensitive($colsDisponibles, 'Identificacion');
    if ($colIdent !== null) {
        $insertCols[] = '`' . str_replace('`', '``', $colIdent) . '`';
        $insertVals[] = $idConductor . '-' . $idVehiculo . '-' . substr($idOpl, 0, 12);
    }

    $insertCols[] = '`' . str_replace('`', '``', $cOpl) . '`';
    $insertVals[] = $idOpl;
    $insertCols[] = '`' . str_replace('`', '``', $cCond) . '`';
    $insertVals[] = $idConductor;
    $insertCols[] = '`' . str_replace('`', '``', $cVeh) . '`';
    $insertVals[] = $idVehiculo;

    if ($insertCols === []) {
        return false;
    }

    try {
        $sql = 'INSERT INTO `' . str_replace('`', '``', $tabla) . '` (' . implode(',', $insertCols) . ') VALUES (' . implode(',', array_fill(0, count($insertVals), '?')) . ')';
        $st = $pdo->prepare($sql);
        $st->execute($insertVals);

        return true;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, '1062') || str_contains($msg, 'Duplicate')) {
            return true;
        }

        return false;
    }
}

/**
 * @return null|array{tabla: string, col_opl: string, col_cond: string, col_veh: string, cols: list<string>}
 */
function logisticos_maestro_resolver_mapa(PDO $pdo): ?array
{
    $tabla = programacion_resolver_nombre_tabla($pdo, 'logisticos');
    if ($tabla === null) {
        return null;
    }
    try {
        $st = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $tabla) . '`');
        $cols = $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    } catch (Throwable) {
        return null;
    }
    if ($cols === []) {
        return null;
    }

    $pick = static function (array $cols, array $candidatos): ?string {
        $map = [];
        foreach ($cols as $c) {
            $map[strtolower((string) $c)] = (string) $c;
        }
        foreach ($candidatos as $want) {
            $k = strtolower($want);
            if (isset($map[$k])) {
                return $map[$k];
            }
        }

        return null;
    };

    $cOpl = $pick($cols, ['ID_OPL', 'OPL', 'Opl', 'Codigo_OPL', 'CodigoOPL', 'ID_Empresa_OPL']);
    $cCond = $pick($cols, ['ID_Conductor', 'Conductor', 'IDConducto', 'ID_Conducto']);
    $cVeh = $pick($cols, ['ID_Vehiculo', 'Vehiculo', 'Placa', 'ID_Vehículo']);
    if ($cOpl === null || $cCond === null || $cVeh === null) {
        return null;
    }

    return [
        'tabla' => $tabla,
        'col_opl' => $cOpl,
        'col_cond' => $cCond,
        'col_veh' => $cVeh,
        'cols' => array_map(static fn ($c): string => (string) $c, $cols),
    ];
}
