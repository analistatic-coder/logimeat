<?php
declare(strict_types=1);

/**
 * Resuelve el nombre real de la tabla en el servidor (mayúsculas/minúsculas).
 */
function programacion_resolver_nombre_tabla(PDO $pdo, string $nombreLogico): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $q = $pdo->query('SHOW TABLES');
            if ($q) {
                while ($row = $q->fetch(PDO::FETCH_NUM)) {
                    $n = (string) ($row[0] ?? '');
                    if ($n !== '') {
                        $cache[strtolower($n)] = $n;
                    }
                }
            }
        } catch (Throwable) {
            $cache = [];
        }
    }

    $k = strtolower($nombreLogico);

    return $cache[$k] ?? null;
}

/**
 * Relación OPL ↔ conductor ↔ vehículo para nueva programación.
 * Intenta varias formas habituales en el esquema legado (tabla puente `logisticos`,
 * columnas en `opl`, o histórico en `Programacion`).
 *
 * @return array{
 *   porOpl: array<string, array{c:list<string>, v:list<string>}>,
 *   usaFiltro: bool,
 *   fuente: ?string
 * }
 */
function programacion_opl_relaciones(PDO $pdo, array $oplsMaestro): array
{
    $porOpl = [];
    $fuente = null;

    $columnas = static function (PDO $pdo, string $tabla): array {
        try {
            $st = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $tabla) . '`');

            return $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        } catch (Throwable) {
            return [];
        }
    };

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

    $resolverConductor = static function (PDO $pdo, string $raw): ?string {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            $st = $pdo->prepare(
                'SELECT ID_Conductor FROM conductor
                 WHERE CAST(ID_Conductor AS CHAR) = ? OR TRIM(CAST(ID_Conductor AS CHAR)) = ?
                    OR TRIM(Conductor) = ? LIMIT 1'
            );
            $st->execute([$raw, $raw, $raw]);
            $id = $st->fetchColumn();
            if ($id !== false && $id !== null && trim((string) $id) !== '') {
                return trim((string) $id);
            }
        } catch (Throwable) {
        }

        return null;
    };

    $resolverVehiculo = static function (PDO $pdo, string $raw): ?string {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            $st = $pdo->prepare(
                'SELECT ID_Vehiculo FROM vehiculo
                 WHERE CAST(ID_Vehiculo AS CHAR) = ? OR TRIM(CAST(ID_Vehiculo AS CHAR)) = ?
                    OR TRIM(Vehiculo) = ? LIMIT 1'
            );
            $st->execute([$raw, $raw, $raw]);
            $id = $st->fetchColumn();
            if ($id !== false && $id !== null && trim((string) $id) !== '') {
                return trim((string) $id);
            }
        } catch (Throwable) {
        }

        return null;
    };

    $acumular = static function (array &$por, string $oplRaw, ?string $cid, ?string $vid): void {
        $k = trim($oplRaw);
        if ($k === '') {
            return;
        }
        if (!isset($por[$k])) {
            $por[$k] = ['c' => [], 'v' => []];
        }
        if ($cid !== null && $cid !== '') {
            $por[$k]['c'][$cid] = true;
        }
        if ($vid !== null && $vid !== '') {
            $por[$k]['v'][$vid] = true;
        }
    };

    // 1) Tabla logisticos (puente)
    $tLog = programacion_resolver_nombre_tabla($pdo, 'logisticos');
    if ($tLog !== null) {
        $cols = $columnas($pdo, $tLog);
        if ($cols !== []) {
            $cOpl = $pick($cols, ['ID_OPL', 'OPL', 'Opl', 'Codigo_OPL', 'CodigoOPL', 'ID_Empresa_OPL']);
            $cCond = $pick($cols, ['ID_Conductor', 'Conductor', 'IDConducto', 'ID_Conducto']);
            $cVeh = $pick($cols, ['ID_Vehiculo', 'Vehiculo', 'Placa', 'ID_Vehículo']);
            if ($cOpl !== null && ($cCond !== null || $cVeh !== null)) {
                try {
                    $sel = array_filter([$cOpl, $cCond, $cVeh]);
                    $sql = 'SELECT DISTINCT `' . implode('`,`', array_map(static fn ($c) => str_replace('`', '``', $c), $sel)) . '` FROM `' . str_replace('`', '``', $tLog) . '`';
                    $q = $pdo->query($sql);
                    if ($q) {
                        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                            $oplRaw = trim((string) ($row[$cOpl] ?? ''));
                            $rawC = $cCond !== null ? trim((string) ($row[$cCond] ?? '')) : '';
                            $rawV = $cVeh !== null ? trim((string) ($row[$cVeh] ?? '')) : '';
                            $cid = $rawC !== '' ? $resolverConductor($pdo, $rawC) : null;
                            $vid = $rawV !== '' ? $resolverVehiculo($pdo, $rawV) : null;
                            $acumular($porOpl, $oplRaw, $cid, $vid);
                        }
                        if ($porOpl !== []) {
                            $fuente = $tLog;
                        }
                    }
                } catch (Throwable) {
                }
            }
        }
    }

    // 2) Columnas en tabla opl
    $tOpl = programacion_resolver_nombre_tabla($pdo, 'opl');
    if ($porOpl === [] && $tOpl !== null) {
        $cols = $columnas($pdo, $tOpl);
        $cOpl = $pick($cols, ['ID_OPL', 'OPL']);
        $cCond = $pick($cols, ['ID_Conductor', 'Conductor', 'Conductor_ID', 'ID_Conducto']);
        $cVeh = $pick($cols, ['ID_Vehiculo', 'Vehiculo', 'Vehiculo_ID', 'Placa', 'ID_Vehículo']);
        if ($cOpl !== null && ($cCond !== null || $cVeh !== null)) {
            try {
                $sel = array_filter([$cOpl, $cCond, $cVeh]);
                $sql = 'SELECT DISTINCT `' . implode('`,`', array_map(static fn ($c) => str_replace('`', '``', $c), $sel)) . '` FROM `' . str_replace('`', '``', $tOpl) . '`';
                $q = $pdo->query($sql);
                if ($q) {
                    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                        $oplRaw = trim((string) ($row[$cOpl] ?? ''));
                        $rawC = $cCond !== null ? trim((string) ($row[$cCond] ?? '')) : '';
                        $rawV = $cVeh !== null ? trim((string) ($row[$cVeh] ?? '')) : '';
                        $cid = $rawC !== '' ? $resolverConductor($pdo, $rawC) : null;
                        $vid = $rawV !== '' ? $resolverVehiculo($pdo, $rawV) : null;
                        $acumular($porOpl, $oplRaw, $cid, $vid);
                    }
                    if ($porOpl !== []) {
                        $fuente = 'opl';
                    }
                }
            } catch (Throwable) {
            }
        }
    }

    // 3) Histórico Programacion
    if ($porOpl === []) {
        $tProg = programacion_resolver_nombre_tabla($pdo, 'programacion');
        if ($tProg === null) {
            $tProg = 'Programacion';
        }
        try {
            $sql = 'SELECT DISTINCT
                        TRIM(CAST(p.OPL AS CHAR)) AS opl_k,
                        TRIM(CAST(p.Conductor AS CHAR)) AS c_raw,
                        TRIM(CAST(p.Vehiculo AS CHAR)) AS v_raw
                    FROM `' . str_replace('`', '``', $tProg) . '` p
                    WHERE TRIM(COALESCE(p.OPL, \'\')) <> \'\'
                      AND (TRIM(COALESCE(p.Conductor, \'\')) <> \'\' OR TRIM(COALESCE(p.Vehiculo, \'\')) <> \'\')';
            $q = $pdo->query($sql);
            if ($q) {
                while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                    $oplRaw = trim((string) ($row['opl_k'] ?? ''));
                    $rawC = trim((string) ($row['c_raw'] ?? ''));
                    $rawV = trim((string) ($row['v_raw'] ?? ''));
                    $cid = $rawC !== '' ? $resolverConductor($pdo, $rawC) : null;
                    $vid = $rawV !== '' ? $resolverVehiculo($pdo, $rawV) : null;
                    $acumular($porOpl, $oplRaw, $cid, $vid);
                }
                if ($porOpl !== []) {
                    $fuente = 'Programacion';
                }
            }
        } catch (Throwable) {
        }
    }

    // Normalizar a claves maestras ID_OPL del desplegable
    $final = [];
    foreach ($oplsMaestro as $o) {
        $id = trim((string) ($o['ID_OPL'] ?? ''));
        if ($id === '') {
            continue;
        }
        $nom = trim((string) ($o['OPL'] ?? ''));
        $cSet = [];
        $vSet = [];
        foreach ([$id, $nom] as $k) {
            if ($k === '' || !isset($porOpl[$k])) {
                continue;
            }
            foreach ($porOpl[$k]['c'] as $cid => $_) {
                $cSet[$cid] = true;
            }
            foreach ($porOpl[$k]['v'] as $vid => $_) {
                $vSet[$vid] = true;
            }
        }
        if ($cSet !== [] || $vSet !== []) {
            $final[$id] = [
                'c' => array_keys($cSet),
                'v' => array_keys($vSet),
            ];
        }
    }

    $usaFiltro = $final !== [];

    return [
        'porOpl' => $final,
        'usaFiltro' => $usaFiltro,
        'fuente' => $fuente,
    ];
}

/**
 * @param array{c:list<string>,v:list<string>}|null $bucket
 */
function programacion_opl_permite_conductor_vehiculo(?array $bucket, string $conductor, string $vehiculo): bool
{
    if ($bucket === null) {
        return true;
    }
    $c = trim($conductor);
    $v = trim($vehiculo);
    $allowC = array_values(array_filter(
        array_map(static fn ($x): string => trim((string) $x), $bucket['c'] ?? []),
        static fn (string $x): bool => $x !== ''
    ));
    $allowV = array_values(array_filter(
        array_map(static fn ($x): string => trim((string) $x), $bucket['v'] ?? []),
        static fn (string $x): bool => $x !== ''
    ));
    if ($c !== '' && $allowC !== [] && !in_array($c, $allowC, true)) {
        return false;
    }
    if ($v !== '' && $allowV !== [] && !in_array($v, $allowV, true)) {
        return false;
    }

    return true;
}
