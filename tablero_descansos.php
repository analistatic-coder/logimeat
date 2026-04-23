<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/personal_helpers.php';
require_once __DIR__ . '/config/programacion_catalogos.php';

$esAdmin = ($_SESSION['rol'] ?? '') === 'Administrador';
$etiquetasPlanta = programacion_plantas_opciones();

$anio = isset($_GET['anio']) ? max(2020, min(2035, (int) $_GET['anio'])) : 2026;
$semana = isset($_GET['semana']) ? max(1, min(53, (int) $_GET['semana'])) : 16;
$areaF = isset($_GET['area']) ? trim((string) $_GET['area']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$flash = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'desc_ok') {
        $flash = 'Descanso guardado correctamente.';
    } elseif ($_GET['msg'] === 'prog_ok') {
        $flash = 'Programación guardada correctamente.';
    }
}

$inicioSem = new DateTime();
$inicioSem->setISODate($anio, $semana);
$inicioSem->setTime(0, 0, 0);
$finSem = clone $inicioSem;
$finSem->modify('+6 days');
$finSem->setTime(23, 59, 59);

$labelRango = $inicioSem->format('d/m/Y') . ' — ' . $finSem->format('d/m/Y');

$paramsD = [];
$sqlD = "SELECT d.*, e.Nombre_Completo, e.Area, e.Cargo
         FROM empleado_descanso d
         LEFT JOIN empleado e ON d.ID_Empleado = e.ID_Empleado
         WHERE 1=1";
if ($areaF !== '') {
    $sqlD .= " AND (e.Area LIKE ? OR e.Area IS NULL)";
    $paramsD[] = '%' . $areaF . '%';
}
if ($q !== '') {
    $sqlD .= " AND (e.Nombre_Completo LIKE ? OR d.ID_Empleado LIKE ? OR d.Tipo LIKE ?)";
    $paramsD[] = '%' . $q . '%';
    $paramsD[] = '%' . $q . '%';
    $paramsD[] = '%' . $q . '%';
}
$sqlD .= " ORDER BY d.Fecha_Inicio ASC, e.Nombre_Completo ASC";

$st = $pdo->prepare($sqlD);
$st->execute($paramsD);
$descansosRaw = $st->fetchAll(PDO::FETCH_ASSOC);
$descansos = array_values(array_filter($descansosRaw, static fn (array $r): bool => lm_descanso_incluir_en_tablero($r, $anio, $semana)));

$paramsP = [$anio, $semana];
$sqlP = "SELECT p.*, e.Nombre_Completo, e.Area, e.Cargo
         FROM empleado_programacion p
         LEFT JOIN empleado e ON p.ID_Empleado = e.ID_Empleado
         WHERE p.Anio = ? AND p.Numero_Semana = ?";
if ($areaF !== '') {
    $sqlP .= " AND (e.Area LIKE ? OR e.Area IS NULL)";
    $paramsP[] = '%' . $areaF . '%';
}
if ($q !== '') {
    $sqlP .= " AND (e.Nombre_Completo LIKE ? OR p.ID_Empleado LIKE ? OR p.Dia_Semana LIKE ? OR p.Turno LIKE ? OR p.Actividad LIKE ? OR p.Planta LIKE ? OR p.Producto LIKE ?)";
    $paramsP[] = '%' . $q . '%';
    $paramsP[] = '%' . $q . '%';
    $paramsP[] = '%' . $q . '%';
    $paramsP[] = '%' . $q . '%';
    $paramsP[] = '%' . $q . '%';
    $paramsP[] = '%' . $q . '%';
    $paramsP[] = '%' . $q . '%';
}
$sqlP .= " ORDER BY p.Dia_Semana ASC, e.Nombre_Completo ASC";

$st2 = $pdo->prepare($sqlP);
$st2->execute($paramsP);
$programacion = $st2->fetchAll(PDO::FETCH_ASSOC);

$areas = $pdo->query("SELECT DISTINCT Area FROM empleado WHERE Area IS NOT NULL AND Area != '' ORDER BY Area")->fetchAll(PDO::FETCH_COLUMN);

$nd = count($descansos);
$np = count($programacion);

