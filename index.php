<?php 
require_once 'auth.php'; 
require_once 'conexion.php';

// 1. CONSULTAS PARA INDICADORES SUPERIORES (KPIs)
$total_kg = $pdo->query("SELECT SUM(Cantidad) FROM Programacion")->fetchColumn() ?: 0;
$total_prog = $pdo->query("SELECT COUNT(*) FROM Programacion")->fetchColumn() ?: 0;
$ejecutados = $pdo->query("SELECT COUNT(*) FROM Programacion WHERE Estado_Actividad = 'EJECUTADO'")->fetchColumn() ?: 0;
$otif_perc = ($total_prog > 0) ? round(($ejecutados / $total_prog) * 100, 1) : 0;

// 2. DATOS PARA GRÁFICA: VOLUMEN POR PLANTA
$sql_planta = "SELECT pl.Planta, SUM(p.Cantidad) as total_kg 
               FROM Programacion p 
               JOIN Planta pl ON p.Planta = pl.ID_Planta 
               GROUP BY pl.Planta";
$res_planta = $pdo->query($sql_planta)->fetchAll(PDO::FETCH_ASSOC);

// 3. DATOS PARA GRÁFICA: TOP 5 CLIENTES (POR VOLUMEN)
$sql_clientes = "SELECT c.Cliente, SUM(p.Cantidad) as kg 
                 FROM Programacion p 
                 JOIN Clientes c ON p.Cliente = c.ID_Cliente 
                 GROUP BY c.Cliente ORDER BY kg DESC LIMIT 5";
$res_clientes = $pdo->query($sql_clientes)->fetchAll(PDO::FETCH_ASSOC);

// 4. DATOS PARA GRÁFICA DE TORTA: ESTADOS DE ACTIVIDAD
$cont_ejecutado = $pdo->query("SELECT COUNT(*) FROM Programacion WHERE Estado_Actividad = 'EJECUTADO'")->fetchColumn() ?: 0;
$cont_programado = $pdo->query("SELECT COUNT(*) FROM Programacion WHERE Estado_Actividad = 'PROGRAMADO'")->fetchColumn() ?: 0;
$cont_cancelado = $pdo->query("SELECT COUNT(*) FROM Programacion WHERE Estado_Actividad = 'CANCELADO'")->fetchColumn() ?: 0;

// 5. DATOS PARA GRÁFICA: TENDENCIA DIARIA (Últimos 7 días)
$sql_trend = "SELECT Fecha_de_Operacion, SUM(Cantidad) as kg 
              FROM Programacion 
              GROUP BY Fecha_de_Operacion 
              ORDER BY STR_TO_DATE(Fecha_de_Operacion, '%d/%m/%Y') DESC LIMIT 7";
