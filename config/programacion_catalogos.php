<?php
declare(strict_types=1);

/**
 * Catálogos operativos para programación de personal (LogiMeat).
 */

/** Actividades estándar (además existen filas en programacion_actividad_extra). */
function programacion_actividades_base(): array
{
    return [
        'Despacho',
        'Traslado a desposte',
        'Desembarco',
    ];
}

/**
 * Orden fijo de plantas en vista programación y formularios (flujo operativo).
 *
 * @return list<string>
 */
function programacion_plantas_orden_vista(): array
{
    return ['BENEFICIO', 'DESPOSTE', 'SUBPRODUCTOS', 'CELFRIO'];
}

/**
 * Clave interna => etiqueta visible (mismo orden que {@see programacion_plantas_orden_vista()}).
 *
 * @return array<string, string>
 */
function programacion_plantas_opciones(): array
{
    return [
        'BENEFICIO' => 'Beneficio',
        'DESPOSTE' => 'Desposte',
        'SUBPRODUCTOS' => 'Subproductos',
        'CELFRIO' => 'Celfrio',
    ];
}

/**
 * ID numérico en tabla `Planta` (maestro): 1=BENEFICIO, 2=DESPOSTE, 3=CELFRIO, 4=SUBPRODUCTOS.
 */
function programacion_id_maestro_desde_grupo(string $grupo): ?string
{
    return match ($grupo) {
        'BENEFICIO' => '1',
        'DESPOSTE' => '2',
        'CELFRIO' => '3',
        'SUBPRODUCTOS' => '4',
        default => null,
    };
}

/**
 * Grupo operativo para agrupar y mostrar. Prioriza `Planta_Operativa`; si falta, usa la columna
 * legada `Programacion.Planta` (1–4). No usa Destino.
 *
 * @return 'BENEFICIO'|'DESPOSTE'|'CELFRIO'|'SUBPRODUCTOS'|'_SIN'
 */
function programacion_grupo_desde_fila(array $r): string
{
    $po = trim((string) ($r['Planta_Operativa'] ?? ''));
    if (in_array($po, ['BENEFICIO', 'DESPOSTE', 'CELFRIO', 'SUBPRODUCTOS'], true)) {
        return $po;
    }

    $legacy = $r['Planta'] ?? null;
    if ($legacy === null || $legacy === '') {
        return '_SIN';
    }

    if (is_numeric($legacy)) {
        $n = (int) round((float) $legacy);
        if ($n === 1) {
            return 'BENEFICIO';
        }
        if ($n === 2) {
            return 'DESPOSTE';
        }
        if ($n === 3) {
            return 'CELFRIO';
        }
        if ($n === 4) {
            return 'SUBPRODUCTOS';
        }
    }

    return '_SIN';
}

function programacion_etiqueta_planta_grupo(string $grupo): string
{
    $m = programacion_plantas_opciones();

    return $m[$grupo] ?? ($grupo === '_SIN' ? 'Sin planta' : $grupo);
}

/**
 * Productos permitidos por planta (clave BENEFICIO|DESPOSTE|CELFRIO).
 *
 * @return array<string, list<string>>
 */
function programacion_productos_por_planta(): array
{
    return [
        'BENEFICIO' => [
            'Canales',
            'Visceras',
            'Carne industrial',
            'Lenguas',
            'Esofagos',
        ],
        'DESPOSTE' => [
            'Producto despostado',
            'Aprovechamientos',
            'Visceras',
        ],
        'SUBPRODUCTOS' => [
            'Subproductos',
            'Aprovechamientos',
            'Visceras',
            'Producto despostado',
        ],
        'CELFRIO' => [
            'Visceras acondicionadas',
            'Productos despostado',
        ],
    ];
}

function programacion_es_producto_valido(string $plantaKey, string $producto): bool
{
    $p = programacion_productos_por_planta();
    if (!isset($p[$plantaKey])) {
        return false;
    }

    return in_array($producto, $p[$plantaKey], true);
}

/**
 * Nombres de tipo de cuarteo en el maestro `tipo_de_cuarteo` (módulo Administración).
 *
 * @return list<string>
 */
function programacion_tipos_cuarteo_desde_bd(?PDO $pdo): array
{
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $rows = $pdo->query(
            'SELECT Tipo_Cuarteo FROM tipo_de_cuarteo
             WHERE Tipo_Cuarteo IS NOT NULL AND TRIM(Tipo_Cuarteo) <> \'\'
             ORDER BY Tipo_Cuarteo ASC'
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($rows as $v) {
            $t = trim((string) $v);
            if ($t === '') {
                continue;
            }
            $k = mb_strtoupper($t, 'UTF-8');
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $t;
        }

        return $out;
    } catch (Throwable) {
        return [];
    }
}

/**
 * Tipo de cuarteo por planta. Base operativa (Beneficio) + tipos del maestro Administración.
 * Desposte/Celfrio/Subproductos: vacíos salvo que existan en BD (se exponen en Beneficio).
 *
 * @return array<string, list<string>>
 */
