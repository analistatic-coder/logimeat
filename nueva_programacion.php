<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/programacion_catalogos.php';

$idProgGen = programacion_generar_id_programacion();
$nextInterno = programacion_siguiente_id_interno_preview($pdo);

$clientes = $pdo->query('SELECT ID_Cliente, Cliente FROM Clientes ORDER BY Cliente ASC')->fetchAll(PDO::FETCH_ASSOC);
$actividades = $pdo->query('SELECT ID_Actividad, Actividad FROM Actividad ORDER BY Actividad ASC')->fetchAll(PDO::FETCH_ASSOC);

$plantas = programacion_plantas_opciones();
$productosJson = json_encode(programacion_productos_por_planta(), JSON_UNESCAPED_UNICODE);
$cuarteoJson = json_encode(programacion_tipos_cuarteo_por_planta(), JSON_UNESCAPED_UNICODE);

$solicitantes = [];
$medios = [];
$municipios = [];
$conductores = [];
$vehiculos = [];
try {
    $solicitantes = $pdo->query('SELECT ID_Solicitante, Solicitante FROM solicitante ORDER BY Solicitante ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
try {
    $medios = $pdo->query('SELECT ID_Medio_Comunicacion, Medio_de_Comunicacion FROM medio_de_comunicacion ORDER BY Medio_de_Comunicacion ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
try {
    $municipios = $pdo->query('SELECT c, Municipio, Departamento FROM municipio ORDER BY Departamento ASC, Municipio ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
try {
    $conductores = $pdo->query('SELECT ID_Conductor, Conductor FROM conductor ORDER BY Conductor ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
try {
    $vehiculos = $pdo->query('SELECT ID_Vehiculo, Vehiculo FROM vehiculo ORDER BY Vehiculo ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Nueva programación</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex min-h-screen text-slate-800">
    <?php mostrarSidebar('prog'); ?>

    <div class="flex-1 ml-64 p-8 min-h-screen bg-[#f8fafc] pb-16">
        <header class="mb-8 flex justify-between items-start">
            <div>
                <a href="programacion.php" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline">← Volver</a>
                <h1 class="text-2xl font-black text-slate-800 mt-2">Nueva programación</h1>
                <p class="text-slate-500 text-sm mt-1">Complete los campos según el registro operativo. El <strong>código interno</strong> y el <strong>ID de programación</strong> son asignados por el sistema, no se pueden editar a mano y <strong>no pueden repetirse</strong>: cada registro tiene un par de identificadores único en toda la base de datos.</p>
            </div>
        </header>

        <form action="procesar_programacion.php" method="POST" class="max-w-5xl bg-white border border-slate-100 rounded-[2rem] p-8 shadow-sm space-y-10">
            <input type="hidden" name="id_programacion_generado" value="<?= htmlspecialchars($idProgGen, ENT_QUOTES, 'UTF-8') ?>">

            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <h2 class="md:col-span-2 text-[10px] font-black text-emerald-800 uppercase tracking-[0.2em]">Identificadores (solo lectura)</h2>
                <p class="md:col-span-2 text-[11px] text-emerald-900 leading-relaxed border border-emerald-300/60 rounded-xl px-4 py-3 bg-white/70">
                    <strong>Unicidad:</strong> el código interno y el ID de programación son <strong>únicos</strong> en el sistema; <strong>ninguno puede repetirse</strong> entre filas. El código interno lo garantiza la base de datos (clave primaria); el ID de programación se controla para que no coincida con uno ya existente.
                </p>
                <div>
                    <label class="block text-[9px] font-black text-emerald-900 uppercase mb-1">Próximo código interno (id_interno)</label>
                    <p class="text-lg font-black text-emerald-950 font-mono"><?= $nextInterno !== null ? (int) $nextInterno : '—' ?></p>
                    <p class="text-[10px] text-emerald-800 mt-1">Valor definitivo al guardar (AUTO_INCREMENT, sin duplicados).</p>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-emerald-900 uppercase mb-1">ID programación asignado</label>
                    <p class="text-lg font-black text-emerald-950 font-mono tracking-wide"><?= htmlspecialchars($idProgGen) ?></p>
                    <p class="text-[10px] text-emerald-800 mt-1">Se registrará este valor si sigue libre al guardar; si hubiera colisión (muy raro), recargue la página para otro ID. No admite repetidos.</p>
                </div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <h2 class="md:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Registro y comunicación</h2>
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Fecha de registro</label>
                    <p class="p-3 rounded-xl bg-slate-100 border border-slate-200 text-sm font-bold text-slate-600">Se generará automáticamente al guardar (fecha y hora del servidor).</p>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Solicitante</label>
                    <select name="solicitante" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Opcional —</option>
                        <?php foreach ($solicitantes as $s): ?>
                            <option value="<?= htmlspecialchars((string) $s['ID_Solicitante']) ?>"><?= htmlspecialchars((string) $s['Solicitante']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Medio de comunicación</label>
                    <select name="medio_comunicacion" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Opcional —</option>
                        <?php foreach ($medios as $m): ?>
                            <option value="<?= htmlspecialchars((string) $m['ID_Medio_Comunicacion']) ?>"><?= htmlspecialchars((string) $m['Medio_de_Comunicacion']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Estado (pedido / registro)</label>
                    <select name="estado_pedido" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="PROGRAMADO" selected>PROGRAMADO</option>
                    </select>
                </div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <h2 class="md:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Planta, producto y actividad</h2>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Planta operativa *</label>
                    <select name="planta_operativa" id="planta_operativa" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <?php foreach ($plantas as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Producto *</label>
                    <select name="producto" id="producto_select" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800"></select>
                </div>
                <div id="wrap_cuarteo">
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Tipo de cuarteo</label>
                    <select name="tipo_cuarteo" id="tipo_cuarteo" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">—</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Actividad *</label>
                    <select name="actividad" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Elegir —</option>
                        <?php foreach ($actividades as $a): ?>
                            <option value="<?= htmlspecialchars((string) $a['ID_Actividad']) ?>"><?= htmlspecialchars((string) $a['Actividad']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Cliente *</label>
                    <select name="cliente" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Elegir —</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= htmlspecialchars((string) $c['ID_Cliente']) ?>"><?= htmlspecialchars((string) $c['Cliente']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <h2 class="md:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Operación y logística</h2>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Fecha operación *</label>
                    <input type="date" name="fecha_operacion" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Hora</label>
                    <input type="time" name="hora" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Cantidad *</label>
                    <input type="number" name="cantidad" step="0.01" min="0" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" placeholder="0">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Lote</label>
                    <input type="text" name="lote" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Ciudad (municipio)</label>
                    <?php if ($municipios !== []): ?>
                    <select name="ciudad" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Opcional —</option>
                        <?php foreach ($municipios as $mun): ?>
                            <option value="<?= htmlspecialchars((string) $mun['c']) ?>"><?= htmlspecialchars((string) ($mun['Departamento'] ?? '') . ' — ' . ($mun['Municipio'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="number" name="ciudad" step="1" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" placeholder="Código municipio (c)">
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Destino</label>
                    <input type="text" name="destino" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Ubicación</label>
                    <input type="text" name="ubicacion" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">OPL / vínculo</label>
                    <input type="text" name="opl" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" placeholder="Código o ID OPL">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Conductor</label>
                    <select name="conductor" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">—</option>
                        <?php foreach ($conductores as $co): ?>
                            <option value="<?= htmlspecialchars((string) $co['ID_Conductor']) ?>"><?= htmlspecialchars((string) $co['Conductor']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Vehículo</label>
                    <select name="vehiculo" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">—</option>
                        <?php foreach ($vehiculos as $v): ?>
                            <option value="<?= htmlspecialchars((string) $v['ID_Vehiculo']) ?>"><?= htmlspecialchars((string) $v['Vehiculo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <h2 class="md:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">OTIF / calidad</h2>
                <?php
                $otifOpts = ['' => '—', 'SI' => 'SI', 'NO' => 'NO'];
                $otifFields = [
                    'cantidad_correcta' => 'Cantidad correcta',
                    'producto_correcto' => 'Producto correcto',
                    'entrega_tiempo' => 'Entrega a tiempo',
                    'direccion_correcta' => 'Dirección correcta',
                    'pedido_perfecto' => 'Pedido perfecto',
                ];
foreach ($otifFields as $name => $lab):
    ?>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1"><?= htmlspecialchars($lab) ?></label>
                    <select name="<?= htmlspecialchars($name) ?>" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <?php foreach ($otifOpts as $val => $disp): ?>
                            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($disp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <h2 class="md:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Estado y contacto</h2>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Estado actividad</label>
                    <select name="estado_actividad" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="PROGRAMADO" selected>PROGRAMADO</option>
                        <option value="EJECUTADO">EJECUTADO</option>
                        <option value="CANCELADO">CANCELADO</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Teléfono</label>
                    <input type="text" name="telefono" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="3" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800"></textarea>
                </div>
            </section>

            <div class="flex flex-col sm:flex-row gap-4 pt-4 border-t border-slate-100">
                <button type="submit" class="flex-1 bg-emerald-600 text-white py-4 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-emerald-500">Guardar programación</button>
                <a href="programacion.php" class="flex-1 bg-slate-100 text-slate-700 py-4 rounded-2xl font-black text-sm uppercase tracking-widest text-center border border-slate-200 hover:bg-slate-200">Cancelar</a>
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
            function refillProd() {
                var k = planta.value;
                var list = prodMap[k] || [];
                prod.innerHTML = '';
                list.forEach(function (p) {
                    var o = document.createElement('option');
                    o.value = p;
                    o.textContent = p;
                    prod.appendChild(o);
                });
            }
            function refillCuarteo() {
                var k = planta.value;
                var list = cuMap[k] || [];
                tc.innerHTML = '<option value="">—</option>';
                list.forEach(function (x) {
                    var o = document.createElement('option');
                    o.value = x;
                    o.textContent = x;
                    tc.appendChild(o);
                });
                wrapC.style.display = list.length ? 'block' : 'none';
            }
            planta.addEventListener('change', function () { refillProd(); refillCuarteo(); });
            refillProd();
            refillCuarteo();
        })();
        </script>
        <?php mostrarFooter(); ?>
    </div>
</body>
</html>
