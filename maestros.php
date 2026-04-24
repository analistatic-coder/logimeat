<?php 
require_once 'auth.php'; 

if (!lm_es_admin()) {
    header('Location: index.php');
    exit();
}

$secciones_maestros = [
    [
        'titulo' => 'Maestros logísticos',
        'descripcion' => 'Catálogos de operación y transporte.',
        'items' => [
            ['id' => 'clientes', 'label' => 'Clientes', 'icon' => '👥'],
            ['id' => 'corte', 'label' => 'Cortes de Canales', 'icon' => '🥩'],
            ['id' => 'zona', 'label' => 'Zonas', 'icon' => '📍'],
            ['id' => 'departamento', 'label' => 'Departamentos', 'icon' => '🗺️'],
            ['id' => 'municipio', 'label' => 'Municipios', 'icon' => '🏘️'],
            ['id' => 'producto', 'label' => 'Productos', 'icon' => '🏷️'],
            ['id' => 'tipo_de_cuarteo', 'label' => 'Tipo de Cuarteo', 'icon' => '🔪'],
            ['id' => 'opl', 'label' => 'OPL (Empresas)', 'icon' => '🏢'],
            ['id' => 'vehiculo', 'label' => 'Vehículos', 'icon' => '🚛'],
            ['id' => 'conductor', 'label' => 'Conductores', 'icon' => '🆔'],
        ],
    ],
    [
        'titulo' => 'Personal y turnos',
        'descripcion' => 'Empleados, descansos y manejo de personal. Orden sugerido: empleados → descansos → manejo de personal.',
        'items' => [
            ['id' => 'empleado', 'label' => 'Empleados', 'icon' => '👤'],
            ['id' => 'empleado_descanso', 'label' => 'Descansos y ausencias', 'icon' => '🛌'],
            ['id' => 'empleado_programacion', 'label' => 'Manejo de personal', 'icon' => '🕐'],
        ],
        'extra_links' => [
            ['href' => 'tablero_descansos.php', 'label' => 'Tablero descansos y turnos', 'icon' => '📋'],
        ],
    ],
];

if (lm_es_super_admin()) {
    $secciones_maestros[] = [
        'titulo' => 'Seguridad y acceso',
        'descripcion' => 'Administración de cuentas del sistema (crear, editar y eliminar usuarios).',
        'items' => [
            ['id' => 'user', 'label' => 'Usuarios', 'icon' => '🔐'],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Configuración</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; } </style>
</head>
<body class="flex min-h-screen">

    <?php mostrarSidebar('configuracion'); ?>

    <div class="flex-1 flex flex-col ml-64 min-h-screen">
        <main class="p-10 flex-grow">
            <header class="mb-10">
                <h2 class="text-3xl font-bold text-slate-800 tracking-tight italic">Panel de Configuración</h2>
                <p class="text-slate-500 font-medium text-sm">Gestión técnica de tablas maestras del ERP.</p>
            </header>

            <?php foreach ($secciones_maestros as $sec): ?>
            <section class="mb-14">
                <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight"><?= htmlspecialchars($sec['titulo']) ?></h3>
                        <p class="text-slate-500 text-sm mt-1"><?= htmlspecialchars($sec['descripcion']) ?></p>
                    </div>
                    <?php if (!empty($sec['extra_links'])): ?>
                    <div class="flex flex-wrap gap-2 justify-end">
                        <?php foreach ($sec['extra_links'] as $lnk): ?>
                        <a href="<?= htmlspecialchars($lnk['href']) ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-slate-900 text-white text-xs font-bold hover:bg-blue-600 transition-colors shadow-sm whitespace-nowrap">
                            <span><?= $lnk['icon'] ?? '' ?></span> <?= htmlspecialchars($lnk['label']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php elseif (!empty($sec['extra_link'])): ?>
                    <a href="<?= htmlspecialchars($sec['extra_link']['href']) ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-slate-900 text-white text-xs font-bold hover:bg-blue-600 transition-colors shadow-sm whitespace-nowrap">
                        <span><?= $sec['extra_link']['icon'] ?? '' ?></span> <?= htmlspecialchars($sec['extra_link']['label']) ?>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($sec['items'] as $tabla): ?>
                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all group cursor-pointer" 
                         onclick="window.location.href='gestion_tabla.php?tabla=<?= htmlspecialchars($tabla['id']) ?>'">
                        <div class="text-3xl mb-4"><?= $tabla['icon'] ?></div>
                        <h4 class="font-bold text-slate-800 group-hover:text-blue-600 transition-colors uppercase text-xs"><?= htmlspecialchars($tabla['label']) ?></h4>
                        <div class="mt-4 flex justify-end">
                            <span class="text-[10px] font-black text-slate-300 group-hover:text-blue-600">GESTIONAR →</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </main>
        <?php mostrarFooter(); ?>
    </div>
</body>
</html>
