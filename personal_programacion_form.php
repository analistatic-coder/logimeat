<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/personal_helpers.php';
require_once __DIR__ . '/config/programacion_catalogos.php';

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_actividad') {
    $nombre = trim((string) ($_POST['nueva_actividad'] ?? ''));
    if ($nombre !== '') {
        try {
            $pdo->prepare('INSERT IGNORE INTO programacion_actividad_extra (Nombre) VALUES (?)')->execute([$nombre]);
        } catch (Throwable) {
        }
    }
    $redAnio = max(2020, min(2035, (int) ($_POST['red_anio'] ?? 2026)));
    $redSem = max(1, min(53, (int) ($_POST['red_semana'] ?? 16)));
    $redId = (int) ($_POST['red_id'] ?? 0);
    $q = http_build_query(array_filter(['id' => $redId > 0 ? $redId : null, 'anio' => $redAnio, 'semana' => $redSem]));
    header('Location: personal_programacion_form.php?' . $q);
    exit();
}

$idInterno = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$retAnio = isset($_GET['anio']) ? max(2020, min(2035, (int) $_GET['anio'])) : 2026;
$retSem = isset($_GET['semana']) ? max(1, min(53, (int) $_GET['semana'])) : 16;

$error = '';

$plantas = programacion_plantas_opciones();
$row = [
    'ID_Empleado' => '',
    'Dia_Semana' => 'LUNES',
    'Hora_Entrada' => '',
    'Hora_Salida' => '',
    'Turno' => '',
    'Actividad' => '',
    'Planta' => 'BENEFICIO',
    'Producto' => '',
    'Observaciones' => '',
    'ID_Programacion' => '',
];

if ($idInterno > 0) {
    $st = $pdo->prepare('SELECT * FROM empleado_programacion WHERE id_interno = ?');
    $st->execute([$idInterno]);
    $ex = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ex) {
        header('Location: tablero_descansos.php?anio=' . $retAnio . '&semana=' . $retSem);
        exit();
    }
    $row = array_merge($row, $ex);
    $retAnio = (int) ($ex['Anio'] ?? $retAnio);
    $retSem = (int) ($ex['Numero_Semana'] ?? $retSem);
}

$plantaKey = (string) ($row['Planta'] ?? 'BENEFICIO');
if (!isset($plantas[$plantaKey])) {
    $plantaKey = 'BENEFICIO';
    $row['Planta'] = $plantaKey;
}

$productosPlanta = programacion_productos_por_planta()[$plantaKey] ?? [];
$prodVal = (string) ($row['Producto'] ?? '');
if ($prodVal === '' || !programacion_es_producto_valido($plantaKey, $prodVal)) {
    $prodVal = $productosPlanta[0] ?? '';
    $row['Producto'] = $prodVal;
}

