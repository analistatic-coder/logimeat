<?php
/**
 * Importación CSV desde: …\logimeat_datos\manejo de empleados
 * Archivos: 01_empleados.csv, 02_descansos.csv, 03_programacion.csv
 */
require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/import_empleados_csv.php';

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    header('Location: index.php');
    exit();
}

$baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logimeat_datos' . DIRECTORY_SEPARATOR . 'manejo de empleados';
$rutaPersonalUtf8 = ruta_logimeat_personal_utf8_csv();
$candidatosUtf8 = rutas_logimeat_personal_utf8_csv();
$mensajes = [];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'importar_utf8') {
    $rUtf = importarEmpleadosDesdeLogimeatPersonalUtf8($pdo);
    $mensajes = $rUtf['mensajes'];
    $errores = $rUtf['errores'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'importar') {
    $rUtf = importarEmpleadosDesdeLogimeatPersonalUtf8($pdo);
    $mensajes = $rUtf['mensajes'];
    $errores = $rUtf['errores'];
    $r = importarEmpleadosDesdeCarpeta($pdo, $baseDir);
    $mensajes = array_merge($mensajes, $r['mensajes']);
    $errores = array_merge($errores, $r['errores']);
    $r2 = importarDescansosDashboardDesdeCarpeta($pdo, $baseDir);
    $mensajes = array_merge($mensajes, $r2['mensajes']);
    $errores = array_merge($errores, $r2['errores']);
    $r3 = importarArchivosProgSemanaDesdeCarpeta($pdo, $baseDir, 2026);
    $mensajes = array_merge($mensajes, $r3['mensajes']);
    $errores = array_merge($errores, $r3['errores']);
}

$archivosEsperados = ['01_empleados.csv', '02_descansos.csv', '03_programacion.csv', 'Descansos/descansos.csv', 'Descansos/programacion_semana.csv', 'programacion/Prog_N.csv (ej. Prog_15.csv)'];
$estadoArchivos = [];
foreach ($archivosEsperados as $a) {
    $p = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $a);
    $estadoArchivos[$a] = is_readable($p);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Personal — importar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex min-h-screen">
    <?php mostrarSidebar('configuracion'); ?>
    <div class="flex-1 flex flex-col ml-64 min-h-screen p-10">
        <header class="mb-8">
            <a href="maestros.php" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline">← Volver a configuración</a>
            <h1 class="text-3xl font-bold text-slate-800 mt-2">Personal (empleados)</h1>
            <p class="text-slate-500 text-sm mt-1">Los empleados se <strong>agregan</strong> con <code class="text-xs bg-slate-100 px-1 rounded">INSERT IGNORE</code> (cédula = <code class="text-xs bg-slate-100 px-1 rounded">ID_Empleado</code>); no se borran ni cambian IDs existentes, para no afectar descansos ni programación que usen ese mismo ID.</p>
            <p class="text-slate-500 text-sm mt-1">El archivo <code class="text-xs bg-slate-100 px-1 rounded">logimeat_personal_UTF8.csv</code> puede estar en la raíz del proyecto, en la carpeta padre o en <code class="text-xs bg-slate-100 px-1 rounded">logimeat_datos/…/manejo de empleados</code>. En la tabla, <strong>ID empleado</strong> y <strong>número documento</strong> quedan con la cédula (los registros <code class="text-xs">EMP-…</code> de demo no se borran).</p>
        </header>

        <?php if ($mensajes !== [] || $errores !== []): ?>
        <div class="mb-6 max-w-3xl space-y-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <?php foreach ($mensajes as $m): ?>
                <p class="text-emerald-700 text-sm font-bold"><?= htmlspecialchars($m) ?></p>
            <?php endforeach; ?>
            <?php foreach ($errores as $e): ?>
                <p class="text-red-600 text-sm font-bold"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-8 max-w-3xl space-y-6">
            <div class="text-sm text-slate-600">
                <p class="font-bold text-slate-800 mb-2">Carpeta</p>
                <code class="block bg-slate-50 p-3 rounded-xl text-xs break-all"><?= htmlspecialchars($baseDir) ?></code>
                <p class="mt-4 text-xs text-slate-500">Exporte cada hoja de Excel como CSV UTF-8 con estos nombres:</p>
                <ul class="list-disc ml-5 mt-2 text-xs space-y-1">
                    <li><strong>01_empleados.csv</strong> — mínimo: ID_Empleado, Nombre_Completo</li>
                    <li><strong>02_descansos.csv</strong> — ID_Empleado, Fecha_Inicio, Fecha_Fin</li>
                    <li><strong>03_programacion.csv</strong> — ID_Empleado, Dia_Semana, Hora_Entrada, Hora_Salida</li>
                    <li><strong>programacion/Prog_15.csv</strong> (opcional) — mismo formato; el número del archivo fija la semana ISO (15 → semana 15). Año por columna Anio o 2026 por defecto.</li>
                </ul>
            </div>

            <div class="grid grid-cols-1 gap-2">
                <?php foreach ($candidatosUtf8 as $cand): ?>
                <div class="flex items-center justify-between p-3 rounded-xl <?= is_readable($cand) ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-50 text-slate-500' ?> text-xs font-bold">
                    <span class="break-all pr-2"><?= htmlspecialchars($cand) ?></span>
                    <span><?= is_readable($cand) ? 'Encontrado' : '—' ?></span>
                </div>
                <?php endforeach; ?>
                <?php if ($rutaPersonalUtf8 !== null): ?>
                <p class="text-xs text-emerald-800 font-bold">Se usará para importar: <?= htmlspecialchars($rutaPersonalUtf8) ?></p>
                <?php else: ?>
                <p class="text-xs text-amber-800 font-bold">Ninguna ruta tiene el archivo; cópielo a una de las anteriores.</p>
                <?php endif; ?>
                <?php foreach ($estadoArchivos as $nom => $ok): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl <?= $ok ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' ?> text-xs font-bold">
                        <span><?= htmlspecialchars($nom) ?></span>
                        <span><?= $ok ? 'Encontrado' : 'Falta' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="POST" class="flex flex-wrap gap-3 items-center">
                <button type="submit" name="action" value="importar_utf8" class="bg-emerald-600 text-white px-8 py-4 rounded-2xl font-bold text-sm hover:bg-emerald-700 shadow-lg">
                    Importar solo logimeat_personal_UTF8.csv
                </button>
                <button type="submit" name="action" value="importar" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-bold text-sm hover:bg-blue-700 shadow-lg">
                    Importación completa (UTF-8 primero, luego CSV)
                </button>
            </form>
        </div>
        <?php mostrarFooter(); ?>
    </div>
</body>
</html>
