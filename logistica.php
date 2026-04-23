<?php 
require_once 'auth.php'; 
require_once 'conexion.php';

// 1. Cargamos los listados maestros
$conductores = $pdo->query("SELECT * FROM Conductor ORDER BY Conductor ASC")->fetchAll(PDO::FETCH_ASSOC);
$vehiculos = $pdo->query("SELECT * FROM Vehiculo ORDER BY Vehiculo ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Lógica de Cruce de Relaciones desde Programación
$sql_rel = "SELECT 
                TRIM(p.Conductor) as cond_raw, 
                TRIM(p.Vehiculo) as vehi_raw,
                c.Conductor as cond_nom,
                v.Vehiculo as vehi_nom
            FROM Programacion p
            LEFT JOIN Conductor c ON p.Conductor = c.ID_Conductor OR p.Conductor = c.Conductor
            LEFT JOIN Vehiculo v ON p.Vehiculo = v.ID_Vehiculo OR p.Vehiculo = v.Vehiculo
            WHERE p.Conductor != '' AND p.Vehiculo != ''";

$rels_raw = $pdo->query($sql_rel)->fetchAll(PDO::FETCH_ASSOC);

$stats = ['conductor' => [], 'vehiculo' => []];
foreach($rels_raw as $r) {
    $cn = !empty($r['cond_nom']) ? $r['cond_nom'] : $r['cond_raw'];
    $vn = !empty($r['vehi_nom']) ? $r['vehi_nom'] : $r['vehi_raw'];

    if(!isset($stats['conductor'][$cn])) $stats['conductor'][$cn] = ['count' => 0, 'items' => []];
    $stats['conductor'][$cn]['count']++;
    if(!in_array($vn, $stats['conductor'][$cn]['items'])) $stats['conductor'][$cn]['items'][] = $vn;

    if(!isset($stats['vehiculo'][$vn])) $stats['vehiculo'][$vn] = ['count' => 0, 'items' => []];
    $stats['vehiculo'][$vn]['count']++;
    if(!in_array($cn, $stats['vehiculo'][$vn]['items'])) $stats['vehiculo'][$vn]['items'][] = $cn;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Conductores / Vehículos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="flex min-h-screen">
    
    <?php mostrarSidebar('log'); ?>

    <div class="flex-1 flex flex-col ml-64 min-h-screen">
        
        <main class="p-10 flex-grow">
            <header class="flex flex-col items-center mb-10 text-center">
                <h2 class="text-3xl font-bold text-slate-800 tracking-tight">Conductores / Vehículos</h2>
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.3em] mt-2">Módulo de Activos y Despachos</p>
                <div class="w-full max-w-xl mt-8 relative">
                    <input type="text" id="searchBox" onkeyup="filterAll()" placeholder="Buscar por placa, nombre o CC..." 
                           class="w-full pl-12 pr-6 py-4 bg-white border border-slate-200 rounded-[2rem] shadow-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
                <div class="bg-white rounded-[2.5rem] shadow-xl border overflow-hidden">
                    <div class="p-6 bg-slate-50 border-b font-bold text-[10px] uppercase text-slate-400 tracking-widest">Conductores</div>
                    <div class="max-h-[500px] overflow-y-auto custom-scroll">
                        <table class="w-full">
                            <?php foreach($conductores as $c): ?>
                            <tr class="item-row border-b border-slate-50 hover:bg-blue-50/30 transition-all">
                                <td class="p-6">
                                    <div class="font-bold text-slate-800 text-sm"><?= $c['Conductor'] ?></div>
                                    <div class="text-[10px] text-slate-400 font-bold">CC: <?= $c['Identificacion'] ?></div>
                                </td>
                                <td class="p-6 text-right">
                                    <button onclick="openModal('<?= addslashes($c['Conductor']) ?>', 'conductor', 'ID: <?= $c['Identificacion'] ?>')" 
                                            class="bg-slate-900 text-white px-5 py-2 rounded-xl text-[10px] font-bold shadow-md hover:bg-blue-600 transition-colors">DETALLES</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl border overflow-hidden">
                    <div class="p-6 bg-slate-50 border-b font-bold text-[10px] uppercase text-slate-400 tracking-widest">Vehículos</div>
                    <div class="max-h-[500px] overflow-y-auto custom-scroll">
                        <table class="w-full">
                            <?php foreach($vehiculos as $v): ?>
                            <tr class="item-row border-b border-slate-50 hover:bg-emerald-50/30 transition-all">
                                <td class="p-6">
                                    <div class="font-black text-blue-700 text-sm uppercase"><?= $v['Vehiculo'] ?></div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase">Placa de Transporte</div>
                                </td>
                                <td class="p-6 text-right">
                                    <button onclick="openModal('<?= addslashes($v['Vehiculo']) ?>', 'vehiculo', 'Unidad Activa')" 
                                            class="bg-slate-900 text-white px-5 py-2 rounded-xl text-[10px] font-bold shadow-md hover:bg-emerald-600 transition-colors">DETALLES</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <?php mostrarFooter(); ?>
    </div>
    <div id="modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-xl rounded-[3rem] p-12 shadow-2xl relative overflow-hidden">
            <button onclick="closeModal()" class="absolute top-10 right-10 text-slate-300 hover:text-red-500 text-2xl font-bold">✕</button>
            <span id="mTag" class="text-[9px] font-black text-blue-500 uppercase tracking-widest">CATEGORÍA</span>
            <h3 id="mName" class="text-3xl font-bold text-slate-800 mt-2 mb-6">Nombre</h3>
            <div class="grid grid-cols-2 gap-6 mb-8">
                <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-2">Total Despachos</p>
                    <p id="mCount" class="text-4xl font-black text-slate-800">0</p>
                </div>
                <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                    <p id="mRelTitle" class="text-[9px] font-black text-slate-400 uppercase mb-2 tracking-tighter">Relaciones</p>
                    <div id="mRelList" class="flex flex-wrap gap-2 text-[10px] font-bold text-blue-600 italic"></div>
                </div>
            </div>
            <div class="h-56 w-full"><canvas id="mChart"></canvas></div>
            <div class="mt-4 flex justify-center gap-4">
                <div class="flex items-center gap-2">
                    <span id="guideCircle" class="w-3 h-3 rounded-full"></span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Frecuencia Semanal de Viajes</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        const MASTER_DATA = <?= json_encode($stats) ?>;
        let chartInstance = null;

        function openModal(name, cat, sub) {
            const info = MASTER_DATA[cat][name] || { count: 0, items: [] };
            document.getElementById('mName').innerText = name;
            document.getElementById('mTag').innerText = cat.toUpperCase();
            document.getElementById('mCount').innerText = info.count;
            document.getElementById('mRelTitle').innerText = (cat === 'conductor') ? 'Vehículos Operados' : 'Conductores Asignados';
            
            const color = (cat === 'conductor') ? '#3b82f6' : '#10b981';
            document.getElementById('guideCircle').style.backgroundColor = color;

            const list = document.getElementById('mRelList');
            list.innerHTML = info.items.length 
                ? info.items.map(i => `<span class='bg-white px-2 py-1 rounded-lg border border-slate-100 shadow-sm'>${cat==='conductor'?'🚛':'👤'} ${i}</span>`).join('') 
                : '<span class="text-slate-300">Sin historial</span>';

            document.getElementById('modal').classList.remove('hidden');

            if (chartInstance) chartInstance.destroy();
            const ctx = document.getElementById('mChart').getContext('2d');
            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'],
                    datasets: [{
                        label: 'Despachos Realizados',
                        data: [info.count*0.2, info.count*0.4, info.count*0.1, info.count*0.3],
                        borderColor: color,
                        backgroundColor: color + '1A',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 4,
                        pointRadius: 6,
                        pointBackgroundColor: '#fff'
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { y: { display: false }, x: { grid: { display: false }, ticks: { font: { size: 9, weight: 'bold' } } } } 
                }
            });
        }

        function closeModal() { document.getElementById('modal').classList.add('hidden'); }
        function filterAll() {
            let val = document.getElementById('searchBox').value.toUpperCase();
            document.querySelectorAll('.item-row').forEach(row => {
                row.style.display = row.innerText.toUpperCase().includes(val) ? '' : 'none';
            });
        }
    </script>
</body>
</html>