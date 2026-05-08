<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/lm_assets.php';

/** Alineado con index.php (reinicio operativo). */
$fechaCorte = '2026-04-30';
$fechaIsoExpr = 'CONCAT(SUBSTRING(p.Fecha_de_Operacion, 7, 4), \'-\', SUBSTRING(p.Fecha_de_Operacion, 4, 2), \'-\', SUBSTRING(p.Fecha_de_Operacion, 1, 2))';
$wherePeriodo = "p.Fecha_de_Operacion REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' AND $fechaIsoExpr >= '$fechaCorte'";

$tituloPeriodo = 'Período operativo (desde el 30 de abril de 2026, inclusive)';

// --- Período (dashboard / corte) ---
$kgPeriodo = (float) ($pdo->query("SELECT COALESCE(SUM(p.Cantidad),0) FROM Programacion p WHERE $wherePeriodo")->fetchColumn() ?: 0);
$movPeriodo = (int) ($pdo->query("SELECT COUNT(*) FROM Programacion p WHERE $wherePeriodo")->fetchColumn() ?: 0);
$ejecP = (int) ($pdo->query("SELECT COUNT(*) FROM Programacion p WHERE $wherePeriodo AND p.Estado_Actividad = 'EJECUTADO'")->fetchColumn() ?: 0);
$progP = (int) ($pdo->query("SELECT COUNT(*) FROM Programacion p WHERE $wherePeriodo AND p.Estado_Actividad = 'PROGRAMADO'")->fetchColumn() ?: 0);
$cancP = (int) ($pdo->query("SELECT COUNT(*) FROM Programacion p WHERE $wherePeriodo AND p.Estado_Actividad = 'CANCELADO'")->fetchColumn() ?: 0);
$otifP = $movPeriodo > 0 ? round($ejecP / $movPeriodo * 100, 1) : 0;
$noCancP = $movPeriodo > 0 ? round(($ejecP + $progP) / $movPeriodo * 100, 1) : 0.0;
$promKgViaje = $movPeriodo > 0 ? round($kgPeriodo / $movPeriodo, 1) : 0.0;

$diasOp = (int) ($pdo->query("SELECT COUNT(DISTINCT p.Fecha_de_Operacion) FROM Programacion p WHERE $wherePeriodo")->fetchColumn() ?: 0);

$sqlPlanta = "SELECT pl.Planta AS nombre, COUNT(*) AS movimientos, COALESCE(SUM(p.Cantidad),0) AS kg
    FROM Programacion p
    JOIN Planta pl ON p.Planta = pl.ID_Planta
    WHERE $wherePeriodo
    GROUP BY pl.Planta
    ORDER BY kg DESC";
$porPlanta = $pdo->query($sqlPlanta)->fetchAll(PDO::FETCH_ASSOC);

$sqlCli = "SELECT c.Cliente AS nombre, COUNT(*) AS movimientos, COALESCE(SUM(p.Cantidad),0) AS kg
    FROM Programacion p
    JOIN Clientes c ON p.Cliente = c.ID_Cliente
    WHERE $wherePeriodo
    GROUP BY c.Cliente
    ORDER BY kg DESC
    LIMIT 15";
$topClientes = $pdo->query($sqlCli)->fetchAll(PDO::FETCH_ASSOC);

