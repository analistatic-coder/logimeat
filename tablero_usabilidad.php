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
$stats = lm_usabilidad_estadisticas($pdo, $dias);
$tablaLista = $stats['tabla_ok'];

$maxMod = 0;
foreach ($stats['modulos'] as $row) {
    $maxMod = max($maxMod, (int) $row['c']);
}
$maxUser = 0;
foreach ($stats['usuarios_top'] as $row) {
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
                <p class="text-slate-500 mt-2">Inicios de sesión y pantallas más usadas (solo Super Admin). Se actualiza solo cada pocos segundos.</p>
                <p id="lum-actualizado" class="text-[10px] font-black uppercase text-violet-600 tracking-wider mt-2"></p>
            </div>
            <form method="get" class="flex items-center gap-3" id="lum-form-dias">
                <label for="dias" class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Periodo</label>
                <select id="dias" name="dias" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700" onchange="this.form.submit()">
                    <?php foreach ($opcionesPeriodo as $val => $et): ?>
                    <option value="<?= (int) $val ?>" <?= $dias === (int) $val ? 'selected' : '' ?>><?= htmlspecialchars($et) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </header>

        <?php if (!$tablaLista || ($stats['error'] ?? '') === 'sin_tabla'): ?>
        <div class="rounded-[1.75rem] border border-amber-200 bg-amber-50 p-8 text-amber-900 text-sm mb-10">
            <p class="font-bold mb-2">Aún no existe la tabla de auditoría o no hay permisos para crearla.</p>
            <p class="mb-3">En el servidor puede ejecutar:</p>
            <code class="block bg-white/70 rounded-xl px-4 py-2 text-xs font-mono">php scripts/aplicar_schema_usabilidad.php</code>
        </div>
        <?php elseif (($stats['error'] ?? '') === 'consulta'): ?>
        <div class="rounded-[1.75rem] border border-red-200 bg-red-50 p-8 text-red-900 text-sm mb-10">
            <p class="font-bold">No se pudieron leer las estadísticas. Revise el registro de errores del servidor (MySQL / ONLY_FULL_GROUP_BY).</p>
        </div>
        <?php endif; ?>

        <?php if ($tablaLista): ?>
        <p id="lum-total-bd" class="mb-6 text-center text-xs font-bold text-slate-600">
            Eventos guardados en base de datos (sin filtrar por periodo): <span id="lum-total-bd-num"><?= number_format((int) ($stats['total_eventos'] ?? 0), 0, ',', '.') ?></span>
            <?php if ((int) ($stats['total_eventos'] ?? 0) === 0): ?>
                <span class="block mt-2 text-amber-700 font-semibold normal-case">Si sigue en 0 tras usar el sistema, el usuario MySQL puede no tener permiso CREATE/ALTER: ejecute <code class="bg-slate-100 px-1 rounded">php scripts/aplicar_schema_usabilidad.php</code> en el servidor o cree la tabla manualmente.</span>
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <p class="text-[10px] font-black text-violet-500 uppercase tracking-widest mb-2">Inicios de sesión</p>
                <p id="lum-kpi-logins" class="text-4xl font-black text-slate-800"><?= number_format($stats['total_logins'], 0, ',', '.') ?></p>
                <p class="text-xs text-slate-400 mt-2">Credenciales correctas registradas.</p>
            </div>
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-2">Vistas de pantalla</p>
                <p id="lum-kpi-paginas" class="text-4xl font-black text-slate-800"><?= number_format($stats['total_paginas'], 0, ',', '.') ?></p>
                <p class="text-xs text-slate-400 mt-2">Recargas incluidas; una navegación = un registro.</p>
            </div>
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2">Usuarios activos</p>
                <p id="lum-kpi-usuarios" class="text-4xl font-black text-slate-800"><?= number_format($stats['usuarios_activos'], 0, ',', '.') ?></p>
                <p class="text-xs text-slate-400 mt-2">Con al menos una visita registrada.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-10">
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight mb-6">Sesiones por día</h2>
                <div id="lum-chart-wrap" class="<?= ($stats['sesiones_por_dia'] === []) ? 'hidden' : '' ?>">
                    <canvas id="chartSesiones" height="220"></canvas>
                </div>
                <p id="lum-chart-empty" class="text-slate-400 text-sm <?= ($stats['sesiones_por_dia'] !== []) ? 'hidden' : '' ?>">Sin datos en este periodo.</p>
            </div>
            <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8">
                <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight mb-6">Módulos más visitados</h2>
                <p id="lum-modulos-empty" class="text-slate-400 text-sm <?= ($stats['modulos'] !== []) ? 'hidden' : '' ?>">Sin visitas registradas.</p>
                <ul id="lum-modulos-list" class="space-y-4 <?= ($stats['modulos'] === []) ? 'hidden' : '' ?>">
                    <?php foreach ($stats['modulos'] as $row):
                        $cnt = (int) $row['c'];
                        $w = $maxMod > 0 ? (int) round($cnt / $maxMod * 100) : 100;
                        $mod = (string) $row['modulo'];
                        $et = (string) ($row['etiqueta'] ?? lm_usabilidad_etiqueta_modulo($mod));
                        ?>
                    <li class="lum-modulo-row">
                        <div class="flex justify-between text-[11px] font-bold uppercase tracking-tight text-slate-600 mb-1 gap-4">
                            <span class="truncate" title="<?= htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($et) ?></span>
                            <span class="text-violet-600 shrink-0 lum-modulo-cnt"><?= number_format($cnt, 0, ',', '.') ?></span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-blue-500 lum-modulo-bar" style="width: <?= $w ?>%;"></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="bg-white rounded-[1.75rem] border border-slate-100 shadow-sm p-8 mb-10">
            <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight mb-6">Usuarios más activos</h2>
            <p id="lum-usuarios-empty" class="text-slate-400 text-sm <?= ($stats['usuarios_top'] !== []) ? 'hidden' : '' ?>">Sin datos.</p>
            <ul id="lum-usuarios-list" class="grid grid-cols-1 md:grid-cols-2 gap-6 <?= ($stats['usuarios_top'] === []) ? 'hidden' : '' ?>">
                <?php foreach ($stats['usuarios_top'] as $row):
                    $cnt = (int) $row['c'];
                    $w = $maxUser > 0 ? (int) round($cnt / $maxUser * 100) : 100;
                    ?>
                <li class="lum-usuario-row">
                    <div class="flex justify-between text-sm font-bold text-slate-700 mb-1 gap-2">
                        <span class="truncate lum-usuario-nombre"><?= htmlspecialchars((string) ($row['nombre'] ?? '')) ?></span>
                        <span class="text-slate-400 shrink-0 lum-usuario-cnt"><?= number_format($cnt, 0, ',', '.') ?></span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-500 lum-usuario-bar" style="width: <?= $w ?>%;"></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <p class="text-center text-[10px] font-bold uppercase text-slate-400 tracking-wider">
            Registro iniciado después del despliegue de auditoría · No incluye trabajo previo ni API sin sesión
        </p>
    </main>
    <?php mostrarFooter(); ?>
</div>

<script>
(function () {
    var diasEl = document.getElementById('dias');
    var chartInstance = null;

    function fmt(n) {
        return new Intl.NumberFormat('es-CO').format(Number(n) || 0);
    }

    function setMeta(t) {
        var el = document.getElementById('lum-actualizado');
        if (el) el.textContent = 'Última actualización: ' + t;
    }

    function destroyChart() {
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
    }

    function renderChart(rows) {
        var wrap = document.getElementById('lum-chart-wrap');
        var empty = document.getElementById('lum-chart-empty');
        var ctx = document.getElementById('chartSesiones');
        if (!wrap || !empty || !ctx) return;

        if (!rows || rows.length === 0) {
            destroyChart();
            wrap.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');
        wrap.classList.remove('hidden');

        var labels = rows.map(function (r) { return r.dia; });
        var data = rows.map(function (r) { return Number(r.c); });

        destroyChart();
        chartInstance = new Chart(ctx, {
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
    }

    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }
    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function renderModulos(modulos) {
        var list = document.getElementById('lum-modulos-list');
        var empty = document.getElementById('lum-modulos-empty');
        if (!list || !empty) return;
        if (!modulos || modulos.length === 0) {
            list.innerHTML = '';
            list.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');
        list.classList.remove('hidden');
        var maxC = 0;
        modulos.forEach(function (m) { maxC = Math.max(maxC, Number(m.c) || 0); });
        list.innerHTML = modulos.map(function (m) {
            var c = Number(m.c) || 0;
            var w = maxC > 0 ? Math.round(c / maxC * 100) : 100;
            var et = String(m.etiqueta || m.modulo || '');
            var mod = String(m.modulo || '');
            return '<li class="lum-modulo-row">' +
                '<div class="flex justify-between text-[11px] font-bold uppercase tracking-tight text-slate-600 mb-1 gap-4">' +
                '<span class="truncate" title="' + escAttr(mod) + '">' + escHtml(et) + '</span>' +
                '<span class="text-violet-600 shrink-0">' + fmt(c) + '</span></div>' +
                '<div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">' +
                '<div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-blue-500" style="width:' + w + '%;"></div></div></li>';
        }).join('');
    }

    function renderUsuarios(rows) {
        var list = document.getElementById('lum-usuarios-list');
        var empty = document.getElementById('lum-usuarios-empty');
        if (!list || !empty) return;
        if (!rows || rows.length === 0) {
            list.innerHTML = '';
            list.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');
        list.classList.remove('hidden');
        var maxC = 0;
        rows.forEach(function (r) { maxC = Math.max(maxC, Number(r.c) || 0); });
        list.innerHTML = rows.map(function (r) {
            var c = Number(r.c) || 0;
            var w = maxC > 0 ? Math.round(c / maxC * 100) : 100;
            var nom = String(r.nombre || '');
            return '<li><div class="flex justify-between text-sm font-bold text-slate-700 mb-1 gap-2">' +
                '<span class="truncate">' + escHtml(nom) + '</span>' +
                '<span class="text-slate-400 shrink-0">' + fmt(c) + '</span></div>' +
                '<div class="h-2 rounded-full bg-slate-100 overflow-hidden">' +
                '<div class="h-full rounded-full bg-emerald-500" style="width:' + w + '%;"></div></div></li>';
        }).join('');
    }

    function aplicarPayload(payload) {
        if (!payload || !payload.ok || !payload.stats) return;
        var s = payload.stats;
        var k1 = document.getElementById('lum-kpi-logins');
        var k2 = document.getElementById('lum-kpi-paginas');
        var k3 = document.getElementById('lum-kpi-usuarios');
        if (k1) k1.textContent = fmt(s.total_logins);
        if (k2) k2.textContent = fmt(s.total_paginas);
        if (k3) k3.textContent = fmt(s.usuarios_activos);
        var tbd = document.getElementById('lum-total-bd-num');
        if (tbd && typeof s.total_eventos !== 'undefined') tbd.textContent = fmt(s.total_eventos);
        renderChart(s.sesiones_por_dia || []);
        renderModulos(s.modulos || []);
        renderUsuarios(s.usuarios_top || []);
        setMeta(payload.actualizado || '');
    }

    function poll() {
        var d = diasEl ? diasEl.value : '30';
        var url = 'tablero_usabilidad_datos.php?dias=' + encodeURIComponent(d) + '&_=' + Date.now();
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(aplicarPayload)
            .catch(function () {
                setMeta('Error al actualizar (sin conexión o sesión caducada)');
            });
    }

    setMeta(<?= json_encode(date('Y-m-d H:i:s'), JSON_UNESCAPED_UNICODE) ?>);
    <?php if ($tablaLista && $stats['sesiones_por_dia'] !== []): ?>
    renderChart(<?= json_encode($stats['sesiones_por_dia'], JSON_UNESCAPED_UNICODE) ?>);
    <?php endif; ?>

    setInterval(poll, 12000);
    setTimeout(poll, 2000);
})();
</script>
</body>
</html>