$res_trend = array_reverse($pdo->query($sql_trend)->fetchAll(PDO::FETCH_ASSOC));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Dashboard Operativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .card-shadow { box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.03), 0 10px 10px -5px rgba(0, 0, 0, 0.02); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php mostrarSidebar('home'); ?>

    <div class="flex-1 flex flex-col ml-64 min-h-screen">
        
        <main class="p-10 flex-grow">
            <header class="mb-10 flex justify-between items-end">
                <div>
                    <h1 class="text-4xl font-extrabold text-slate-800 tracking-tight">Panel de Control</h1>
                    <p class="text-slate-500 font-medium italic">Resumen general de la operación logística.</p>
                </div>
                <div class="bg-white px-6 py-3 rounded-2xl border border-slate-100 shadow-sm text-center">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Estado del Sistema</span>
                    <span class="flex items-center gap-2 text-emerald-500 font-bold text-xs uppercase">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span> Sincronizado
                    </span>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="bg-white p-8 rounded-[2.5rem] card-shadow border border-slate-50">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Carga Acumulada</p>
                    <h3 class="text-3xl font-black text-slate-800"><?= number_format($total_kg) ?> <span class="text-sm text-slate-400">kg</span></h3>
                </div>
                <div class="bg-white p-8 rounded-[2.5rem] card-shadow border border-slate-50">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Viajes Totales</p>
                    <h3 class="text-3xl font-black text-slate-800"><?= $total_prog ?></h3>
                </div>
                <div class="bg-white p-8 rounded-[2.5rem] card-shadow border border-slate-50">
                    <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-2">Puntaje OTIF</p>
                    <h3 class="text-3xl font-black text-blue-600"><?= $otif_perc ?>%</h3>
                </div>
                <div class="bg-white p-8 rounded-[2.5rem] card-shadow border border-slate-50">
                    <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2">Cumplimiento</p>
                    <h3 class="text-3xl font-black text-emerald-500">96.8%</h3>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
                <div class="lg:col-span-2 bg-white p-10 rounded-[3rem] card-shadow border border-slate-50">
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-8">Volumen por Planta de Proceso</h4>
                    <div class="h-80"><canvas id="chartPlanta"></canvas></div>
                </div>

                <div class="bg-white p-10 rounded-[3rem] card-shadow border border-slate-50 text-center">
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-8">Estatus de Actividad</h4>
                    <div class="h-64 flex justify-center"><canvas id="chartEstados"></canvas></div>
                    <div class="mt-6">
                        <span class="text-3xl font-black text-slate-800"><?= $total_prog ?></span>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Registros Totales</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white p-10 rounded-[3rem] card-shadow border border-slate-50">
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-8">Tendencia de Carga Diaria</h4>
                    <div class="h-72"><canvas id="chartTrend"></canvas></div>
                </div>
                <div class="bg-white p-10 rounded-[3rem] card-shadow border border-slate-50">
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-8">Top 5 Clientes Prioritarios</h4>
                    <div class="h-72"><canvas id="chartClientes"></canvas></div>
                </div>
            </div>
        </main>

        <?php mostrarFooter(); ?>
    </div>

    <script>
        Chart.defaults.font.family = "'Plus Jakarta Sans'";
        Chart.defaults.color = '#94a3b8';

        new Chart(document.getElementById('chartPlanta'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($res_planta, 'Planta')) ?>,
                datasets: [{
                    label: 'Kilogramos',
                    data: <?= json_encode(array_column($res_planta, 'total_kg')) ?>,
                    backgroundColor: ['#3b82f6', '#10b981'],
                    borderRadius: 20,
                    barThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
                }
            }
        });

        new Chart(document.getElementById('chartEstados'), {
            type: 'doughnut',
            data: {
                labels: ['Ejecutado', 'Programado', 'Cancelado'],
                datasets: [{
                    data: [<?= $cont_ejecutado ?>, <?= $cont_programado ?>, <?= $cont_cancelado ?>],
                    backgroundColor: ['#f59e0b', '#ef4444', '#10b981'],
                    hoverOffset: 15,
                    borderWidth: 0,
                    cutout: '80%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20, boxWidth: 10, font: { size: 11, weight: 'bold' } }
                    }
                }
            }
        });

        new Chart(document.getElementById('chartTrend'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($res_trend, 'Fecha_de_Operacion')) ?>,
                datasets: [{
                    label: 'KG',
                    data: <?= json_encode(array_column($res_trend, 'kg')) ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.05)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 4,
                    pointRadius: 5,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { display: false },
                    x: { grid: { display: false }, ticks: { font: { weight: 'bold' } } }
                }
            }
        });

        new Chart(document.getElementById('chartClientes'), {
            type: 'bar',
            indexAxis: 'y',
            data: {
                labels: <?= json_encode(array_column($res_clientes, 'Cliente')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($res_clientes, 'kg')) ?>,
                    backgroundColor: '#6366f1',
                    borderRadius: 15,
                    barThickness: 25
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    x: { display: false },
                    y: { grid: { display: false }, ticks: { font: { weight: 'bold', size: 10 } } }
                }
            }
        });
    </script>
</body>
</html>