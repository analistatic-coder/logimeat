<?php 
declare(strict_types=1);

require_once 'auth.php'; 
require_once 'conexion.php';
require_once __DIR__ . '/config/programacion_catalogos.php';

$puedeSeleccionColumnas = lm_es_admin();

$desde = isset($_GET['desde']) ? trim((string) $_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim((string) $_GET['hasta']) : '';
$buscar = isset($_GET['buscar']) ? trim((string) $_GET['buscar']) : '';

$plantasEt = programacion_plantas_opciones();

/** Escapa comodines LIKE en MySQL. */
function programacion_like_pattern(string $texto): string
{
    $esc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);

    return '%' . $esc . '%';
}

/** Texto precomputado para filtrar en el navegador sin leer innerText (evita bloqueos). */
function programacion_blob_busqueda_local(array $r): string
{
    $g = programacion_grupo_desde_fila($r);
    $etPlanta = programacion_etiqueta_planta_grupo($g);
    $parts = [
        (string) ($r['id_interno'] ?? ''),
        (string) ($r['ID_Programacion'] ?? ''),
        (string) ($r['Fecha_de_Registro'] ?? ''),
        (string) ($r['NomSolicitante'] ?? ''),
        (string) ($r['NomMedioCom'] ?? ''),
        (string) ($r['Estado'] ?? ''),
        (string) ($r['NomCli'] ?? ''),
        (string) ($r['Cliente'] ?? ''),
        (string) ($r['Planta'] ?? ''),
        (string) ($r['NomPlantaMaestro'] ?? ''),
        $etPlanta,
        (string) ($r['NomAct'] ?? ''),
        (string) ($r['Fecha_de_Operacion'] ?? ''),
        (string) ($r['Hora'] ?? ''),
        (string) ($r['NomProdDisplay'] ?? ''),
        (string) ($r['NomTipoCuarteo'] ?? ''),
        (string) ($r['Tipo_de_Cuarteo'] ?? ''),
        (string) ($r['Lote'] ?? ''),
        (string) ($r['Cantidad'] ?? ''),
        (string) ($r['NomCiudad'] ?? ''),
        (string) ($r['Destino'] ?? ''),
        (string) ($r['Ubicacion'] ?? ''),
        (string) ($r['NomOPL'] ?? ''),
        (string) ($r['OPL'] ?? ''),
        (string) ($r['NomConductor'] ?? ''),
        (string) ($r['PlacaVeh'] ?? ''),
        (string) ($r['Vehiculo'] ?? ''),
        (string) ($r['Observaciones'] ?? ''),
        (string) ($r['Cantidad_Correcta'] ?? ''),
        (string) ($r['Producto_Correcto'] ?? ''),
        (string) ($r['Entrega_a_Tiempo'] ?? ''),
        (string) ($r['Direccion_Correcta'] ?? ''),
        (string) ($r['Pedido_Perfecto'] ?? ''),
        (string) ($r['Estado_Actividad'] ?? ''),
        (string) ($r['Telefono'] ?? ''),
    ];

    return mb_strtoupper(implode(' ', $parts), 'UTF-8');
}

/** Ruta base URL del proyecto (vacío si la app está en la raíz del host; p. ej. /logimeat). */
function programacion_base_web_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/programacion.php');
    $script = str_replace('\\', '/', $script);
    $dir = dirname($script);
    if ($dir === '.' || $dir === DIRECTORY_SEPARATOR || $dir === '/') {
        return '';
    }

    return rtrim($dir, '/');
}

/** Construye URL a un recurso bajo la misma carpeta que programacion.php (assets, etc.). */
function programacion_asset_url(string $rutaRelAlProyecto): string
{
    $base = programacion_base_web_path();
    $ruta = ltrim(str_replace('\\', '/', $rutaRelAlProyecto), '/');

    return ($base !== '' ? $base . '/' : '') . $ruta;
}

/** URL a un archivo en assets/ cuando el nombre tiene espacios o caracteres especiales. */
function programacion_assets_named_url(string $nombreArchivo, int $mtime): string
{
    $base = programacion_base_web_path();
    $arch = rawurlencode($nombreArchivo);
    $pre = ($base !== '' ? $base . '/' : '') . 'assets/' . $arch;

    return $pre . '?v=' . (string) $mtime;
}

/**
 * Logo Colbeef: busca PNG/ICO en assets/ (nombres estándar o el ICO exportado);
 * si no hay imagen usable, SVG local embebido o marcador por defecto.
 */
function programacion_markup_logo_colbeef(string $dirAssets): string
{
    $h = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    };

    $dirAssets = rtrim($dirAssets, '\\/');

    /** @var list<string> */
    $prioridadNombre = [
        'colbeef-logo.png',
        'colbeef-logo.ico',
        // Archivo habitual al exportar desde diseño:
        'EXPORTADAS_Mesa-de-trabajo-1 (3).ico',
    ];

    foreach ($prioridadNombre as $nom) {
        $abs = $dirAssets . DIRECTORY_SEPARATOR . $nom;
        if (!is_readable($abs)) {
            continue;
        }
        $v = (int) (@filemtime($abs) ?: 1);
        $src = programacion_assets_named_url($nom, $v);

        return '<img src="' . $h($src) . '" width="200" height="40" alt="Colbeef®" class="h-8 sm:h-9 w-auto max-w-[200px] object-contain object-left select-none" loading="eager" decoding="async">';
    }

    $absSvg = $dirAssets . DIRECTORY_SEPARATOR . 'colbeef-logo.svg';
    $svgRaw = '';
    if (is_readable($absSvg)) {
        $svgRaw = trim((string) @file_get_contents($absSvg));
    }
    if ($svgRaw === '') {
        $svgRaw = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 56"><text x="10" y="40" fill="#00A651" font-family="system-ui,sans-serif" font-weight="800" font-size="34" letter-spacing="-0.03em"><tspan fill="#00A651">Col</tspan><tspan fill="#DA291C">beef</tspan></text><text x="234" y="16" fill="#DA291C" font-family="system-ui,sans-serif" font-weight="700" font-size="12">®</text></svg>';
    }

    return '<span class="prog-logo-svg-inline block" role="img" aria-label="Colbeef">' . $svgRaw . '</span>';
}

