<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/programacion_catalogos.php';

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    header('Location: index.php');
    exit();
}

$error = '';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: programacion.php');
    exit();
}

$stmt = $pdo->prepare('SELECT * FROM Programacion WHERE id_interno = ?');
$stmt->execute([$id]);
$prog = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prog) {
    header('Location: programacion.php');
    exit();
}

$clientes = $pdo->query('SELECT ID_Cliente, Cliente FROM Clientes ORDER BY Cliente ASC')->fetchAll(PDO::FETCH_ASSOC);
$actividades = $pdo->query('SELECT ID_Actividad, Actividad FROM Actividad ORDER BY Actividad ASC')->fetchAll(PDO::FETCH_ASSOC);

$plantas = programacion_plantas_opciones();
$productosJson = json_encode(programacion_productos_por_planta(), JSON_UNESCAPED_UNICODE);
$cuarteoJson = json_encode(programacion_tipos_cuarteo_por_planta(), JSON_UNESCAPED_UNICODE);

$grupoRes = programacion_grupo_desde_fila($prog);
$plantaKey = $grupoRes !== '_SIN' ? $grupoRes : 'BENEFICIO';
if (!isset($plantas[$plantaKey])) {
    $plantaKey = 'BENEFICIO';
}

$prodActual = '';
if (programacion_es_producto_valido($plantaKey, (string) ($prog['Producto'] ?? ''))) {
    $prodActual = (string) $prog['Producto'];
} else {
    $pp = programacion_productos_por_planta()[$plantaKey] ?? [];
    $prodActual = $pp[0] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plantaOp = trim((string) ($_POST['planta_operativa'] ?? ''));
    $producto = trim((string) ($_POST['producto'] ?? ''));
    $actividad = trim((string) ($_POST['actividad'] ?? ''));
    $cliente = trim((string) ($_POST['cliente'] ?? ''));
    $opl = trim((string) ($_POST['opl'] ?? ''));
    $destino = trim((string) ($_POST['destino'] ?? ''));
    $cantidad = $_POST['cantidad'] ?? '';
    $fechaOp = trim((string) ($_POST['fecha_operacion'] ?? ''));
    $hora = trim((string) ($_POST['hora'] ?? ''));
    $tipoCuarteo = trim((string) ($_POST['tipo_cuarteo'] ?? ''));
    $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
    $estado = trim((string) ($_POST['estado'] ?? 'PROGRAMADO'));
    $vehiculo = trim((string) ($_POST['vehiculo'] ?? ''));
    $conductor = trim((string) ($_POST['conductor'] ?? ''));

    if (!programacion_es_producto_valido($plantaOp, $producto)) {
        $error = 'El producto no corresponde a la planta.';
    } else {
        $tcList = programacion_tipos_cuarteo_por_planta()[$plantaOp] ?? [];
        if ($tcList === []) {
            $tipoCuarteo = '';
        }

        $fechaFmt = $fechaOp !== '' ? date('d/m/Y', strtotime($fechaOp)) : $prog['Fecha_de_Operacion'];

        $idPlantaMaestro = programacion_id_maestro_desde_grupo($plantaOp);
        if ($idPlantaMaestro === null) {
            $error = 'Planta operativa no válida.';
        } else {
        $sql = 'UPDATE Programacion SET
            Cliente = ?, Planta = ?, Planta_Operativa = ?, Actividad = ?, Fecha_de_Operacion = ?, Hora = ?,
            Producto = ?, Tipo_de_Cuarteo = ?, Cantidad = ?, Destino = ?, OPL = ?, Observaciones = ?,
            Estado_Actividad = ?, Vehiculo = ?, Conductor = ?
            WHERE id_interno = ?';

        $pdo->prepare($sql)->execute([
            $cliente,
            $idPlantaMaestro,
            $plantaOp,
            $actividad,
            $fechaFmt,
            $hora !== '' ? $hora : null,
            $producto,
            $tipoCuarteo !== '' ? $tipoCuarteo : null,
            $cantidad !== '' ? $cantidad : null,
            $destino !== '' ? $destino : null,
            $opl !== '' ? $opl : null,
            $observaciones !== '' ? $observaciones : null,
            $estado,
            $vehiculo !== '' ? $vehiculo : null,
            $conductor !== '' ? $conductor : null,
            $id,
        ]);

        header('Location: programacion.php?msj=editado');
        exit();
        }
    }
}

$conductores = $pdo->query('SELECT Conductor FROM Conductor ORDER BY Conductor ASC')->fetchAll(PDO::FETCH_COLUMN);
$vehiculos = $pdo->query('SELECT Vehiculo FROM Vehiculo ORDER BY Vehiculo ASC')->fetchAll(PDO::FETCH_COLUMN);

$fechaInput = '';
if (!empty($prog['Fecha_de_Operacion'])) {
    $raw = trim((string) $prog['Fecha_de_Operacion']);
    $d = DateTime::createFromFormat('d/m/Y', $raw);
    if ($d instanceof DateTime) {
        $fechaInput = $d->format('Y-m-d');
    } else {
        $ts = strtotime($raw);
        if ($ts !== false) {
            $fechaInput = date('Y-m-d', $ts);
        }
    }
}

