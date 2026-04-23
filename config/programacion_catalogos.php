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
    ];
}

/**
 * ID numérico en tabla `Planta` (maestro nivel 5): 1=BENEFICIO, 2=DESPOSTE, 3=CELFRIO.
 */
function programacion_id_maestro_desde_grupo(string $grupo): ?string
{
    return match ($grupo) {
        'BENEFICIO' => '1',
        'DESPOSTE' => '2',
        'CELFRIO' => '3',
        default => null,
    };
}

/**
 * Grupo operativo para agrupar y mostrar. Prioriza `Planta_Operativa`; si falta, usa la columna
 * legada `Programacion.Planta` (1/2/3 como en Programacion.sql). No usa Destino.
 *
 * @return 'BENEFICIO'|'DESPOSTE'|'CELFRIO'|'_SIN'
 */
function programacion_grupo_desde_fila(array $r): string
{
    $po = trim((string) ($r['Planta_Operativa'] ?? ''));
    if (in_array($po, ['BENEFICIO', 'DESPOSTE', 'CELFRIO'], true)) {
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
    ];
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