$qsRet = 'anio=' . (int) $anio . '&semana=' . (int) $semana . ($areaF !== '' ? '&area=' . urlencode($areaF) : '') . ($q !== '' ? '&q=' . urlencode($q) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Tablero descansos y programación</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex min-h-screen">
    <?php mostrarSidebar('tablero'); ?>

    <div class="flex-1 flex flex-col ml-64 min-h-screen">
        <main class="p-8 flex-grow">
            <header class="mb-8 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Tablero personal</h1>
                    <p class="text-slate-500 text-sm mt-1">Descansos y programación por semana ISO · <span class="font-bold text-slate-700"><?= htmlspecialchars($labelRango) ?></span></p>
                </div>
                <div class="flex flex-wrap gap-3 items-center">
                    <?php if ($esAdmin): ?>
                        <a href="personal_descanso_form.php?<?= htmlspecialchars($qsRet) ?>" class="bg-amber-500 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-600">+ Descanso</a>
                        <a href="personal_programacion_form.php?<?= htmlspecialchars($qsRet) ?>" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-700">+ Programación</a>
                    <?php endif; ?>
                    <a href="maestros.php" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline">Configuración →</a>
                </div>
            </header>

            <?php if ($flash !== ''): ?>
                <p class="mb-4 text-sm font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3"><?= htmlspecialchars($flash) ?></p>
            <?php endif; ?>

            <form method="get" class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6 mb-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Año</label>
                    <input type="number" name="anio" value="<?= (int) $anio ?>" min="2020" max="2035" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Nº semana (ISO)</label>
                    <input type="number" name="semana" value="<?= (int) $semana ?>" min="1" max="53" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Área</label>
                    <select name="area" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                        <option value="">Todas</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= $areaF === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2 lg:col-span-2">
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Buscar (nombre, código, tipo…)</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ej. EMP-0001 o nombre" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
                <div class="md:col-span-2 lg:col-span-1">
                    <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-blue-600 transition-colors">Filtrar</button>
                </div>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Semana</p>
                    <p class="text-2xl font-black text-slate-800"><?= (int) $semana ?> / <?= (int) $anio ?></p>
                </div>
                <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm border-l-4 border-l-amber-400">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Registros descanso</p>
                    <p class="text-2xl font-black text-amber-700"><?= $nd ?></p>
                </div>
                <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm border-l-4 border-l-blue-500">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Programación</p>
                    <p class="text-2xl font-black text-blue-700"><?= $np ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                <section class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-tight">Descansos y ausencias</h2>
                    </div>
                    <div class="overflow-x-auto max-h-[480px] overflow-y-auto">
                        <table class="w-full text-left text-[10px]">
                            <thead class="text-slate-400 uppercase font-black border-b border-slate-100 sticky top-0 bg-white">
                                <tr>
                                    <th class="p-3">Empleado</th>
                                    <th class="p-3">Tipo</th>
                                    <th class="p-3">Desde</th>
                                    <th class="p-3">Hasta</th>
                                    <th class="p-3">Área</th>
                                    <?php if ($esAdmin): ?><th class="p-3 w-24"></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (!$descansos): ?>
                                    <tr><td colspan="<?= $esAdmin ? 6 : 5 ?>" class="p-6 text-center text-slate-400 font-bold">Sin registros para este filtro.</td></tr>
                                <?php else: foreach ($descansos as $r): ?>
                                <tr class="hover:bg-slate-50/80">
                                    <td class="p-3 font-bold text-slate-800"><?= htmlspecialchars($r['Nombre_Completo'] ?? $r['ID_Empleado']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($r['Tipo'] ?? '') ?></td>
                                    <td class="p-3"><?= htmlspecialchars((string) ($r['Fecha_Inicio'] ?? '')) ?></td>
                                    <td class="p-3"><?= htmlspecialchars((string) ($r['Fecha_Fin'] ?? '')) ?></td>
                                    <td class="p-3 text-slate-500"><?= htmlspecialchars($r['Area'] ?? '') ?></td>
                                    <?php if ($esAdmin): ?>
                                    <td class="p-3">
                                        <a href="personal_descanso_form.php?id=<?= (int) ($r['id_interno'] ?? 0) ?>&<?= htmlspecialchars($qsRet) ?>" class="text-blue-600 font-black hover:underline">Editar</a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-100">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-tight">Programación de personal</h2>
                    </div>
                    <div class="overflow-x-auto max-h-[480px] overflow-y-auto">
                        <table class="w-full text-left text-[10px]">
                            <thead class="text-slate-400 uppercase font-black border-b border-slate-100 sticky top-0 bg-white">
                                <tr>
                                    <th class="p-3">Empleado</th>
                                    <th class="p-3">Día</th>
                                    <th class="p-3">Actividad</th>
                                    <th class="p-3">Planta</th>
                                    <th class="p-3">Producto</th>
                                    <th class="p-3">Entrada</th>
                                    <th class="p-3">Salida</th>
                                    <th class="p-3">Turno</th>
                                    <?php if ($esAdmin): ?><th class="p-3 w-24"></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (!$programacion): ?>
                                    <tr><td colspan="<?= $esAdmin ? 9 : 8 ?>" class="p-6 text-center text-slate-400 font-bold">Sin registros para este filtro.</td></tr>
                                <?php else: foreach ($programacion as $r): ?>
                                <tr class="hover:bg-blue-50/50">
                                    <td class="p-3 font-bold text-slate-800"><?= htmlspecialchars($r['Nombre_Completo'] ?? $r['ID_Empleado']) ?></td>
                                    <td class="p-3 text-blue-700 font-black"><?= htmlspecialchars((string) ($r['Dia_Semana'] ?? '')) ?></td>
                                    <td class="p-3 text-slate-700"><?= htmlspecialchars((string) ($r['Actividad'] ?? '')) ?></td>
                                    <td class="p-3"><?php $pk = (string) ($r['Planta'] ?? ''); echo htmlspecialchars($etiquetasPlanta[$pk] ?? $pk); ?></td>
                                    <td class="p-3"><?= htmlspecialchars((string) ($r['Producto'] ?? '')) ?></td>
                                    <td class="p-3"><?= htmlspecialchars((string) ($r['Hora_Entrada'] ?? '')) ?></td>
                                    <td class="p-3"><?= htmlspecialchars((string) ($r['Hora_Salida'] ?? '')) ?></td>
                                    <td class="p-3"><?= htmlspecialchars((string) ($r['Turno'] ?? '')) ?></td>
                                    <?php if ($esAdmin): ?>
                                    <td class="p-3">
                                        <a href="personal_programacion_form.php?id=<?= (int) ($r['id_interno'] ?? 0) ?>&<?= htmlspecialchars($qsRet) ?>" class="text-blue-600 font-black hover:underline">Editar</a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
        <?php mostrarFooter(); ?>
    </div>
</body>
</html>
