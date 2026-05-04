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
 * Clave interna => etiqueta visible.
 *
 * @return array<string, string>
 */
function programacion_plantas_opciones(): array
{
    return [
        'BENEFICIO' => 'Beneficio',
        'DESPOSTE' => 'Desposte',
        'CELFRIO' => 'Celfrio',
        'SUBPRODUCTOS' => 'Subproductos',
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
        'CELFRIO' => [
            'Visceras acondicionadas',
            'Productos despostado',
        ],
        'SUBPRODUCTOS' => [
            'Subproductos',
            'Aprovechamientos',
            'Visceras',
            'Producto despostado',
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
 * Tipo de cuarteo (principalmente Beneficio). Desposte/Celfrio suelen ir vacíos.
 *
 * @return array<string, list<string>>
 */
function programacion_tipos_cuarteo_por_planta(): array
{
    return [
        'BENEFICIO' => ['REGIONAL', 'PISTOLA'],
        'DESPOSTE' => [],
        'CELFRIO' => [],
        'SUBPRODUCTOS' => [],
    ];
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
