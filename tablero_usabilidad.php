<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/usabilidad_log.php';

if (!lm_es_super_admin()) {
    header('Location: index.php');
    exit();
}

$dias = isset($_GET['dias']) ? max(7, min(365, (int) $_GET['dias'])) : 30;
$opcionesPeriodo = [7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => '1 año'];
lm_usabilidad_ensure_table($pdo);
$tablaLista = lm_usabilidad_tabla_exist($pdo);
$fechaDesde = date('Y-m-d H:i:s', strtotime('-' . $dias . ' days'));

$totalLogins = 0;
$totalPaginas = 0;
$usuariosActivos = 0;
$sesionesPorDia = [];
$modulosTop = [];
$usuariosTop = [];

if ($tablaLista) {
    try {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM app_usabilidad_evento WHERE tipo = \'login\' AND creado_en >= ?'
        );
        $st->execute([$fechaDesde]);
        $totalLogins = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM app_usabilidad_evento WHERE tipo = \'pagina\' AND creado_en >= ?'
        );
        $st->execute([$fechaDesde]);
        $totalPaginas = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            'SELECT COUNT(DISTINCT id_user) FROM app_usabilidad_evento
             WHERE tipo = \'pagina\' AND id_user IS NOT NULL AND creado_en >= ?'
        );
        $st->execute([$fechaDesde]);
        $usuariosActivos = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            'SELECT DATE(creado_en) AS dia, COUNT(*) AS c
             FROM app_usabilidad_evento
             WHERE tipo = \'login\' AND creado_en >= ?
             GROUP BY DATE(creado_en)
             ORDER BY dia ASC'
        );
        $st->execute([$fechaDesde]);
        $sesionesPorDia = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $pdo->prepare(
            'SELECT modulo, COUNT(*) AS c
             FROM app_usabilidad_evento
             WHERE tipo = \'pagina\' AND creado_en >= ?
             GROUP BY modulo ORDER BY c DESC LIMIT 18'
        );
        $st->execute([$fechaDesde]);
        $modulosTop = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $pdo->prepare(
            'SELECT e.id_user, COALESCE(NULLIF(TRIM(u.Nombre), \'\'), CONCAT(\'ID \', e.id_user)) AS nombre, COUNT(*) AS c
             FROM app_usabilidad_evento e
             LEFT JOIN `User` u ON u.ID_User = e.id_user
             WHERE e.tipo = \'pagina\' AND e.id_user IS NOT NULL AND e.creado_en >= ?
             GROUP BY e.id_user, u.Nombre ORDER BY c DESC LIMIT 12'
        );
        $st->execute([$fechaDesde]);
        $usuariosTop = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $tablaLista = false;
    }
}