$horaInput = '';
if (!empty($prog['Hora'])) {
    $h = trim((string) $prog['Hora']);
    if (preg_match('/(\d{1,2}):(\d{2})/', $h, $hm)) {
        $horaInput = sprintf('%02d:%02d', (int) $hm[1], (int) $hm[2]);
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Editar programación</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex min-h-screen text-slate-800">
    <?php mostrarSidebar('prog'); ?>

    <main class="flex-1 ml-64 p-8 min-h-screen bg-[#f8fafc]">
        <header class="mb-8">
            <a href="programacion.php" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline">← Programación</a>
            <h2 class="text-2xl font-black text-slate-800 mt-2">Editar programación #<?= (int) $id ?></h2>
        </header>

        <?php if ($error): ?>
            <p class="mb-4 text-red-600 font-bold text-sm"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="max-w-4xl bg-white border border-slate-100 rounded-[2rem] p-8 grid grid-cols-1 md:grid-cols-2 gap-6 shadow-sm">
            <div class="md:col-span-2">
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Planta operativa *</label>
                <select name="planta_operativa" id="planta_operativa" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                    <?php foreach ($plantas as $k => $lab): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= $plantaKey === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Producto *</label>
                <select name="producto" id="producto_select" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800"></select>
            </div>

            <div id="wrap_cuarteo">
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Tipo de cuarteo</label>
                <select name="tipo_cuarteo" id="tipo_cuarteo" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800"></select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Actividad *</label>
                <select name="actividad" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                    <?php foreach ($actividades as $a): ?>
                        <option value="<?= htmlspecialchars((string) $a['ID_Actividad']) ?>" <?= ((string) $prog['Actividad'] === (string) $a['ID_Actividad']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['Actividad']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Cliente *</label>
                <select name="cliente" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= htmlspecialchars((string) $c['ID_Cliente']) ?>" <?= ((string) $prog['Cliente'] === (string) $c['ID_Cliente']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['Cliente']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Vínculo (OPL)</label>
                <input type="text" name="opl" value="<?= htmlspecialchars((string) ($prog['OPL'] ?? '')) ?>" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Destino</label>
                <input type="text" name="destino" value="<?= htmlspecialchars((string) ($prog['Destino'] ?? '')) ?>" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Cantidad</label>
                <input type="number" name="cantidad" step="0.01" value="<?= htmlspecialchars((string) ($prog['Cantidad'] ?? '')) ?>" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Fecha operación</label>
                <input type="date" name="fecha_operacion" value="<?= htmlspecialchars($fechaInput) ?>" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Hora</label>
                <input type="time" name="hora" value="<?= htmlspecialchars($horaInput) ?>" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Conductor</label>
                <select name="conductor" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                    <option value="">—</option>
                    <?php foreach ($conductores as $co): ?>
                        <option value="<?= htmlspecialchars((string) $co) ?>" <?= ((string) ($prog['Conductor'] ?? '') === (string) $co) ? 'selected' : '' ?>><?= htmlspecialchars((string) $co) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Vehículo</label>
                <select name="vehiculo" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                    <option value="">—</option>
                    <?php foreach ($vehiculos as $v): ?>
                        <option value="<?= htmlspecialchars((string) $v) ?>" <?= ((string) ($prog['Vehiculo'] ?? '') === (string) $v) ? 'selected' : '' ?>><?= htmlspecialchars((string) $v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Estado actividad</label>
                <select name="estado" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                    <option value="PROGRAMADO" <?= (($prog['Estado_Actividad'] ?? '') === 'PROGRAMADO') ? 'selected' : '' ?>>PROGRAMADO</option>
                    <option value="EJECUTADO" <?= (($prog['Estado_Actividad'] ?? '') === 'EJECUTADO') ? 'selected' : '' ?>>EJECUTADO</option>
                    <option value="CANCELADO" <?= (($prog['Estado_Actividad'] ?? '') === 'CANCELADO') ? 'selected' : '' ?>>CANCELADO</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Observaciones</label>
                <textarea name="observaciones" rows="3" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800"><?= htmlspecialchars((string) ($prog['Observaciones'] ?? '')) ?></textarea>
            </div>

            <div class="md:col-span-2 flex gap-4">
                <button type="submit" class="flex-1 bg-emerald-600 text-white py-4 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-emerald-500">Guardar cambios</button>
                <a href="programacion.php" class="flex-1 bg-slate-100 text-center text-slate-700 py-4 rounded-2xl font-black text-sm uppercase tracking-widest border border-slate-200 hover:bg-slate-200">Cancelar</a>
            </div>
        </form>

        <script>
        (function () {
            var prodMap = <?= $productosJson ?>;
            var cuMap = <?= $cuarteoJson ?>;
            var planta = document.getElementById('planta_operativa');
            var prod = document.getElementById('producto_select');
            var tc = document.getElementById('tipo_cuarteo');
            var wrapC = document.getElementById('wrap_cuarteo');
            var curProd = <?= json_encode($prodActual, JSON_UNESCAPED_UNICODE) ?>;
            var curTc = <?= json_encode((string) ($prog['Tipo_de_Cuarteo'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
            function refillProd() {
                var k = planta.value;
                var list = prodMap[k] || [];
                prod.innerHTML = '';
                var ok = false;
                list.forEach(function (p) {
                    var o = document.createElement('option');
                    o.value = p;
                    o.textContent = p;
                    if (p === curProd) { o.selected = true; ok = true; }
                    prod.appendChild(o);
                });
                if (!ok && list.length) { prod.selectedIndex = 0; }
            }
            function refillCuarteo() {
                var k = planta.value;
                var list = cuMap[k] || [];
                tc.innerHTML = '<option value="">—</option>';
                list.forEach(function (x) {
                    var o = document.createElement('option');
                    o.value = x;
                    o.textContent = x;
                    if (x === curTc) { o.selected = true; }
                    tc.appendChild(o);
                });
                wrapC.style.display = list.length ? 'block' : 'none';
            }
            planta.addEventListener('change', function () { curProd = ''; curTc = ''; refillProd(); refillCuarteo(); });
            refillProd();
            refillCuarteo();
        })();
        </script>
        <?php mostrarFooter(); ?>
    </main>
</body>
</html>