function programacion_tipos_cuarteo_por_planta(?PDO $pdo = null): array
{
    $base = [
        'BENEFICIO' => ['REGIONAL', 'PISTOLA'],
        'DESPOSTE' => [],
        'SUBPRODUCTOS' => [],
        'CELFRIO' => [],
    ];
    $fromDb = programacion_tipos_cuarteo_desde_bd($pdo);
    if ($fromDb === []) {
        return $base;
    }
    $merged = $base['BENEFICIO'];
    $seen = [];
    foreach ($merged as $x) {
        $seen[mb_strtoupper($x, 'UTF-8')] = true;
    }
    foreach ($fromDb as $t) {
        $k = mb_strtoupper($t, 'UTF-8');
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $merged[] = $t;
    }
    $base['BENEFICIO'] = $merged;

    return $base;
}

/** True si el tipo está permitido para la planta (lista fija + maestro BD). */
function programacion_es_tipo_cuarteo_valido(string $plantaKey, string $tipo, ?PDO $pdo = null): bool
{
    $tipo = trim($tipo);
    if ($tipo === '') {
        return true;
    }
    $list = programacion_tipos_cuarteo_por_planta($pdo)[$plantaKey] ?? [];
    foreach ($list as $x) {
        if (strcasecmp(trim((string) $x), $tipo) === 0) {
            return true;
        }
    }

    return false;
}

/** Timestamp UNIX de Fecha_de_Operacion d/m/Y o null si no parsea. */
function programacion_ts_fecha_operacion(?string $dmy): ?int
{
    $dmy = trim((string) $dmy);
    if ($dmy === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('d/m/Y', $dmy);

    return $dt instanceof DateTime ? (int) $dt->format('U') : null;
}

/** Segundos desde medianoche para Hora HH:MM o HH:MM:SS; null si vacío o inválido. */
function programacion_ts_hora_dia(?string $hora): ?int
{
    $hora = trim((string) $hora);
    if ($hora === '') {
        return null;
    }
    foreach (['H:i:s', 'H:i'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $hora);
        if ($dt instanceof DateTime) {
            return (int) $dt->format('H') * 3600 + (int) $dt->format('i') * 60 + (int) $dt->format('s');
        }
    }

    return null;
}

/**
 * Ordena filas de programación por fecha de operación (asc), hora (asc), id_interno (desc).
 *
 * @param list<array<string, mixed>> $filas
 * @return list<array<string, mixed>>
 */
function programacion_ordenar_filas_por_fecha_hora(array $filas): array
{
    usort($filas, static function (array $a, array $b): int {
        $fa = programacion_ts_fecha_operacion($a['Fecha_de_Operacion'] ?? null);
        $fb = programacion_ts_fecha_operacion($b['Fecha_de_Operacion'] ?? null);
        if ($fa !== $fb) {
            if ($fa === null) {
                return 1;
            }
            if ($fb === null) {
                return -1;
            }

            return $fa <=> $fb;
        }

        $ha = programacion_ts_hora_dia($a['Hora'] ?? null);
        $hb = programacion_ts_hora_dia($b['Hora'] ?? null);
        if ($ha !== $hb) {
            if ($ha === null) {
                return 1;
            }
            if ($hb === null) {
                return -1;
            }

            return $ha <=> $hb;
        }

        return (int) ($b['id_interno'] ?? 0) <=> (int) ($a['id_interno'] ?? 0);
    });

    return $filas;
}

/** Icono según nombre de actividad (programación operativa). */
function programacion_icono_actividad(string $nombreActividad): string
{
    $u = mb_strtoupper(trim($nombreActividad));
    if ($u === '') {
        return '📋';
    }
    if (str_contains($u, 'DESPACHO')) {
        return '🚚';
    }
    if (str_contains($u, 'TRASLADO')) {
        return '🪝';
    }
    if (str_contains($u, 'DESEMBARCO')) {
        return '⚓';
    }
    if (str_contains($u, 'INVENTARIO')) {
        return '📦';
    }

    return '📋';
}

/**
 * @param list<string> $actividadesExtra desde BD (solo Nombre)
 */
function programacion_listar_actividades(PDO $pdo): array
{
    $base = programacion_actividades_base();
    $extra = [];
    try {
        $q = $pdo->query('SELECT Nombre FROM programacion_actividad_extra ORDER BY Nombre');
        if ($q) {
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                $n = trim((string) ($r['Nombre'] ?? ''));
                if ($n !== '') {
                    $extra[] = $n;
                }
            }
        }
    } catch (Throwable) {
        // tabla aún no existe
    }

    $todo = array_merge($base, $extra);
    $todo = array_values(array_unique($todo));
    sort($todo, SORT_NATURAL | SORT_FLAG_CASE);

    return $todo;
}

/** Próximo `AUTO_INCREMENT` de Programación (vista previa; se confirma al insertar). */
function programacion_siguiente_id_interno_preview(PDO $pdo): ?int
{
    try {
        foreach (['Programacion', 'programacion'] as $tabla) {
            $st = $pdo->query('SHOW TABLE STATUS LIKE ' . $pdo->quote($tabla));
            if ($st) {
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['Auto_increment']) && $row['Auto_increment'] !== null) {
                    return (int) $row['Auto_increment'];
                }
            }
        }
    } catch (Throwable) {
    }

    return null;
}

/** Genera un ID de programación como en datos legado (8 hex). */
function programacion_generar_id_programacion(): string
{
    return substr(bin2hex(random_bytes(4)), 0, 8);
}

function programacion_id_programacion_valido(string $id): bool
{
    return (bool) preg_match('/^[a-fA-F0-9]{8}$/', trim($id));
}