$diasOpcion = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO', 'LUNES A VIERNES'];
$actividadesLista = programacion_listar_actividades($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'eliminar' && $idInterno > 0) {
        $pdo->prepare('DELETE FROM empleado_programacion WHERE id_interno = ?')->execute([$idInterno]);
        header('Location: tablero_descansos.php?anio=' . $retAnio . '&semana=' . $retSem . '&msg=prog_ok');
        exit();
    }

    if ($action === 'refrescar') {
        $row['ID_Empleado'] = trim((string) ($_POST['ID_Empleado'] ?? ''));
        $row['Dia_Semana'] = trim((string) ($_POST['Dia_Semana'] ?? 'LUNES'));
        $row['Hora_Entrada'] = trim((string) ($_POST['Hora_Entrada'] ?? ''));
        $row['Hora_Salida'] = trim((string) ($_POST['Hora_Salida'] ?? ''));
        $row['Turno'] = trim((string) ($_POST['Turno'] ?? ''));
        $row['Actividad'] = trim((string) ($_POST['Actividad'] ?? ''));
        $row['Planta'] = trim((string) ($_POST['Planta'] ?? 'BENEFICIO'));
        $row['Producto'] = trim((string) ($_POST['Producto'] ?? ''));
        $row['Observaciones'] = trim((string) ($_POST['Observaciones'] ?? ''));
        $retAnio = max(2020, min(2035, (int) ($_POST['Anio'] ?? $retAnio)));
        $retSem = max(1, min(53, (int) ($_POST['Numero_Semana'] ?? $retSem)));
        $plantaKey = (string) $row['Planta'];
        if (!isset($plantas[$plantaKey])) {
            $plantaKey = 'BENEFICIO';
            $row['Planta'] = $plantaKey;
        }
        if (!programacion_es_producto_valido($plantaKey, (string) $row['Producto'])) {
            $row['Producto'] = (programacion_productos_por_planta()[$plantaKey] ?? [])[0] ?? '';
        }
    } elseif ($action === 'guardar') {
        $row['ID_Empleado'] = trim((string) ($_POST['ID_Empleado'] ?? ''));
        $row['Dia_Semana'] = trim((string) ($_POST['Dia_Semana'] ?? ''));
        $row['Hora_Entrada'] = trim((string) ($_POST['Hora_Entrada'] ?? ''));
        $row['Hora_Salida'] = trim((string) ($_POST['Hora_Salida'] ?? ''));
        $row['Turno'] = trim((string) ($_POST['Turno'] ?? ''));
        $row['Actividad'] = trim((string) ($_POST['Actividad'] ?? ''));
        $row['Planta'] = trim((string) ($_POST['Planta'] ?? ''));
        $row['Producto'] = trim((string) ($_POST['Producto'] ?? ''));
        $row['Observaciones'] = trim((string) ($_POST['Observaciones'] ?? ''));
        $py = max(2020, min(2035, (int) ($_POST['Anio'] ?? $retAnio)));
        $pw = max(1, min(53, (int) ($_POST['Numero_Semana'] ?? $retSem)));

        $plantaKey = (string) $row['Planta'];
        if ($row['Actividad'] === '') {
            $error = 'Seleccione o indique una actividad.';
        } elseif (!isset($plantas[$plantaKey])) {
            $error = 'Planta no válida.';
        } elseif (!programacion_es_producto_valido($plantaKey, (string) $row['Producto'])) {
            $error = 'El producto no corresponde a la planta elegida.';
        } elseif ($row['ID_Empleado'] === '') {
            $error = 'Seleccione un empleado.';
        } else {
            $errVal = lm_validar_programacion_libre($pdo, $row['ID_Empleado'], $row['Dia_Semana'], $py, $pw, null, $idInterno > 0 ? $idInterno : null);
            if ($errVal !== null) {
                $error = $errVal;
                $retAnio = $py;
                $retSem = $pw;
            } else {
                $idP = trim((string) ($row['ID_Programacion'] ?? ''));
                if ($idP === '') {
                    $idP = substr(bin2hex(random_bytes(4)), 0, 8);
                }

                if ($idInterno > 0) {
                    $pdo->prepare('UPDATE empleado_programacion SET ID_Empleado=?, Dia_Semana=?, Hora_Entrada=?, Hora_Salida=?, Turno=?, Actividad=?, Planta=?, Producto=?, Observaciones=?, Anio=?, Numero_Semana=? WHERE id_interno=?')
                        ->execute([$row['ID_Empleado'], $row['Dia_Semana'], $row['Hora_Entrada'] ?: null, $row['Hora_Salida'] ?: null, $row['Turno'] ?: null, $row['Actividad'], $plantaKey, $row['Producto'], $row['Observaciones'] ?: null, $py, $pw, $idInterno]);
                } else {
                    $pdo->prepare('INSERT INTO empleado_programacion (ID_Programacion,ID_Empleado,Dia_Semana,Hora_Entrada,Hora_Salida,Turno,Actividad,Planta,Producto,Observaciones,Anio,Numero_Semana) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$idP, $row['ID_Empleado'], $row['Dia_Semana'], $row['Hora_Entrada'] ?: null, $row['Hora_Salida'] ?: null, $row['Turno'] ?: null, $row['Actividad'], $plantaKey, $row['Producto'], $row['Observaciones'] ?: null, $py, $pw]);
                }
                header('Location: tablero_descansos.php?anio=' . $py . '&semana=' . $pw . '&msg=prog_ok');
                exit();
            }
        }
    }
}

$plantaKey = (string) ($row['Planta'] ?? 'BENEFICIO');
if (!isset($plantas[$plantaKey])) {
    $plantaKey = 'BENEFICIO';
}
$productosPlanta = programacion_productos_por_planta()[$plantaKey] ?? [];

$disponibles = lm_empleados_disponibles_programacion(
    $pdo,
    $retAnio,
    $retSem,
    $row['Dia_Semana'] ?: 'LUNES',
    $idInterno > 0 ? $idInterno : null,
    $row['ID_Empleado'] !== '' ? $row['ID_Empleado'] : null
);

