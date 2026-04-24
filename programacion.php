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

$sqlCount = 'SELECT COUNT(DISTINCT p.id_interno) FROM Programacion p' . $sqlJoins . ' ' . $where;
$stc = $pdo->prepare($sqlCount);
$stc->execute($params);
$total_registros = (int) $stc->fetchColumn();

$limitSql = '';
$aviso_limite_default = false;
$aviso_limite_buscar = false;
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
        ORDER BY p.id_interno DESC
        $limitSql";

$st = $pdo->prepare($sql);
$st->execute($params);
$todas = $st->fetchAll(PDO::FETCH_ASSOC);
$filas_cargadas = count($todas);

$ordenGrupo = ['BENEFICIO', 'DESPOSTE', 'CELFRIO', '_SIN'];
$grupos = [];
foreach ($ordenGrupo as $k) {
    $grupos[$k] = [];
}
foreach ($todas as $r) {
    $g = programacion_grupo_desde_fila($r);
    $grupos[$g][] = $r;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Programación</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; overflow-x: hidden; }
        .compact-table th, .compact-table td { padding: 7px 8px !important; font-size: 10px; line-height: 1.25; }
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
            max-height: min(calc(100vh - 13rem), 920px);
        }
        .status-pill { padding: 2px 8px; border-radius: 6px; font-weight: 800; font-size: 8px; }
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

        <main class="p-8 flex-grow">
            <div class="flex flex-wrap justify-between items-center gap-4 mb-8">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Home › Programación</p>
                    <h2 class="text-3xl font-black text-slate-800 tracking-tight italic">Programación</h2>
                    <p class="text-slate-500 text-sm mt-1">Cada registro tiene su <span class="font-bold text-slate-700">planta asignada</span> (Beneficio / Desposte / Celfrio). Destino, observaciones, OPL y vehículo son datos aparte.</p>
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

            <form method="get" action="programacion.php" class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6 mb-6 flex flex-col gap-4">
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
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Columnas visibles (solo Admin/Super Admin)</p>
                    </div>
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
                <?php endif; ?>
            </form>

            <?php
            foreach ($ordenGrupo as $gkey):
                $filasG = $grupos[$gkey] ?? [];
                if ($filasG === []) {
                    continue;
                }
                $titulo = $gkey === '_SIN' ? 'Sin planta asignada' : ($plantasEt[$gkey] ?? $gkey);
                $cnt = count($filasG);
                $borderPlanta = $gkey === 'BENEFICIO' ? 'border-l-emerald-500' : ($gkey === 'DESPOSTE' ? 'border-l-rose-500' : ($gkey === 'CELFRIO' ? 'border-l-sky-500' : 'border-l-amber-500'));
                $iconPlanta = $gkey === 'BENEFICIO' ? '🚚' : ($gkey === 'DESPOSTE' ? '🥩' : ($gkey === 'CELFRIO' ? '❄️' : '⚠️'));
                ?>
            <section class="mb-10">
                <div class="flex items-center gap-3 px-6 py-4 bg-white rounded-t-[1.5rem] border border-slate-100 border-b-0 shadow-sm <?= $borderPlanta ?> border-l-[6px]">
                    <span class="text-2xl leading-none"><?= $iconPlanta ?></span>
                    <span class="text-sm font-black uppercase tracking-tight text-slate-800"><?= htmlspecialchars(mb_strtoupper($titulo)) ?> <span class="text-emerald-600">(<?= $cnt ?>)</span></span>
                </div>
                <div class="bg-white rounded-b-[1.5rem] border border-slate-100 border-t-0 overflow-hidden shadow-sm">
                    <div class="prog-scroll-outer overflow-x-auto overflow-y-auto">
                        <table class="w-full text-left compact-table prog-table min-w-[2100px] border-separate border-spacing-0">
                            <thead>
                                <tr class="text-slate-500 uppercase font-black tracking-tighter border-b border-slate-200 text-[9px]">
                                    <th class="whitespace-nowrap sticky-l1 text-center">Cód.</th>
                                    <th class="whitespace-nowrap sticky-l2">ID prog.</th>
                                    <th class="whitespace-nowrap col-meta">F. registro</th>
                                    <th class="min-w-[100px] col-meta">Solicitante</th>
                                    <th class="col-meta">Medio</th>
                                    <th>Estado pedido</th>
                                    <th class="min-w-[120px]">Cliente</th>
                                    <th>Nº planta</th>
                                    <th>Planta (nombre)</th>
                                    <th>Planta op.</th>
                                    <th class="min-w-[90px]">Actividad</th>
                                    <th class="whitespace-nowrap">F. operación</th>
                                    <th>Hora</th>
                                    <th class="min-w-[90px]">Producto</th>
                                    <th>T. cuarteo</th>
                                    <th>Lote</th>
                                    <th class="text-right">Cantidad</th>
                                    <th>Ciudad</th>
                                    <th>Destino</th>
                                    <th>Ubicación</th>
                                    <th>OPL</th>
                                    <th>Conductor</th>
                                    <th>Vehículo</th>
                                    <th class="min-w-[120px]">Observaciones</th>
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
                                <?php foreach ($filasG as $r):
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
                                    <td class="sticky-l2 font-mono text-[9px] text-slate-600 whitespace-nowrap max-w-[7rem] truncate" title="<?= htmlspecialchars((string) ($r['ID_Programacion'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['ID_Programacion'] ?? '')) ?></td>
                                    <td class="text-slate-600 whitespace-nowrap col-meta"><?= htmlspecialchars((string) ($r['Fecha_de_Registro'] ?? '')) ?></td>
                                    <td class="max-w-[140px] truncate text-slate-700 col-meta" title="<?= htmlspecialchars((string) ($r['NomSolicitante'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomSolicitante'] ?? '')) ?></td>
                                    <td class="max-w-[90px] truncate col-meta" title="<?= htmlspecialchars((string) ($r['NomMedioCom'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomMedioCom'] ?? '')) ?></td>
                                    <td class="text-slate-600"><?= htmlspecialchars((string) ($r['Estado'] ?? '')) ?></td>
                                    <td class="max-w-[160px] truncate font-medium text-slate-800" title="<?= htmlspecialchars((string) ($r['NomCli'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomCli'] ?? $r['Cliente'] ?? '')) ?></td>
                                    <td class="text-slate-500"><?= htmlspecialchars((string) ($r['Planta'] !== null && $r['Planta'] !== '' ? (string) $r['Planta'] : '')) ?></td>
                                    <td class="text-slate-600 max-w-[100px] truncate" title="<?= htmlspecialchars((string) ($r['NomPlantaMaestro'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomPlantaMaestro'] ?? '')) ?></td>
                                    <td class="font-black text-[8px] uppercase text-slate-600"><?= htmlspecialchars(programacion_etiqueta_planta_grupo($gFila)) ?></td>
                                    <td>
                                        <span class="mr-1"><?= $ic ?></span>
                                        <span class="<?= $actClass ?> font-black uppercase text-[8px]"><?= htmlspecialchars($nomAct) ?></span>
                                    </td>
                                    <td class="font-bold text-slate-900 whitespace-nowrap"><?= htmlspecialchars((string) ($r['Fecha_de_Operacion'] ?? '')) ?></td>
                                    <td class="text-slate-600"><?= htmlspecialchars((string) ($r['Hora'] ?? '')) ?></td>
                                    <td class="text-slate-800 font-bold uppercase"><?= htmlspecialchars((string) ($r['NomProdDisplay'] ?? '')) ?></td>
                                    <td class="text-slate-600 max-w-[100px] truncate" title="<?= htmlspecialchars($tcShow) ?>"><?= htmlspecialchars($tcShow) ?></td>
                                    <td class="text-slate-600 max-w-[100px] truncate" title="<?= htmlspecialchars((string) ($r['Lote'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['Lote'] ?? '')) ?></td>
                                    <td class="text-right font-bold text-blue-700"><?= $r['Cantidad'] !== null && $r['Cantidad'] !== '' ? number_format((float) $r['Cantidad'], 2) : '' ?></td>
                                    <td class="max-w-[100px] truncate text-slate-600" title="<?= htmlspecialchars((string) ($r['NomCiudad'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['NomCiudad'] ?? '')) ?></td>
                                    <td class="max-w-[140px] truncate text-slate-700" title="<?= htmlspecialchars((string) ($r['Destino'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['Destino'] ?? '')) ?></td>
                                    <td class="max-w-[100px] truncate text-slate-600" title="<?= htmlspecialchars((string) ($r['Ubicacion'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['Ubicacion'] ?? '')) ?></td>
                                    <td class="max-w-[100px] truncate text-slate-600 text-[8px]" title="<?= htmlspecialchars($oplShow) ?>"><?= htmlspecialchars($oplShow) ?></td>
                                    <td class="max-w-[110px] truncate text-slate-700" title="<?= htmlspecialchars($condShow) ?>"><?= htmlspecialchars($condShow) ?></td>
                                    <td class="font-black text-slate-800 uppercase whitespace-nowrap"><?= htmlspecialchars($vehDisplay !== '' ? $vehDisplay : '—') ?></td>
                                    <td class="max-w-[180px] truncate text-slate-500 text-[8px]" title="<?= htmlspecialchars((string) ($r['Observaciones'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['Observaciones'] ?? '')) ?></td>
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
                                        <a href="editar_programacion.php?id=<?= (int) ($r['id_interno'] ?? 0) ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 font-black hover:bg-blue-50" title="Editar">›</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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
                if (_columnVisibility[def.key] === undefined) _columnVisibility[def.key] = true;
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
                var btnAplicar = document.getElementById('btnAplicarColumnas');
                if (btnAplicar) {
                    btnAplicar.addEventListener('click', function () {
                        aplicarSeleccionColumnas();
                    });
                }
                var btnReset = document.getElementById('btnResetColumnas');
                if (btnReset) {
                    btnReset.addEventListener('click', function () {
                        _columnDefs.forEach(function (def) { _columnVisibility[def.key] = true; });
                        renderColumnManager();
                        var tc = document.getElementById('toggleCalidad');
                        var tm = document.getElementById('toggleMeta');
                        if (tc) tc.checked = true;
                        if (tm) tm.checked = true;
                        applyProgColumnToggles();
                    });
                }
            }
            applyProgColumnToggles();
        });
    </script>
</body>
</html>