$maxMod = 0;
foreach ($modulosTop as $row) {
    $maxMod = max($maxMod, (int) $row['c']);
}
$maxUser = 0;
foreach ($usuariosTop as $row) {
    $maxUser = max($maxUser, (int) $row['c']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiMeat | Usabilidad del sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex min-h-screen">

<?php mostrarSidebar('usabilidad'); ?>

<div class="flex-1 flex flex-col ml-64 min-h-screen">
    <main class="p-10 flex-grow max-w-[120rem] mx-auto w-full">
        <header class="mb-10 flex flex-col lg:flex-row lg:justify-between lg:items-end gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-slate-800 tracking-tight">Usabilidad</h1>
                <p class="text-slate-500 mt-2">Inicios de sesión y pantallas más usadas (solo Super Admin).</p>
            </div>
            <form method="get" class="flex items-center gap-3">
                <label for="dias" class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Periodo</label>
                <select id="dias" name="dias" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700" onchange="this.form.submit()">
                    <?php foreach ($opcionesPeriodo as $val => $et): ?>
                    <option value="<?= (int) $val ?>" <?= $dias === (int) $val ? 'selected' : '' ?>><?= htmlspecialchars($et) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </header>

        <?php if (!$tablaLista): ?>
        <div class="rounded-[1.75rem] border border-amber-200 bg-amber-50 p-8 text-amber-900 text-sm mb-10">
            <p class="font-bold mb-2">Aún no hay datos de uso.</p>
            <p class="mb-3">Los eventos se guardan cuando los usuarios entran tras el despliegue. Opcionalmente puede crear la tabla a mano ejecutando:</p>
            <code class="block bg-white/70 rounded-xl px-4 py-2 text-xs font-mono">php scripts/aplicar_schema_usabilidad.php</code>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <p class="text-[10px] font-black text-violet-500 uppercase tracking-widest mb-2">Inicios de sesión</p>
                <p class="text-4xl font-black text-slate-800"><?= number_format($totalLogins, 0, ',', '.') ?></p>
                <p class="text-xs text-slate-400 mt-2">Credenciales correctas registradas.</p>
            </div>
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-2">Vistas de pantalla</p>
                <p class="text-4xl font-black text-slate-800"><?= number_format($totalPaginas, 0, ',', '.') ?></p>
                <p class="text-xs text-slate-400 mt-2">Recargas incluidas; una navegación = un registro.</p>
            </div>
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2">Usuarios activos</p>
                <p class="text-4xl font-black text-slate-800"><?= number_format($usuariosActivos, 0, ',', '.') ?></p>
                <p class="text-xs text-slate-400 mt-2">Con al menos una visita registrada.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-10">
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight mb-6">Sesiones por día</h2>
                <?php if ($sesionesPorDia === []): ?>
                <p class="text-slate-400 text-sm">Sin datos en este periodo.</p>
                <?php else: ?>
                <canvas id="chartSesiones" height="220"></canvas>
                <?php endif; ?>
            </div>
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight mb-6">Módulos más visitados</h2>
                <?php if ($modulosTop === []): ?>
                <p class="text-slate-400 text-sm">Sin visitas registradas.</p>
                <?php else: ?>
                <ul class="space-y-4">
                    <?php foreach ($modulosTop as $row):
                        $cnt = (int) $row['c'];
                        $w = $maxMod > 0 ? (int) round($cnt / $maxMod * 100) : 100;
                        $mod = (string) $row['modulo'];
                        ?>
                    <li>
                        <div class="flex justify-between text-[11px] font-bold uppercase tracking-tight text-slate-600 mb-1 gap-4">
                            <span class="truncate" title="<?= htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(lm_usabilidad_etiqueta_modulo($mod)) ?>
                            </span>
                            <span class="text-violet-600 shrink-0"><?= number_format($cnt, 0, ',', '.') ?></span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-blue-500" style="width: <?= $w ?>%;"></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8 mb-10">
            <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight mb-6">Usuarios más activos</h2>
            <?php if ($usuariosTop === []): ?>
            <p class="text-slate-400 text-sm">Sin datos.</p>
            <?php else: ?>
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($usuariosTop as $row):
                    $cnt = (int) $row['c'];
                    $w = $maxUser > 0 ? (int) round($cnt / $maxUser * 100) : 100;
                    ?>
                <li>
                    <div class="flex justify-between text-sm font-bold text-slate-700 mb-1 gap-2">
                        <span class="truncate"><?= htmlspecialchars((string) ($row['nombre'] ?? '')) ?></span>
                        <span class="text-slate-400 shrink-0"><?= number_format($cnt, 0, ',', '.') ?></span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-500" style="width: <?= $w ?>%;"></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <p class="text-center text-[10px] font-bold uppercase text-slate-400 tracking-wider">
            Registro iniciado después del despliegue de auditoría · No incluye trabajo previo ni API sin sesión
        </p>
    </main>
    <?php mostrarFooter(); ?>
</div>

<?php if ($tablaLista && $sesionesPorDia !== []): ?>
<script>
(function () {
    var raw = <?= json_encode($sesionesPorDia, JSON_UNESCAPED_UNICODE) ?>;
    var labels = raw.map(function (r) { return r.dia; });
    var data = raw.map(function (r) { return Number(r.c); });
    var ctx = document.getElementById('chartSesiones');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Inicios',
                data: data,
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124, 58, 237, 0.12)',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: { legend: { display: false } }
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
