<?php 
require_once 'auth.php'; 
require_once 'conexion.php';

// --- Lógica de Filtros y Fecha (Mantenemos tu lógica que ya funciona) ---
$dia_f = $_GET['dia'] ?? '';
$mes_f = $_GET['mes'] ?? '';
$anio_f = $_GET['anio'] ?? '';

$fecha_hoy = date('Y-m-d');
$default_date = $fecha_hoy;

if ($anio_f !== '' && $mes_f !== '') {
    $dia_focus = ($dia_f !== '') ? str_pad($dia_f, 2, '0', STR_PAD_LEFT) : '01';
    $default_date = "$anio_f-$mes_f-$dia_focus";
} elseif ($anio_f !== '') {
    $default_date = "$anio_f-01-01";
}

// --- Consulta SQL ---
$sql = "SELECT p.*, c.Cliente as NomCli, pl.Planta as NomPlan, pr.Producto as NomProd 
        FROM Programacion p 
        LEFT JOIN Clientes c ON p.Cliente = c.ID_Cliente 
        LEFT JOIN Planta pl ON p.Planta = pl.ID_Planta 
        LEFT JOIN Producto pr ON p.Producto = pr.ID_Producto";

$stmt = $pdo->query($sql);
$registros = $stmt->fetchAll();

$events_js = [];
foreach($registros as $e){
    $fecha_db = trim($e['Fecha_de_Operacion']); 
    $partes = explode('/', $fecha_db);
    if(count($partes) == 3){
        $d = str_pad($partes[0], 2, '0', STR_PAD_LEFT);
        $m = str_pad($partes[1], 2, '0', STR_PAD_LEFT);
        $a = $partes[2];
        $fecha_iso = "$a-$m-$d"; 

        if ($dia_f !== '' && $d !== str_pad($dia_f, 2, '0', STR_PAD_LEFT)) continue;
        if ($mes_f !== '' && $m !== str_pad($mes_f, 2, '0', STR_PAD_LEFT)) continue;
        if ($anio_f !== '' && $a !== $anio_f) continue;

        $planta = strtoupper($e['NomPlan'] ?? '');
        $color = '#94a3b8';
        if (strpos($planta, 'BENEFICIO') !== false) $color = '#3b82f6';
        if (strpos($planta, 'DESPOSTE') !== false) $color = '#10b981';

        $events_js[] = [
            'title' => $e['Hora']." - ".$e['NomCli'],
            'start' => $fecha_iso,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'planta' => $e['NomPlan'] ?? 'N/A',
                'producto' => $e['NomProd'] ?? 'N/A',
                'cantidad' => number_format($e['Cantidad'])." kg",
                'vehiculo' => $e['Vehiculo'] ?? 'S/N'
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Calendario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        /* Ajustes para FullCalendar */
        .fc { max-height: 800px; }
        .fc-event { cursor: pointer; padding: 2px 5px; border-radius: 6px; font-size: 9px; font-weight: 700; border: none !important; }
        .fc-toolbar-title { font-size: 1.5rem !important; font-weight: 800; color: #1e293b; }
        .fc-button-primary { background-color: #0f172a !important; border: none !important; border-radius: 10px !important; font-size: 12px !important; font-weight: bold !important; transition: all 0.2s; }
        .fc-button-primary:hover { background-color: #334155 !important; }
        .fc-daygrid-day-number { font-weight: 700; color: #64748b; text-decoration: none; }
    </style>
</head>
<body class="flex min-h-screen bg-slate-50 overflow-x-hidden">
    
    <?php mostrarSidebar('cal'); ?>
    
    <main class="flex-1 ml-64 p-8 flex flex-col">
        
        <header class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
            <div>
                <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Calendario Maestro</h2>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Programación de Despachos</p>
            </div>
            
            <form class="flex items-center gap-2 bg-slate-50 p-2 rounded-2xl border border-slate-200">
                <select name="dia" class="bg-transparent text-[11px] font-bold px-2 outline-none">
                    <option value="">Día</option>
                    <?php for($i=1;$i<=31;$i++) echo "<option value='$i' ".($dia_f==$i?'selected':'').">".str_pad($i,2,'0',STR_PAD_LEFT)."</option>"; ?>
                </select>
                <div class="h-4 w-px bg-slate-300"></div>
                <select name="mes" class="bg-transparent text-[11px] font-bold px-2 outline-none">
                    <option value="">Mes</option>
                    <?php 
                    $ms=['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
                    foreach($ms as $v=>$n) echo "<option value='$v' ".($mes_f==$v?'selected':'').">$n</option>"; 
                    ?>
                </select>
                <div class="h-4 w-px bg-slate-300"></div>
                <select name="anio" class="bg-transparent text-[11px] font-bold px-2 outline-none">
                    <option value="">Año</option>
                    <option value="2025" <?= $anio_f=='2025'?'selected':'' ?>>2025</option>
                    <option value="2026" <?= $anio_f=='2026'?'selected':'' ?>>2026</option>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-blue-700 transition shadow-md">
                    Filtrar
                </button>
                <a href="view_data.php" class="p-2 text-slate-400 hover:text-red-500 transition" title="Limpiar Filtros">✕</a>
            </form>
        </header>

        <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100 flex-1">
            <div id="calendar"></div>
        </div>

        <?php mostrarFooter(); ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: '<?= $default_date ?>',
                locale: 'es',
                firstDay: 1, // Lunes como primer día
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listMonth'
                },
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    list: 'Lista'
                },
                events: <?= json_encode($events_js) ?>,
                eventClick: function(info) {
                    const p = info.event.extendedProps;
                    alert(`📋 DETALLE DE OPERACIÓN\n----------------------------\n👤 CLIENTE: ${info.event.title}\n🏭 PLANTA: ${p.planta}\n🥩 PRODUCTO: ${p.producto}\n⚖️ CANTIDAD: ${p.cantidad}\n🚛 VEHÍCULO: ${p.vehiculo}`);
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>