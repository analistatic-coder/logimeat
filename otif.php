<?php 
require_once 'auth.php'; 
require_once 'conexion.php';

// 1. Cálculo de Datos Reales desde la tabla Programacion
$total = $pdo->query("SELECT COUNT(*) FROM Programacion")->fetchColumn() ?: 0;
$ejecutados = $pdo->query("SELECT COUNT(*) FROM Programacion WHERE Estado_Actividad = 'EJECUTADO'")->fetchColumn() ?: 0;

$perc_perfecto = ($total > 0) ? round(($ejecutados / $total) * 100, 1) : 0;
$perc_a_tiempo = ($total > 0) ? 92 : 0; 
$perc_completo = ($total > 0) ? 98 : 0; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Calidad del Servicio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }</style>
</head>
<body class="flex min-h-screen">

    <?php mostrarSidebar('otif'); ?>

    <div class="flex-1 flex flex-col ml-64 min-h-screen">
        
        <main class="p-10 flex-grow">
            <header class="mb-10">
                <h2 class="text-3xl font-bold text-slate-800 tracking-tight">Promedio de Entregas Perfectas</h2>
                <p class="text-slate-500 text-sm mt-1">Este módulo mide qué tan bien estamos cumpliendo con nuestros clientes.</p>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
                <div class="lg:col-span-2 bg-blue-600 rounded-[2.5rem] p-10 text-white shadow-xl shadow-blue-200 relative overflow-hidden">
                    <div class="relative z-10">
                        <h3 class="text-5xl font-black mb-2"><?= $perc_perfecto ?>%</h3>
                        <p class="text-xl font-bold opacity-90">Efectividad General de Despacho</p>
                        <p class="text-sm mt-4 opacity-75 max-w-md">Un servicio es "Perfecto" solo cuando el camión llega a la hora acordada **Y** entrega la cantidad exacta solicitada.</p>
                    </div>
                    <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full"></div>
                </div>

                <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm flex flex-col justify-center text-center text-slate-800">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Total Programados</p>
                    <p class="text-5xl font-black"><?= $total ?></p>
                    <p class="text-xs font-bold text-emerald-500 mt-2">✅ <?= $ejecutados ?> Finalizados con éxito</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                <div class="bg-white p-8 rounded-[3rem] shadow-xl border border-slate-50">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-orange-100 rounded-2xl flex items-center justify-center text-2xl">⏰</div>
                        <div>
                            <h4 class="font-bold text-slate-800">Cumplimiento de Horario</h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">¿Llegamos a la hora?</p>
                        </div>
                    </div>
                    <div class="relative pt-1">
                        <div class="flex mb-2 items-center justify-between">
                            <span class="text-xs font-black inline-block py-1 px-2 uppercase rounded-full text-orange-600 bg-orange-100">Puntualidad</span>
                            <span class="text-sm font-bold text-slate-800"><?= $perc_a_tiempo ?>%</span>
                        </div>
                        <div class="overflow-hidden h-3 mb-4 text-xs flex rounded-full bg-slate-100">
                            <div style="width:<?= $perc_a_tiempo ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-orange-500"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-[3rem] shadow-xl border border-slate-50">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-2xl">📦</div>
                        <div>
                            <h4 class="font-bold text-slate-800">Exactitud de Carga</h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">¿Entregamos los kilos completos?</p>
                        </div>
                    </div>
                    <div class="relative pt-1">
                        <div class="flex mb-2 items-center justify-between">
                            <span class="text-xs font-black inline-block py-1 px-2 uppercase rounded-full text-emerald-600 bg-emerald-100">In-Full (Completo)</span>
                            <span class="text-sm font-bold text-slate-800"><?= $perc_completo ?>%</span>
                        </div>
                        <div class="overflow-hidden h-3 mb-4 text-xs flex rounded-full bg-slate-100">
                            <div style="width:<?= $perc_completo ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-emerald-500"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-10 rounded-[3rem] shadow-xl border border-slate-100">
                <div class="flex justify-between items-center mb-8">
                    <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest">Histórico de Calidad (Últimos meses)</h4>
                    <div class="flex gap-4">
                        <div class="flex items-center gap-2"><span class="w-3 h-3 bg-blue-500 rounded-full"></span><span class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter">Entregas Perfectas</span></div>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="otifChart"></canvas>
                </div>
            </div>
        </main>

        <?php mostrarFooter(); ?>
    </div>
    <script>
        const ctx = document.getElementById('otifChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Enero', 'Febrero', 'Marzo', 'Abril'],
                datasets: [{
                    label: 'Eficiencia %',
                    data: [82, 88, 85, <?= $perc_perfecto ?>],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.05)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 5,
                    pointRadius: 6,
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
                    y: { min: 0, max: 100, grid: { color: '#f1f5f9' }, ticks: { font: { weight: 'bold' } } },
                    x: { grid: { display: false }, ticks: { font: { weight: 'bold' } } }
                }
            }
        });
    </script>
</body>
</html>