$sqlAct = "SELECT COALESCE(act.Actividad, '(sin actividad)') AS nombre, COUNT(*) AS movimientos, COALESCE(SUM(p.Cantidad),0) AS kg
    FROM Programacion p
    LEFT JOIN Actividad act
      ON CAST(p.Actividad AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
       = CAST(act.ID_Actividad AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
    WHERE $wherePeriodo
    GROUP BY nombre
    ORDER BY movimientos DESC";
$porActividad = $pdo->query($sqlAct)->fetchAll(PDO::FETCH_ASSOC);

$sqlProd = "SELECT COALESCE(pr.Producto, '(sin producto)') AS nombre, COUNT(*) AS movimientos, COALESCE(SUM(p.Cantidad),0) AS kg
    FROM Programacion p
    LEFT JOIN Producto pr ON p.Producto = pr.ID_Producto
    WHERE $wherePeriodo
    GROUP BY nombre
    ORDER BY kg DESC
    LIMIT 12";
$porProducto = $pdo->query($sqlProd)->fetchAll(PDO::FETCH_ASSOC);

$conCond = (int) ($pdo->query("SELECT COUNT(*) FROM Programacion p WHERE $wherePeriodo AND TRIM(COALESCE(p.Conductor,'')) <> ''")->fetchColumn() ?: 0);
$conVehi = (int) ($pdo->query("SELECT COUNT(*) FROM Programacion p WHERE $wherePeriodo AND TRIM(COALESCE(p.Vehiculo,'')) <> ''")->fetchColumn() ?: 0);
$conAmbos = (int) ($pdo->query("SELECT COUNT(*) FROM Programacion p WHERE $wherePeriodo AND TRIM(COALESCE(p.Conductor,'')) <> '' AND TRIM(COALESCE(p.Vehiculo,'')) <> ''")->fetchColumn() ?: 0);

// --- Histórico completo (sin filtro de fecha) ---
$kgTotal = (float) ($pdo->query('SELECT COALESCE(SUM(Cantidad),0) FROM Programacion')->fetchColumn() ?: 0);
$movTotal = (int) ($pdo->query('SELECT COUNT(*) FROM Programacion')->fetchColumn() ?: 0);

$nEmpleados = null;
try {
    $nEmpleados = (int) $pdo->query('SELECT COUNT(*) FROM empleado')->fetchColumn();
} catch (Throwable) {
}

$nClientesMaestro = null;
try {
    $nClientesMaestro = (int) $pdo->query('SELECT COUNT(*) FROM Clientes')->fetchColumn();
} catch (Throwable) {
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiMeat | Estadísticas</title>
    <?php lm_head_local_assets(); ?>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }</style>
</head>
<body class="flex min-h-screen">
<?php mostrarSidebar('estadisticas'); ?>

<div class="flex-1 flex flex-col ml-64 min-h-screen">
    <main class="p-8 flex-grow max-w-7xl mx-auto w-full">
        <header class="mb-10">
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Estadísticas del sistema</h1>
            <p class="text-slate-500 text-sm mt-2">Resumen de programación, kilogramos, estados y logística. URL: <span class="font-mono text-slate-600">estadisticas.php</span></p>
        </header>

        <section class="mb-12">
            <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4"><?= htmlspecialchars($tituloPeriodo, ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Kilogramos</p>
                    <p class="text-2xl font-black text-slate-800 mt-1"><?= number_format($kgPeriodo, 0, ',', '.') ?> <span class="text-sm font-bold text-slate-400">kg</span></p>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Movimientos (viajes)</p>
                    <p class="text-2xl font-black text-slate-800 mt-1"><?= number_format($movPeriodo) ?></p>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">OTIF (ejecutados)</p>
                    <p class="text-2xl font-black text-blue-600 mt-1"><?= $otifP ?><span class="text-lg">%</span></p>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Sin cancelar</p>
                    <p class="text-2xl font-black text-emerald-600 mt-1"><?= $noCancP ?><span class="text-lg">%</span></p>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm col-span-2 md:col-span-1">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Kg / viaje (prom.)</p>
                    <p class="text-2xl font-black text-slate-800 mt-1"><?= number_format($promKgViaje, 1, ',', '.') ?></p>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                    <span class="block text-[10px] font-black text-amber-600 uppercase">Ejecutados</span>
                    <span class="text-xl font-black text-slate-800"><?= number_format($ejecP) ?></span>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                    <span class="block text-[10px] font-black text-rose-600 uppercase">Programados</span>
                    <span class="text-xl font-black text-slate-800"><?= number_format($progP) ?></span>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                    <span class="block text-[10px] font-black text-slate-500 uppercase">Cancelados</span>
                    <span class="text-xl font-black text-slate-800"><?= number_format($cancP) ?></span>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                    <span class="block text-[10px] font-black text-slate-500 uppercase">Días con carga</span>
                    <span class="text-xl font-black text-slate-800"><?= number_format($diasOp) ?></span>
                </div>
            </div>

            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Logística (en el período)</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-bold text-slate-400 uppercase">Con conductor asignado</p>
                    <p class="text-2xl font-black text-slate-800"><?= number_format($conCond) ?></p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-bold text-slate-400 uppercase">Con vehículo asignado</p>
                    <p class="text-2xl font-black text-slate-800"><?= number_format($conVehi) ?></p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-bold text-slate-400 uppercase">Conductor y vehículo</p>
                    <p class="text-2xl font-black text-slate-800"><?= number_format($conAmbos) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 bg-slate-50 border-b border-slate-100">
                        <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">Por planta</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead><tr class="text-slate-400 font-black uppercase text-[10px] border-b border-slate-100">
                                <th class="p-4">Planta</th><th class="p-4 text-right">Mov.</th><th class="p-4 text-right">Kg</th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-50">
                            <?php foreach ($porPlanta as $r): ?>
                                <tr>
                                    <td class="p-4 font-bold text-slate-700"><?= htmlspecialchars((string) $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="p-4 text-right font-semibold text-slate-600"><?= (int) $r['movimientos'] ?></td>
                                    <td class="p-4 text-right font-black text-slate-800"><?= number_format((float) $r['kg'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($porPlanta === []): ?>
                                <tr><td colspan="3" class="p-6 text-center text-slate-400">Sin datos</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 bg-slate-50 border-b border-slate-100">
                        <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">Top clientes (kg)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead><tr class="text-slate-400 font-black uppercase text-[10px] border-b border-slate-100">
                                <th class="p-4">Cliente</th><th class="p-4 text-right">Mov.</th><th class="p-4 text-right">Kg</th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-50">
                            <?php foreach ($topClientes as $r): ?>
                                <tr>
                                    <td class="p-4 font-bold text-slate-700"><?= htmlspecialchars((string) $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="p-4 text-right font-semibold text-slate-600"><?= (int) $r['movimientos'] ?></td>
                                    <td class="p-4 text-right font-black text-slate-800"><?= number_format((float) $r['kg'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($topClientes === []): ?>
                                <tr><td colspan="3" class="p-6 text-center text-slate-400">Sin datos</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 bg-slate-50 border-b border-slate-100">
                        <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">Por actividad</h3>
                    </div>
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-white"><tr class="text-slate-400 font-black uppercase text-[10px] border-b border-slate-100">
                                <th class="p-4">Actividad</th><th class="p-4 text-right">Mov.</th><th class="p-4 text-right">Kg</th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-50">
                            <?php foreach ($porActividad as $r): ?>
                                <tr>
                                    <td class="p-4 font-bold text-slate-700"><?= htmlspecialchars((string) $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="p-4 text-right font-semibold text-slate-600"><?= (int) $r['movimientos'] ?></td>
                                    <td class="p-4 text-right font-black text-slate-800"><?= number_format((float) $r['kg'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-8 py-4 bg-slate-50 border-b border-slate-100">
                        <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">Por producto (top kg)</h3>
                    </div>
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-white"><tr class="text-slate-400 font-black uppercase text-[10px] border-b border-slate-100">
                                <th class="p-4">Producto</th><th class="p-4 text-right">Mov.</th><th class="p-4 text-right">Kg</th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-50">
                            <?php foreach ($porProducto as $r): ?>
                                <tr>
                                    <td class="p-4 font-bold text-slate-700"><?= htmlspecialchars((string) $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="p-4 text-right font-semibold text-slate-600"><?= (int) $r['movimientos'] ?></td>
                                    <td class="p-4 text-right font-black text-slate-800"><?= number_format((float) $r['kg'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-10">
            <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Histórico total en base de datos</h2>
            <p class="text-sm text-slate-500 mb-4">Sin filtro de fecha (todos los registros de programación).</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Kilogramos acumulados</p>
                    <p class="text-2xl font-black text-slate-800 mt-1"><?= number_format($kgTotal, 0, ',', '.') ?> <span class="text-sm text-slate-400">kg</span></p>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Movimientos totales</p>
                    <p class="text-2xl font-black text-slate-800 mt-1"><?= number_format($movTotal) ?></p>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                     <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Maestros (referencia)</p>
                     <p class="text-sm text-slate-600 mt-2">
                        Clientes en catálogo: <strong><?= $nClientesMaestro !== null ? number_format($nClientesMaestro) : '—' ?></strong>
                     </p>
                     <p class="text-sm text-slate-600 mt-1">
                        Empleados: <strong><?= $nEmpleados !== null ? number_format($nEmpleados) : '—' ?></strong>
                     </p>
                </div>
            </div>
        </section>
    </main>
    <?php mostrarFooter(); ?>
</div>
</body>
</html>