$productosJson = json_encode(programacion_productos_por_planta(), JSON_UNESCAPED_UNICODE);
$prodSel = (string) ($row['Producto'] ?? '');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Programación personal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex min-h-screen">
    <?php mostrarSidebar('tablero'); ?>
    <div class="flex-1 flex flex-col ml-64 min-h-screen p-8">
        <header class="mb-8">
            <a href="tablero_descansos.php?anio=<?= (int) $retAnio ?>&semana=<?= (int) $retSem ?>" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline">← Tablero personal</a>
            <h1 class="text-2xl font-black text-slate-800 mt-2"><?= $idInterno ? 'Editar programación' : 'Nueva programación' ?></h1>
            <p class="text-slate-500 text-sm mt-1">Semana ISO <?= (int) $retSem ?> de <?= (int) $retAnio ?>. El producto depende de la planta seleccionada.</p>
        </header>

        <?php if ($error): ?>
            <p class="mb-4 text-sm font-bold text-red-600"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-8 max-w-xl space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Año (ISO)</label>
                    <input type="number" name="Anio" value="<?= (int) $retAnio ?>" min="2020" max="2035" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Nº semana</label>
                    <input type="number" name="Numero_Semana" value="<?= (int) $retSem ?>" min="1" max="53" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
            </div>
            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Día</label>
                <select name="Dia_Semana" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                    <?php
                    $diaSel = lm_normalizar_dia_semana((string) $row['Dia_Semana']);
                    foreach ($diasOpcion as $d):
                        ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= ($diaSel === lm_normalizar_dia_semana($d)) ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Actividad</label>
                <select name="Actividad" required class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                    <option value="">— Elegir —</option>
                    <?php foreach ($actividadesLista as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= ((string) $row['Actividad'] === $a) ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Planta</label>
                <select name="Planta" id="planta_select" required class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                    <?php foreach ($plantas as $k => $label): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= ($plantaKey === $k) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Producto</label>
                <select name="Producto" id="producto_select" required class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                    <?php foreach ($productosPlanta as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= ($prodSel === $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Empleado</label>
                <select name="ID_Empleado" required class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                    <option value="">— Elegir —</option>
                    <?php foreach ($disponibles as $e): ?>
                        <option value="<?= htmlspecialchars($e['ID_Empleado']) ?>" <?= ((string) $row['ID_Empleado'] === (string) $e['ID_Empleado']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['Nombre_Completo'] . ' (' . $e['ID_Empleado'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Entrada</label>
                    <input type="text" name="Hora_Entrada" value="<?= htmlspecialchars((string) $row['Hora_Entrada']) ?>" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold" placeholder="06:00">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Salida</label>
                    <input type="text" name="Hora_Salida" value="<?= htmlspecialchars((string) $row['Hora_Salida']) ?>" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold" placeholder="14:00">
                </div>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Turno</label>
                <input type="text" name="Turno" value="<?= htmlspecialchars((string) $row['Turno']) ?>" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Observaciones</label>
                <textarea name="Observaciones" rows="2" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold"><?= htmlspecialchars((string) $row['Observaciones']) ?></textarea>
            </div>

            <div class="flex flex-wrap gap-3 pt-4">
                <button type="submit" name="action" value="guardar" class="bg-slate-900 text-white px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-blue-600">Guardar</button>
                <button type="submit" name="action" value="refrescar" class="bg-slate-100 text-slate-700 px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-200">Actualizar lista</button>
                <?php if ($idInterno > 0): ?>
                    <button type="submit" name="action" value="eliminar" class="bg-red-50 text-red-600 px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest border border-red-100" onclick="return confirm('¿Eliminar este registro?');">Eliminar</button>
                <?php endif; ?>
            </div>
        </form>

        <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-8 max-w-xl mt-6">
            <p class="text-[10px] font-black text-slate-400 uppercase mb-2">Actividades adicionales</p>
            <p class="text-xs text-slate-500 mb-4">Registre un nombre nuevo; quedará disponible en la lista «Actividad».</p>
            <form method="post" class="flex flex-col sm:flex-row gap-3 items-end">
                <input type="hidden" name="action" value="add_actividad">
                <input type="hidden" name="red_anio" value="<?= (int) $retAnio ?>">
                <input type="hidden" name="red_semana" value="<?= (int) $retSem ?>">
                <input type="hidden" name="red_id" value="<?= (int) $idInterno ?>">
                <div class="flex-1 w-full">
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Nombre</label>
                    <input type="text" name="nueva_actividad" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold" placeholder="Ej. Recepción frío">
                </div>
                <button type="submit" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-900 whitespace-nowrap">Registrar actividad</button>
            </form>
        </div>

        <script>
        (function () {
            var map = <?= $productosJson ?>;
            var planta = document.getElementById('planta_select');
            var prod = document.getElementById('producto_select');
            var cur = <?= json_encode($prodSel, JSON_UNESCAPED_UNICODE) ?>;
            function refill() {
                var k = planta.value;
                var list = map[k] || [];
                prod.innerHTML = '';
                var keep = false;
                list.forEach(function (p) {
                    var o = document.createElement('option');
                    o.value = p;
                    o.textContent = p;
                    if (p === cur) { o.selected = true; keep = true; }
                    prod.appendChild(o);
                });
                if (!keep && list.length) { prod.selectedIndex = 0; }
            }
            planta.addEventListener('change', function () { cur = ''; refill(); });
            refill();
        })();
        </script>
        <?php mostrarFooter(); ?>
    </div>
</body>
</html>