$sqlJoins = '
        LEFT JOIN Clientes c
            ON CAST(p.Cliente AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(c.ID_Cliente AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
        LEFT JOIN producto pr
            ON CAST(p.Producto AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(pr.ID_Producto AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
        LEFT JOIN actividad act
            ON CAST(p.Actividad AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(act.ID_Actividad AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
        LEFT JOIN vehiculo vh
            ON CAST(p.Vehiculo AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(vh.ID_Vehiculo AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
        LEFT JOIN planta plm ON p.Planta = plm.ID_Planta
        LEFT JOIN solicitante sol
            ON CAST(p.Solicitante AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(sol.ID_Solicitante AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
        LEFT JOIN medio_de_comunicacion mdc
            ON CAST(p.Medio_de_Comunicacion AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(mdc.ID_Medio_Comunicacion AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
        LEFT JOIN municipio mun ON p.Ciudad = mun.c
        LEFT JOIN tipo_de_cuarteo tc
            ON CAST(p.Tipo_de_Cuarteo AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(tc.ID_Tipo_Cuarteo AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
        LEFT JOIN opl oplm ON (
            p.OPL = oplm.ID_OPL
            OR CAST(p.OPL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(oplm.ID_OPL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
        )
        LEFT JOIN conductor cond
            ON CAST(p.Conductor AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             = CAST(cond.ID_Conductor AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci';

$params = [];
$where = 'WHERE 1=1';
if ($desde !== '' && $hasta !== '') {
    $where .= " AND STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y') BETWEEN ? AND ?";
    $params[] = $desde;
    $params[] = $hasta;
}

if ($buscar !== '') {
    $term = programacion_like_pattern($buscar);
    $orParts = [];
    $buscarParams = [];
    if (ctype_digit($buscar)) {
        $orParts[] = 'p.id_interno = ?';
        $buscarParams[] = (int) $buscar;
    }
    $likeExprs = [
        'COALESCE(p.ID_Programacion,\'\')',
        'COALESCE(p.Destino,\'\')',
        'COALESCE(p.Observaciones,\'\')',
        'COALESCE(p.OPL,\'\')',
        'COALESCE(p.Conductor,\'\')',
        'COALESCE(p.Vehiculo,\'\')',
        'COALESCE(p.Producto,\'\')',
        'COALESCE(p.Tipo_de_Cuarteo,\'\')',
        'COALESCE(p.Lote,\'\')',
        'COALESCE(p.Ubicacion,\'\')',
        'COALESCE(p.Telefono,\'\')',
        'COALESCE(p.Estado,\'\')',
        'COALESCE(p.Estado_Actividad,\'\')',
        'COALESCE(p.Fecha_de_Operacion,\'\')',
        'COALESCE(p.Fecha_de_Registro,\'\')',
        'COALESCE(p.Hora,\'\')',
        'COALESCE(p.Medio_de_Comunicacion,\'\')',
        'COALESCE(p.Solicitante,\'\')',
        'CAST(p.id_interno AS CHAR)',
        'CAST(p.Planta AS CHAR)',
        'CAST(p.Ciudad AS CHAR)',
        'CAST(p.Cliente AS CHAR)',
        'COALESCE(CAST(p.Cantidad AS CHAR),\'\')',
        'COALESCE(p.Cantidad_Correcta,\'\')',
        'COALESCE(p.Producto_Correcto,\'\')',
        'COALESCE(p.Entrega_a_Tiempo,\'\')',
        'COALESCE(p.Direccion_Correcta,\'\')',
        'COALESCE(p.Pedido_Perfecto,\'\')',
        'COALESCE(c.Cliente,\'\')',
        'COALESCE(pr.Producto,\'\')',
        'COALESCE(act.Actividad,\'\')',
        'COALESCE(vh.Vehiculo,\'\')',
        'COALESCE(sol.Solicitante,\'\')',
        'COALESCE(mdc.Medio_de_Comunicacion,\'\')',
        'COALESCE(mun.Municipio,\'\')',
        'COALESCE(tc.Tipo_Cuarteo,\'\')',
        'COALESCE(oplm.OPL,\'\')',
        'COALESCE(cond.Conductor,\'\')',
        'COALESCE(plm.Planta,\'\')',
    ];
    foreach ($likeExprs as $expr) {
        $orParts[] = $expr . ' LIKE ?';
        $buscarParams[] = $term;
    }
    $where .= ' AND (' . implode(' OR ', $orParts) . ')';
    $params = array_merge($params, $buscarParams);
}

$limitSql = '';
$aviso_limite_default = false;
$aviso_limite_buscar = false;
if ($buscar === '' && !($desde !== '' && $hasta !== '')) {
    $sqlCount = 'SELECT COUNT(DISTINCT p.id_interno) FROM Programacion p' . $sqlJoins . ' ' . $where;
    $stc = $pdo->prepare($sqlCount);
    $stc->execute($params);
    $total_registros = (int) $stc->fetchColumn();
} else {
    $sqlCount = 'SELECT COUNT(DISTINCT p.id_interno) FROM Programacion p' . $sqlJoins . ' ' . $where;
    $stc = $pdo->prepare($sqlCount);
    $stc->execute($params);
    $total_registros = (int) $stc->fetchColumn();
}

if ($buscar === '' && !($desde !== '' && $hasta !== '')) {
    $limitSql = ' LIMIT 2000';
    $aviso_limite_default = $total_registros > 2000;
} elseif ($buscar !== '') {
    $limitSql = ' LIMIT 8000';
    $aviso_limite_buscar = $total_registros > 8000;
}

$sql = "SELECT p.*, 
               c.Cliente AS NomCli,
               COALESCE(pr.Producto, p.Producto) AS NomProdDisplay,
               act.Actividad AS NomAct,
               vh.Vehiculo AS PlacaVeh,
               plm.Planta AS NomPlantaMaestro,
               COALESCE(sol.Solicitante, p.Solicitante) AS NomSolicitante,
               mdc.Medio_de_Comunicacion AS NomMedioCom,
               mun.Municipio AS NomCiudad,
               tc.Tipo_Cuarteo AS NomTipoCuarteo,
               oplm.OPL AS NomOPL,
               cond.Conductor AS NomConductor
        FROM Programacion p 
        $sqlJoins
        $where
        ORDER BY
            CASE
                WHEN STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y') = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 0
                ELSE 1
            END ASC,
            CASE
                WHEN STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y') = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                     AND NULLIF(TRIM(p.Hora), '') IS NULL THEN 1
                WHEN STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y') = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 0
                ELSE 0
            END ASC,
            CASE
                WHEN STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y') = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    THEN COALESCE(
                        STR_TO_DATE(NULLIF(TRIM(p.Hora), ''), '%H:%i:%s'),
                        STR_TO_DATE(NULLIF(TRIM(p.Hora), ''), '%H:%i')
                    )
                ELSE NULL
            END ASC,
            CASE
                WHEN STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y') = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN NULL
                ELSE STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Registro), ''), '%d/%m/%Y %H:%i:%s')
            END DESC,
            CASE
                WHEN STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y') = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN NULL
                ELSE STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y')
            END DESC,
            p.id_interno DESC
        $limitSql";

$st = $pdo->prepare($sql);
$st->execute($params);
$todas = $st->fetchAll(PDO::FETCH_ASSOC);
$filas_cargadas = count($todas);

$ordenGrupo = array_merge(programacion_plantas_orden_vista(), ['_SIN']);
$grupos = [];
foreach ($ordenGrupo as $k) {
    $grupos[$k] = [];
}
foreach ($todas as $r) {
    $g = programacion_grupo_desde_fila($r);
    $grupos[$g][] = $r;
}

// En vista por defecto (sin filtros/buscar): por cada grupo mostrar
// 1) solo registros de mañana; 2) si no hay, dejar solo el más reciente del grupo.
$modoPorDefecto = ($buscar === '' && !($desde !== '' && $hasta !== ''));
if ($modoPorDefecto) {
    $fechaManana = date('d/m/Y', strtotime('+1 day'));
    $condGrupoSql = [
        'BENEFICIO' => "(p.Planta_Operativa = 'BENEFICIO' OR (TRIM(COALESCE(p.Planta_Operativa,'')) = '' AND CAST(p.Planta AS CHAR) = '1'))",
        'DESPOSTE' => "(p.Planta_Operativa = 'DESPOSTE' OR (TRIM(COALESCE(p.Planta_Operativa,'')) = '' AND CAST(p.Planta AS CHAR) = '2'))",
        'SUBPRODUCTOS' => "(p.Planta_Operativa = 'SUBPRODUCTOS' OR (TRIM(COALESCE(p.Planta_Operativa,'')) = '' AND CAST(p.Planta AS CHAR) = '4'))",
        'CELFRIO' => "(p.Planta_Operativa = 'CELFRIO' OR (TRIM(COALESCE(p.Planta_Operativa,'')) = '' AND CAST(p.Planta AS CHAR) = '3'))",
    ];
    foreach ($ordenGrupo as $k) {
        $filasGrupo = $grupos[$k] ?? [];
        if ($filasGrupo !== []) {
            $deManana = [];
            foreach ($filasGrupo as $fila) {
                if (trim((string) ($fila['Fecha_de_Operacion'] ?? '')) === $fechaManana) {
                    $deManana[] = $fila;
                }
            }
            if ($deManana !== []) {
                $grupos[$k] = $deManana;
            } else {
                // Si no hay de mañana, mostrar TODAS las de la última fecha disponible del grupo.
                $fechaUltima = trim((string) (reset($filasGrupo)['Fecha_de_Operacion'] ?? ''));
                if ($fechaUltima === '') {
                    $grupos[$k] = [reset($filasGrupo)];
                } else {
                    $ultimasDelDia = [];
                    foreach ($filasGrupo as $fila) {
                        if (trim((string) ($fila['Fecha_de_Operacion'] ?? '')) === $fechaUltima) {
                            $ultimasDelDia[] = $fila;
                        }
                    }
                    $grupos[$k] = $ultimasDelDia !== [] ? $ultimasDelDia : [reset($filasGrupo)];
                }
            }
            continue;
        }
        if (!isset($condGrupoSql[$k])) {
            continue;
        }
        $condGrupoPx = str_replace('p.', 'px.', $condGrupoSql[$k]);
        // Respaldo: si no quedó ninguna fila cargada para el grupo, traer TODAS las filas de su última fecha.
        $sqlUltimaGrupo = "SELECT p.*,
                   c.Cliente AS NomCli,
                   COALESCE(pr.Producto, p.Producto) AS NomProdDisplay,
                   act.Actividad AS NomAct,
                   vh.Vehiculo AS PlacaVeh,
                   plm.Planta AS NomPlantaMaestro,
                   COALESCE(sol.Solicitante, p.Solicitante) AS NomSolicitante,
                   mdc.Medio_de_Comunicacion AS NomMedioCom,
                   mun.Municipio AS NomCiudad,
                   tc.Tipo_Cuarteo AS NomTipoCuarteo,
                   oplm.OPL AS NomOPL,
                   cond.Conductor AS NomConductor
            FROM Programacion p
            $sqlJoins
            WHERE {$condGrupoSql[$k]}
              AND STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Operacion), ''), '%d/%m/%Y') = (
                SELECT MAX(STR_TO_DATE(NULLIF(TRIM(px.Fecha_de_Operacion), ''), '%d/%m/%Y'))
                FROM Programacion px
                WHERE {$condGrupoPx}
              )
            ORDER BY
                CASE WHEN NULLIF(TRIM(p.Hora), '') IS NULL THEN 1 ELSE 0 END ASC,
                COALESCE(
                    STR_TO_DATE(NULLIF(TRIM(p.Hora), ''), '%H:%i:%s'),
                    STR_TO_DATE(NULLIF(TRIM(p.Hora), ''), '%H:%i')
                ) ASC,
                STR_TO_DATE(NULLIF(TRIM(p.Fecha_de_Registro), ''), '%d/%m/%Y %H:%i:%s') DESC,
                p.id_interno DESC";
        try {
            $stUlt = $pdo->query($sqlUltimaGrupo);
            $rowsUlt = $stUlt ? $stUlt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (is_array($rowsUlt) && $rowsUlt !== []) {
                $grupos[$k] = $rowsUlt;
            }
        } catch (Throwable) {
        }
    }
}

foreach ($ordenGrupo as $gk) {
    if (($grupos[$gk] ?? []) !== []) {
        $grupos[$gk] = programacion_ordenar_filas_por_fecha_hora($grupos[$gk]);
    }
}

$fechaTituloManana = (new DateTimeImmutable('tomorrow'))->format('d/m/Y');
$tituloProgramacionConFecha = 'Programación logística (' . $fechaTituloManana . ')';

$logoProgDirAssets = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
$logoProgMarkup = programacion_markup_logo_colbeef($logoProgDirAssets);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | <?= htmlspecialchars($tituloProgramacionConFecha, ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; overflow-x: hidden; }
        .compact-table th, .compact-table td { padding: 4px 5px !important; font-size: 12px; line-height: 1.2; }
        /* Bloques por planta: casi pegados para ganar alto útil */
        section.prog-bloque-planta { margin-bottom: 0.25rem; }
        /* Sticky: esquina sup-izq (cód + ID) y acción derecha */
        .compact-table thead th.sticky-l1 {
            position: sticky; left: 0; top: 0; z-index: 35;
            width: 3.5rem; min-width: 3.5rem; max-width: 3.5rem;
            background: #f1f5f9; box-shadow: 1px 0 0 #e2e8f0, 0 1px 0 #e2e8f0;
        }
        .compact-table thead th.sticky-l2 {
            position: sticky; left: 3.5rem; top: 0; z-index: 35;
            width: 7rem; min-width: 7rem;
            background: #f1f5f9; box-shadow: 1px 0 0 #e2e8f0, 0 1px 0 #e2e8f0;
        }
        .compact-table thead th.sticky-r {
            position: sticky; right: 0; top: 0; z-index: 35;
            width: 2.75rem; min-width: 2.75rem;
            background: #f1f5f9; box-shadow: -1px 0 0 #e2e8f0, 0 1px 0 #e2e8f0;
        }
        .compact-table tbody td.sticky-l1 {
            position: sticky; left: 0; z-index: 20;
            width: 3.5rem; min-width: 3.5rem; max-width: 3.5rem;
            background: #fff;
            box-shadow: 1px 0 0 #f1f5f9;
        }
        .compact-table tbody td.sticky-l2 {
            position: sticky; left: 3.5rem; z-index: 20;
            width: 7rem; min-width: 7rem;
            background: #fff;
            box-shadow: 1px 0 0 #f1f5f9;
        }
        .compact-table tbody td.sticky-r {
            position: sticky; right: 0; z-index: 20;
            width: 2.75rem; min-width: 2.75rem;
            background: #fff;
            box-shadow: -1px 0 0 #f1f5f9;
        }
        .compact-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .compact-table tbody tr:nth-child(odd) td { background: #fff; }
        .compact-table tbody tr:hover td { background: #f1f5f9 !important; }
        .prog-scroll-outer {
            position: relative;
            max-height: min(calc(100vh - 10rem), 960px);
        }
        /* Sin sombra interior: menos “hueco” visual entre grupos */
        .prog-card-planta { box-shadow: none; }
        .compact-table thead th.col-t-cuarteo,
        .compact-table tbody td.col-t-cuarteo {
            width: 7.25rem !important;
            min-width: 6rem !important;
            max-width: 9rem !important;
            white-space: normal !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
            vertical-align: top !important;
            text-align: center;
            line-height: 1.2;
        }
        .status-pill { padding: 1px 6px; border-radius: 4px; font-weight: 800; font-size: 10px; }
        .prog-logo-svg-inline svg { height: 2rem; width: auto; max-height: 2.25rem; max-width: 200px; display: block; }
        @media (min-width: 640px) {
            .prog-logo-svg-inline svg { height: 2.25rem; max-height: 2.25rem; }
        }
        .compact-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f1f5f9;
        }
        .compact-table thead th.sticky-l1,
        .compact-table thead th.sticky-l2,
        .compact-table thead th.sticky-r { z-index: 35; }
    </style>
</head>
<body class="flex min-h-screen text-slate-800">
    
    <?php mostrarSidebar('prog'); ?>

    <div class="flex-1 flex flex-col ml-64 min-h-screen w-[calc(100%-16rem)] bg-[#f8fafc]">

        <main class="p-6 flex-grow">
            <div class="flex flex-wrap justify-between items-center gap-4 mb-5">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Home › Programación logística</p>
                    <p class="text-slate-500 text-xs mt-1.5 leading-snug max-w-3xl">Cada registro tiene su <span class="font-bold text-slate-700">planta asignada</span> (Beneficio / Desposte / Subproductos / Celfrio). En la barra oscura debajo de los filtros, el <span class="font-bold text-slate-700">título usa siempre la fecha de mañana</span> (día siguiente a la fecha del servidor).</p>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <a href="nueva_programacion.php" class="bg-emerald-600 text-white px-6 py-3 rounded-xl font-black text-[10px] shadow-lg uppercase tracking-widest hover:bg-emerald-500">+ Nueva</a>
                </div>
            </div>

            <?php if ($aviso_limite_default): ?>
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-[11px] text-amber-900 font-semibold">
                Hay más registros en la base de datos. Por rendimiento solo se cargan los <strong>2000</strong> más recientes.
                Use <strong>rango de fechas</strong> o <strong>buscar en base de datos</strong> para acotar o encontrar un dato concreto.
            </div>
            <?php endif; ?>
            <?php if ($aviso_limite_buscar): ?>
            <div class="mb-4 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-3 text-[11px] text-sky-900 font-semibold">
                La búsqueda devolvió más de 8000 coincidencias; solo se muestran las <strong>8000</strong> más recientes. Afine el texto o combine con fechas.
            </div>
            <?php endif; ?>

            <form method="get" action="programacion.php" class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-5 mb-4 flex flex-col gap-3">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex-1 min-w-[220px] max-w-xl">
                        <label for="buscar" class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Buscar en base de datos (cualquier campo)</label>
                        <div class="relative">
                            <input type="text" name="buscar" id="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Cliente, destino, OPL, ID, observaciones, placa…"
                                   class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none text-xs font-bold text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400">
                            <span class="absolute left-3 top-3 text-slate-400 text-sm">🔍</span>
                        </div>
                        <p class="text-[9px] text-slate-400 mt-1.5">Pulse <strong>Aplicar filtros</strong> o Enter. La búsqueda usa el servidor (no cuelga el navegador).</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Desde</span>
                            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-[10px] font-bold text-slate-800">
                        </div>
                        <div>
                            <span class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Hasta</span>
                            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-[10px] font-bold text-slate-800">
                        </div>
                        <button type="submit" class="bg-slate-900 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-600">Aplicar filtros</button>
                        <?php if ($buscar !== '' || $desde !== '' || $hasta !== ''): ?>
                        <a href="programacion.php" class="text-[10px] font-black text-slate-500 hover:text-slate-800 py-3 px-2 uppercase tracking-wider">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4 pt-2 border-t border-slate-100">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-[10px] font-bold text-slate-600">
                        <input type="checkbox" id="toggleCalidad" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" checked>
                        Mostrar columnas OTIF (cant./prod./entrega/dir./pedido)
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-[10px] font-bold text-slate-600">
                        <input type="checkbox" id="toggleMeta" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" checked>
                        Mostrar registro / solicitante / medio
                    </label>
                    <span class="hidden md:inline text-[9px] text-slate-400 font-semibold ml-auto max-w-xl text-right leading-tight">Desplazamiento horizontal: columnas fijas a la izquierda (cód. + ID) y acción a la derecha.</span>
                </div>
                <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-slate-100">
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest w-full sm:w-auto">Refinar en pantalla (solo filas cargadas)</label>
                    <input type="search" id="refinarCliente" autocomplete="off" placeholder="Texto instantáneo sin recargar…"
                           class="flex-1 min-w-[180px] max-w-md px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] font-bold text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-blue-500/20">
                </div>
                <?php if ($puedeSeleccionColumnas): ?>
                <div class="pt-3 border-t border-slate-100">
                    <button type="button" id="btnToggleColumnas" class="w-full inline-flex items-center justify-between gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 text-[10px] font-black uppercase tracking-wider hover:bg-slate-100">
                        <span>Columnas visibles (solo Admin/Super Admin)</span>
                        <span id="iconToggleColumnas">▾</span>
                    </button>
                    <div id="panelColumnas" class="mt-2 hidden">
                        <div id="columnasProgramacionWrap" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2"></div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" id="btnAplicarColumnas" class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-[10px] font-black uppercase tracking-wider hover:bg-emerald-700">
                                Aplicar columnas
                            </button>
                            <button type="button" id="btnResetColumnas" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-wider hover:bg-slate-200">
                                Restablecer filas
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                </form>

                <div class="mb-4 flex flex-wrap sm:flex-nowrap items-center gap-3 sm:gap-4 rounded-2xl border border-slate-800 bg-slate-950 px-3 sm:px-4 py-3 shadow-sm">
                    <div class="flex w-full sm:w-[200px] shrink-0 justify-center sm:justify-start order-2 sm:order-1">
                        <div class="rounded-xl bg-black px-3 sm:px-4 py-2 border border-zinc-900 shadow-inner ring-1 ring-white/5">
                            <?= $logoProgMarkup ?>
                        </div>
                    </div>
                    <div class="flex flex-1 min-w-0 justify-center order-1 sm:order-2 px-2">
                        <div class="text-center max-w-full">
                            <span class="block text-base sm:text-lg md:text-[1.35rem] font-black text-white tracking-tight italic leading-tight"><?= htmlspecialchars($tituloProgramacionConFecha, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="mt-1.5 block text-[9px] font-semibold tracking-wide text-zinc-400">Las filas de la tabla muestran otras fechas según filtros y reglas de prioridad (mañana, último día cargado…).</span>
                        </div>
                    </div>
                    <div class="hidden sm:flex w-[200px] shrink-0 justify-end order-3 pointer-events-none select-none" aria-hidden="true"></div>
                </div>

            <?php
            foreach ($ordenGrupo as $gkey):
                $filasG = $grupos[$gkey] ?? [];
                // SUBPRODUCTOS: mostrar sección aunque no haya filas (vista por planta).
                if ($filasG === [] && $gkey !== 'SUBPRODUCTOS') {
                    continue;
                }
                $titulo = $gkey === '_SIN' ? 'Sin planta asignada' : ($plantasEt[$gkey] ?? $gkey);
                $cnt = count($filasG);
                [$borderPlanta, $iconPlanta] = match ($gkey) {
                    'BENEFICIO' => ['border-l-emerald-500', '🚚'],
                    'DESPOSTE' => ['border-l-rose-500', '🥩'],
                    'CELFRIO' => ['border-l-sky-500', '❄️'],
                    'SUBPRODUCTOS' => ['border-l-violet-500', '📦'],
                    default => ['border-l-amber-500', '⚠️'],
                };
                ?>
            <section class="prog-bloque-planta">
                <div class="flex items-center gap-2 px-3 py-1 bg-white rounded-t-lg border border-slate-200 border-b-0 prog-card-planta <?= $borderPlanta ?> border-l-[5px]">
                    <span class="text-base leading-none"><?= $iconPlanta ?></span>
                    <span class="text-[11px] font-black uppercase tracking-tight text-slate-800 leading-tight"><?= htmlspecialchars(mb_strtoupper($titulo)) ?> <span class="text-emerald-600">(<?= $cnt ?>)</span></span>
            </div>
                <div class="bg-white rounded-b-lg border border-slate-200 border-t-0 overflow-hidden prog-card-planta">
                    <div class="prog-scroll-outer overflow-x-hidden overflow-y-auto">
                        <table class="w-full text-left compact-table prog-table min-w-full table-fixed border-separate border-spacing-0">
                        <thead>
                                <tr class="text-slate-500 uppercase font-black tracking-tighter border-b border-slate-200 text-[11px]">
                                    <th class="whitespace-nowrap sticky-l1 text-center">Cód.</th>
                                    <th class="whitespace-nowrap sticky-l2">ID prog.</th>
                                    <th class="whitespace-nowrap col-meta">F. registro</th>
                                    <th class="min-w-[100px] col-meta">Solicitante</th>
                                    <th class="col-meta">Medio</th>
                                    <th>Estado pedido</th>
                                    <th class="min-w-[260px] max-w-none">Cliente</th>
                                    <th>Nº planta</th>
                                    <th>Planta (nombre)</th>
                                    <th>Planta op.</th>
                                    <th class="min-w-[90px]">Actividad</th>
                                    <th class="whitespace-nowrap">F. operación</th>
                                    <th>Hora</th>
                                    <th class="min-w-[90px]">Producto</th>
                                    <th class="col-t-cuarteo">T. cuarteo</th>
                                    <th class="min-w-[54px]">Lote</th>
                                    <th class="text-right min-w-[68px]">Cantidad</th>
                                    <th>Ciudad</th>
                                    <th>Destino</th>
                                    <th>Ubicación</th>
                                    <th>OPL</th>
                                    <th>Conductor</th>
                                    <th>Vehículo</th>
                                    <th class="min-w-[28rem] max-w-none">Observaciones</th>
                                    <th class="col-calidad">Cant. OK</th>
                                    <th class="col-calidad">Prod. OK</th>
                                    <th class="col-calidad">Entr. tiempo</th>
                                    <th class="col-calidad">Dir. OK</th>
                                    <th class="col-calidad">Ped. perf.</th>
                                    <th class="text-center">Estado act.</th>
                                    <th>Teléfono</th>
                                    <th class="text-center sticky-r"></th>
                            </tr>
                        </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if ($filasG === []): ?>
                                <tr>
                                    <td colspan="32" class="text-center text-slate-400 py-2 text-[11px] font-semibold uppercase tracking-wide leading-snug">
                                        Sin programación para esta planta. Use <a href="nueva_programacion.php" class="text-violet-600 underline hover:text-violet-800">nueva programación</a> para registrar.
                                    </td>
                                </tr>
                                <?php else: foreach ($filasG as $r):
                                    $st = (string) ($r['Estado_Actividad'] ?? '');
                                    $class = ($st === 'PROGRAMADO') ? 'bg-red-50 text-red-700 border border-red-100' : (($st === 'EJECUTADO') ? 'bg-emerald-50 text-emerald-800 border border-emerald-100' : 'bg-amber-50 text-amber-900 border border-amber-100');
                                    $nomAct = $r['NomAct'] ?? '';
                                    $ic = programacion_icono_actividad($nomAct);
                                    $actClass = 'text-slate-700';
                                    $u = mb_strtoupper($nomAct);
                                    if (str_contains($u, 'DESPACHO')) {
                                        $actClass = 'text-emerald-700';
                                    } elseif (str_contains($u, 'TRASLADO')) {
                                        $actClass = 'text-rose-700';
                                    }
                                    $vehDisplay = trim((string) ($r['PlacaVeh'] ?? $r['Vehiculo'] ?? ''));
                                    $gFila = programacion_grupo_desde_fila($r);
                                    $oplShow = trim((string) ($r['NomOPL'] ?? '')) !== '' ? (string) $r['NomOPL'] : (string) ($r['OPL'] ?? '');
                                    $tcShow = trim((string) ($r['NomTipoCuarteo'] ?? '')) !== '' ? (string) $r['NomTipoCuarteo'] : (string) ($r['Tipo_de_Cuarteo'] ?? '');
                                    $condShow = trim((string) ($r['NomConductor'] ?? '')) !== '' ? (string) $r['NomConductor'] : (string) ($r['Conductor'] ?? '');
                                    ?>
                                <tr class="row-item transition-colors" data-search="<?= htmlspecialchars(programacion_blob_busqueda_local($r), ENT_QUOTES, 'UTF-8') ?>">
                                    <td class="sticky-l1 font-black text-emerald-800 whitespace-nowrap text-center"><?= (int) ($r['id_interno'] ?? 0) ?></td>
                                    <td class="sticky-l2 font-mono text-[10px] text-slate-600 whitespace-nowrap max-w-[7rem] truncate" title="<?= htmlspecialchars((string) ($r['ID_Programacion'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['ID_Programacion'] ?? '')) ?></td>
                                    <td class="text-slate-600 whitespace-nowrap col-meta"><?= htmlspecialchars((string) ($r['Fecha_de_Registro'] ?? '')) ?></td>
                                    <td class="max-w-[140px] truncate text-slate-700 col-meta" title="<?= htmlspecialchars((string) ($r['NomSolicitante'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomSolicitante'] ?? '')) ?></td>
                                    <td class="max-w-[90px] truncate col-meta" title="<?= htmlspecialchars((string) ($r['NomMedioCom'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomMedioCom'] ?? '')) ?></td>
                                    <td class="text-slate-600"><?= htmlspecialchars((string) ($r['Estado'] ?? '')) ?></td>
                                    <td class="min-w-[260px] max-w-[520px] whitespace-normal break-words font-medium text-slate-800" title="<?= htmlspecialchars((string) ($r['NomCli'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomCli'] ?? $r['Cliente'] ?? '')) ?></td>
                                    <td class="text-slate-500"><?= htmlspecialchars((string) ($r['Planta'] !== null && $r['Planta'] !== '' ? (string) $r['Planta'] : '')) ?></td>
                                    <td class="text-slate-600 max-w-[100px] truncate" title="<?= htmlspecialchars((string) ($r['NomPlantaMaestro'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomPlantaMaestro'] ?? '')) ?></td>
                                    <td class="font-black text-[10px] uppercase text-slate-600"><?= htmlspecialchars(programacion_etiqueta_planta_grupo($gFila)) ?></td>
                                    <td>
                                        <span class="mr-1"><?= $ic ?></span>
                                        <span class="<?= $actClass ?> font-black uppercase text-[10px]"><?= htmlspecialchars($nomAct) ?></span>
                                </td>
                                    <td class="font-bold text-slate-900 whitespace-nowrap"><?= htmlspecialchars((string) ($r['Fecha_de_Operacion'] ?? '')) ?></td>
                                    <td class="text-slate-600"><?= htmlspecialchars((string) ($r['Hora'] ?? '')) ?></td>
                                    <td class="text-slate-800 font-bold uppercase"><?= htmlspecialchars((string) ($r['NomProdDisplay'] ?? '')) ?></td>
                                    <td class="col-t-cuarteo text-slate-700 text-[11px] font-semibold leading-snug" title="<?= htmlspecialchars($tcShow) ?>"><?= htmlspecialchars($tcShow) ?></td>
                                    <td class="min-w-[54px] max-w-[70px] truncate text-slate-600 text-center" title="<?= htmlspecialchars((string) ($r['Lote'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['Lote'] ?? '—')) ?></td>
                                    <td class="min-w-[68px] text-right font-bold text-blue-700"><?= $r['Cantidad'] !== null && $r['Cantidad'] !== '' ? number_format((float) $r['Cantidad'], 2) : '' ?></td>
                                    <td class="max-w-[100px] truncate text-slate-600" title="<?= htmlspecialchars((string) ($r['NomCiudad'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomCiudad'] ?? '')) ?></td>
                                    <td class="max-w-[140px] truncate text-slate-700" title="<?= htmlspecialchars((string) ($r['Destino'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['Destino'] ?? '')) ?></td>
                                    <td class="max-w-[100px] truncate text-slate-600" title="<?= htmlspecialchars((string) ($r['Ubicacion'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['Ubicacion'] ?? '')) ?></td>
                                    <td class="max-w-[100px] truncate text-slate-600 text-[10px]" title="<?= htmlspecialchars($oplShow) ?>"><?= htmlspecialchars($oplShow) ?></td>
                                    <td class="max-w-[110px] truncate text-slate-700" title="<?= htmlspecialchars($condShow) ?>"><?= htmlspecialchars($condShow) ?></td>
                                    <td class="font-black text-slate-800 uppercase whitespace-nowrap"><?= htmlspecialchars($vehDisplay !== '' ? $vehDisplay : '—') ?></td>
                                    <td class="min-w-[28rem] max-w-[40rem] whitespace-normal break-words text-slate-500 text-[11px]" title="<?= htmlspecialchars((string) ($r['Observaciones'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['Observaciones'] ?? '')) ?></td>
                                    <td class="text-slate-500 col-calidad"><?= htmlspecialchars((string) ($r['Cantidad_Correcta'] ?? '')) ?></td>
                                    <td class="text-slate-500 col-calidad"><?= htmlspecialchars((string) ($r['Producto_Correcto'] ?? '')) ?></td>
                                    <td class="text-slate-500 col-calidad"><?= htmlspecialchars((string) ($r['Entrega_a_Tiempo'] ?? '')) ?></td>
                                    <td class="text-slate-500 col-calidad"><?= htmlspecialchars((string) ($r['Direccion_Correcta'] ?? '')) ?></td>
                                    <td class="text-slate-500 col-calidad"><?= htmlspecialchars((string) ($r['Pedido_Perfecto'] ?? '')) ?></td>
                                <td class="text-center">
                                        <span class="status-pill <?= $class ?>"><?= htmlspecialchars($st ?: '—') ?></span>
                                </td>
                                    <td class="text-slate-600 whitespace-nowrap"><?= htmlspecialchars((string) ($r['Telefono'] ?? '')) ?></td>
                                    <td class="text-center sticky-r whitespace-nowrap">
                                        <a href="editar_programacion.php?id=<?= (int) ($r['id_interno'] ?? 0) ?>" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-blue-600 text-sm font-black hover:bg-blue-50" title="Editar">›</a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>

            <?php if ($total_registros === 0): ?>
            <div class="bg-white border border-slate-100 rounded-[2rem] p-12 text-center text-slate-400 font-bold shadow-sm">
                No hay programaciones. Ajuste fechas o cree un registro nuevo.
            </div>
            <?php endif; ?>

            <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm text-[9px] text-slate-500 font-bold uppercase text-center">
                Coincidencias con filtros actuales: <?= $total_registros ?>
                <?php if ($filas_cargadas < $total_registros): ?>
                    <span class="text-amber-600"> · En pantalla: <?= $filas_cargadas ?> (límite de carga)</span>
                <?php elseif ($filas_cargadas > 0): ?>
                    <span class="text-slate-400"> · En pantalla: <?= $filas_cargadas ?></span>
                <?php endif; ?>
            </div>
        </main>
        <?php mostrarFooter(); ?>
    </div>

    <script>
        var _progRefinarTimer = null;
        var _columnVisibility = {};
        var _columnDefaultVisibility = {
            codigo: false,
            id_programacion: false,
            f_registro: false,
            solicitante: false,
            medio: false,
            estado_pedido: false,
            cliente: true,
            n_planta: false,
            planta_nombre: false,
            planta_op: false,
            actividad: true,
            f_operacion: true,
            hora: true,
            producto: true,
            t_cuarteo: true,
            lote: false,
            cantidad: true,
            ciudad: false,
            destino: true,
            ubicacion: false,
            opl: false,
            conductor: false,
            vehiculo: true,
            obs: true,
            cant_ok: false,
            prod_ok: false,
            ent_tiempo: false,
            dir_ok: false,
            ped_perf: false,
            estado_actividad: false,
            telefono: false
        };
        var _columnWidths = {
            cliente: '28rem',
            actividad: '10rem',
            f_operacion: '8rem',
            hora: '5rem',
            producto: '9rem',
            t_cuarteo: '7.25rem',
            lote: '4.25rem',
            cantidad: '5rem',
            destino: '11rem',
            conductor: '10rem',
            vehiculo: '8rem',
            obs: '44rem'
        };
        var _columnDefs = [
            { i: 1, key: 'codigo', label: 'Cód.' },
            { i: 2, key: 'id_programacion', label: 'ID prog.' },
            { i: 3, key: 'f_registro', label: 'F. registro' },
            { i: 4, key: 'solicitante', label: 'Solicitante' },
            { i: 5, key: 'medio', label: 'Medio' },
            { i: 6, key: 'estado_pedido', label: 'Estado pedido' },
            { i: 7, key: 'cliente', label: 'Cliente' },
            { i: 8, key: 'n_planta', label: 'Nº planta' },
            { i: 9, key: 'planta_nombre', label: 'Planta (nombre)' },
            { i: 10, key: 'planta_op', label: 'Planta op.' },
            { i: 11, key: 'actividad', label: 'Actividad' },
            { i: 12, key: 'f_operacion', label: 'F. operación' },
            { i: 13, key: 'hora', label: 'Hora' },
            { i: 14, key: 'producto', label: 'Producto' },
            { i: 15, key: 't_cuarteo', label: 'T. cuarteo' },
            { i: 16, key: 'lote', label: 'Lote' },
            { i: 17, key: 'cantidad', label: 'Cantidad' },
            { i: 18, key: 'ciudad', label: 'Ciudad' },
            { i: 19, key: 'destino', label: 'Destino' },
            { i: 20, key: 'ubicacion', label: 'Ubicación' },
            { i: 21, key: 'opl', label: 'OPL' },
            { i: 22, key: 'conductor', label: 'Conductor' },
            { i: 23, key: 'vehiculo', label: 'Vehículo' },
            { i: 24, key: 'obs', label: 'Observaciones' },
            { i: 25, key: 'cant_ok', label: 'Cant. OK' },
            { i: 26, key: 'prod_ok', label: 'Prod. OK' },
            { i: 27, key: 'ent_tiempo', label: 'Entr. tiempo' },
            { i: 28, key: 'dir_ok', label: 'Dir. OK' },
            { i: 29, key: 'ped_perf', label: 'Ped. perf.' },
            { i: 30, key: 'estado_actividad', label: 'Estado act.' },
            { i: 31, key: 'telefono', label: 'Teléfono' }
        ];
        var _esAdminColumnas = <?= $puedeSeleccionColumnas ? 'true' : 'false' ?>;

        /** Filtra filas usando data-search (sin innerText; no bloquea el navegador). */
        function aplicarRefinarCliente() {
            var inp = document.getElementById('refinarCliente');
            if (!inp) return;
            var val = inp.value.toUpperCase().replace(/\s+/g, ' ').trim();
            document.querySelectorAll('.prog-table tbody tr.row-item').forEach(function (r) {
                var blob = (r.getAttribute('data-search') || '').toUpperCase();
                if (val === '') {
                    r.style.display = '';
                    return;
                }
                r.style.display = blob.indexOf(val) !== -1 ? '' : 'none';
            });
        }

        function programarRefinarCliente() {
            clearTimeout(_progRefinarTimer);
            _progRefinarTimer = setTimeout(aplicarRefinarCliente, 160);
        }

        function applyProgColumnToggles() {
            var showCal = document.getElementById('toggleCalidad').checked;
            var showMeta = document.getElementById('toggleMeta').checked;
            document.querySelectorAll('.col-calidad').forEach(function (el) {
                el.classList.toggle('hidden', !showCal);
            });
            document.querySelectorAll('.col-meta').forEach(function (el) {
                el.classList.toggle('hidden', !showMeta);
            });
            applyAdminColumnVisibility();
            aplicarRefinarCliente();
        }

        function applyAdminColumnVisibility() {
            if (!_esAdminColumnas) return;
            document.querySelectorAll('.prog-table').forEach(function (tb) {
                _columnDefs.forEach(function (def) {
                    var visible = _columnVisibility[def.key] !== false;
                    tb.querySelectorAll('tr').forEach(function (tr) {
                        var cell = tr.children[def.i - 1];
                        if (!cell) return;
                        cell.classList.toggle('hidden', !visible);
                        if (visible && _columnWidths[def.key]) {
                            cell.style.minWidth = _columnWidths[def.key];
                            cell.style.maxWidth = _columnWidths[def.key];
                        } else if (visible) {
                            cell.style.removeProperty('min-width');
                            cell.style.removeProperty('max-width');
                        }
                    });
                });
            });
        }

        function renderColumnManager() {
            if (!_esAdminColumnas) return;
            var wrap = document.getElementById('columnasProgramacionWrap');
            if (!wrap) return;
            wrap.innerHTML = '';
            _columnDefs.forEach(function (def) {
                if (_columnVisibility[def.key] === undefined) {
                    _columnVisibility[def.key] = _columnDefaultVisibility[def.key] === true;
                }
                var id = 'col_' + def.key;
                var label = document.createElement('label');
                label.setAttribute('for', id);
                label.className = 'inline-flex items-center gap-2 px-2.5 py-2 rounded-lg border border-slate-200 bg-white text-[10px] font-bold text-slate-700 cursor-pointer hover:bg-slate-50';
                var ck = document.createElement('input');
                ck.type = 'checkbox';
                ck.id = id;
                ck.checked = _columnVisibility[def.key] !== false;
                ck.className = 'rounded border-slate-300 text-emerald-600 focus:ring-emerald-500';
                var tx = document.createElement('span');
                tx.textContent = def.label;
                label.appendChild(ck);
                label.appendChild(tx);
                wrap.appendChild(label);
            });
        }

        function aplicarSeleccionColumnas() {
            if (!_esAdminColumnas) return;
            _columnDefs.forEach(function (def) {
                var ck = document.getElementById('col_' + def.key);
                if (!ck) return;
                _columnVisibility[def.key] = !!ck.checked;
            });
            applyAdminColumnVisibility();
        }

        document.addEventListener('DOMContentLoaded', function () {
            var tc = document.getElementById('toggleCalidad');
            var tm = document.getElementById('toggleMeta');
            var ref = document.getElementById('refinarCliente');
            if (tc) tc.addEventListener('change', applyProgColumnToggles);
            if (tm) tm.addEventListener('change', applyProgColumnToggles);
            if (ref) {
                ref.addEventListener('input', programarRefinarCliente);
                ref.addEventListener('search', function () {
                    if (ref.value === '') aplicarRefinarCliente();
                });
            }
            if (_esAdminColumnas) {
                renderColumnManager();
                var tc = document.getElementById('toggleCalidad');
                var tm = document.getElementById('toggleMeta');
                if (tc) tc.checked = false;
                if (tm) tm.checked = false;
                var btnToggle = document.getElementById('btnToggleColumnas');
                var panelColumnas = document.getElementById('panelColumnas');
                var iconToggle = document.getElementById('iconToggleColumnas');
                if (btnToggle && panelColumnas && iconToggle) {
                    btnToggle.addEventListener('click', function () {
                        var oculto = panelColumnas.classList.contains('hidden');
                        panelColumnas.classList.toggle('hidden', !oculto);
                        iconToggle.textContent = oculto ? '▴' : '▾';
                    });
                }
                var btnAplicar = document.getElementById('btnAplicarColumnas');
                if (btnAplicar) {
                    btnAplicar.addEventListener('click', function () {
                        aplicarSeleccionColumnas();
                    });
                }
                var btnReset = document.getElementById('btnResetColumnas');
                if (btnReset) {
                    btnReset.addEventListener('click', function () {
                        _columnDefs.forEach(function (def) { _columnVisibility[def.key] = _columnDefaultVisibility[def.key] === true; });
                        renderColumnManager();
                        var tc = document.getElementById('toggleCalidad');
                        var tm = document.getElementById('toggleMeta');
                        if (tc) tc.checked = false;
                        if (tm) tm.checked = false;
                        applyProgColumnToggles();
                    });
                }
            }
            applyProgColumnToggles();
        });
    </script>
</body>
</html>
