<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/programacion_catalogos.php';
require_once __DIR__ . '/config/programacion_opl_logistica.php';

if (isset($_GET['duplicar_de']) && (int) $_GET['duplicar_de'] > 0 && !lm_es_admin()) {
    header('Location: programacion.php');
    exit();
}

$idProgGen = programacion_generar_id_programacion();
$nextInterno = programacion_siguiente_id_interno_preview($pdo);
$puedeCrearMaestros = lm_es_admin();

$clientes = $pdo->query('SELECT ID_Cliente, Cliente FROM Clientes ORDER BY Cliente ASC')->fetchAll(PDO::FETCH_ASSOC);
$actividades = $pdo->query('SELECT ID_Actividad, Actividad FROM Actividad ORDER BY Actividad ASC')->fetchAll(PDO::FETCH_ASSOC);

$actividadesRequeridas = [
    'TRASLADO A PCC',
    'ENTREGA INTERNA',
    'MOVIMIENTO INTERNO',
    'TRASLADO INTERNO',
    'ALISTAMIENTO',
    'REPROCESO',
];
$actividadesIndex = [];
foreach ($actividades as $a) {
    $nombreAct = trim((string) ($a['Actividad'] ?? ''));
    if ($nombreAct === '') {
        continue;
    }
    $actividadesIndex[mb_strtoupper($nombreAct)] = true;
}
foreach ($actividadesRequeridas as $nombreReq) {
    $keyReq = mb_strtoupper($nombreReq);
    if (isset($actividadesIndex[$keyReq])) {
        continue;
    }
    $actividades[] = [
        'ID_Actividad' => $nombreReq,
        'Actividad' => $nombreReq,
    ];
}

$plantas = programacion_plantas_opciones();
$productosJson = json_encode(programacion_productos_por_planta(), JSON_UNESCAPED_UNICODE);
$cuarteoJson = json_encode(programacion_tipos_cuarteo_por_planta(), JSON_UNESCAPED_UNICODE);

$solicitantes = [];
$medios = [];
$estadosPedido = [];
$estadosActividad = [];
$municipios = [];
$conductores = [];
$vehiculos = [];
$opls = [];
try {
    $solicitantes = $pdo->query('SELECT ID_Solicitante, Solicitante FROM solicitante ORDER BY Solicitante ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
try {
    $medios = $pdo->query('SELECT ID_Medio_Comunicacion, Medio_de_Comunicacion FROM medio_de_comunicacion ORDER BY Medio_de_Comunicacion ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
try {
    $estadosPedido = $pdo->query('SELECT ID_Estado, Estado FROM estado ORDER BY Estado ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
try {
    $estadosActividad = $pdo->query('SELECT ID_Estado_Actividad, Estado_Actividad FROM estado_actividad ORDER BY Estado_Actividad ASC')->fetchAll(PDO::FETCH_ASSOC);
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
try {
    $opls = $pdo->query('SELECT ID_OPL, OPL FROM opl ORDER BY OPL ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}

$oplRelacion = null;
$cacheKey = 'lm_opl_rel_cache_v1';
$cacheTtl = 90;
$cacheSig = md5(json_encode(array_map(static fn (array $o): string => (string) ($o['ID_OPL'] ?? ''), $opls), JSON_UNESCAPED_UNICODE) ?: 'none');
if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
    $cache = $_SESSION[$cacheKey];
    $cacheAt = (int) ($cache['at'] ?? 0);
    $cacheSigSaved = (string) ($cache['sig'] ?? '');
    if ($cacheSigSaved === $cacheSig && (time() - $cacheAt) <= $cacheTtl && isset($cache['data']) && is_array($cache['data'])) {
        $oplRelacion = $cache['data'];
    }
}
if ($oplRelacion === null) {
    $oplRelacion = programacion_opl_relaciones($pdo, $opls);
    $_SESSION[$cacheKey] = [
        'at' => time(),
        'sig' => $cacheSig,
        'data' => $oplRelacion,
    ];
}
$oplRelJson = json_encode($oplRelacion, JSON_UNESCAPED_UNICODE);
$conductoresChoicesJson = json_encode(array_map(static function (array $c): array {
    return [
        'id' => trim((string) ($c['ID_Conductor'] ?? '')),
        'nom' => (string) ($c['Conductor'] ?? ''),
    ];
}, $conductores), JSON_UNESCAPED_UNICODE);
$vehiculosChoicesJson = json_encode(array_map(static function (array $v): array {
    return [
        'id' => trim((string) ($v['ID_Vehiculo'] ?? '')),
        'nom' => (string) ($v['Vehiculo'] ?? ''),
    ];
}, $vehiculos), JSON_UNESCAPED_UNICODE);

$esDuplicar = false;
/** @var array<string, mixed>|null */
$dup = null;
$dupFechaOpYmd = '';
$dupHoraTime = '';
$dupPlantaOp = '';
$dupProd = '';
$dupTipoCuarteo = '';
$dupIdOrigen = 0;

$dupId = isset($_GET['duplicar_de']) ? (int) $_GET['duplicar_de'] : 0;
if ($dupId > 0 && lm_es_admin()) {
    $stDup = $pdo->prepare('SELECT * FROM Programacion WHERE id_interno = ?');
    $stDup->execute([$dupId]);
    $rowDup = $stDup->fetch(PDO::FETCH_ASSOC);
    if (is_array($rowDup)) {
        $esDuplicar = true;
        $dup = $rowDup;
        $dupIdOrigen = $dupId;
        $gDup = programacion_grupo_desde_fila($rowDup);
        $dupPlantaOp = $gDup !== '_SIN' && isset($plantas[$gDup]) ? $gDup : trim((string) ($rowDup['Planta_Operativa'] ?? ''));
        if ($dupPlantaOp === '' || !isset($plantas[$dupPlantaOp])) {
            $kf = array_key_first($plantas);
            $dupPlantaOp = $kf !== null ? (string) $kf : 'BENEFICIO';
        }
        if (programacion_es_producto_valido($dupPlantaOp, (string) ($rowDup['Producto'] ?? ''))) {
            $dupProd = (string) $rowDup['Producto'];
        } else {
            $ppx = programacion_productos_por_planta()[$dupPlantaOp] ?? [];
            $dupProd = $ppx[0] ?? '';
        }
        $dupTipoCuarteo = trim((string) ($rowDup['Tipo_de_Cuarteo'] ?? ''));
        $rawF = trim((string) ($rowDup['Fecha_de_Operacion'] ?? ''));
        if ($rawF !== '') {
            $dd = DateTime::createFromFormat('d/m/Y', $rawF);
            if ($dd instanceof DateTime) {
                $dupFechaOpYmd = $dd->format('Y-m-d');
            } else {
                $ts = strtotime($rawF);
                if ($ts !== false) {
                    $dupFechaOpYmd = date('Y-m-d', $ts);
                }
            }
        }
        $hRaw = trim((string) ($rowDup['Hora'] ?? ''));
        if (preg_match('/(\d{1,2}):(\d{2})/', $hRaw, $hm)) {
            $dupHoraTime = sprintf('%02d:%02d', (int) $hm[1], (int) $hm[2]);
        }
    }
} elseif ($dupId > 0 && lm_es_admin()) {
    $dupIdOrigen = $dupId;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Nueva programación</title>
    <?php lm_head_local_assets(); ?>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex min-h-screen text-slate-800">
    <?php mostrarSidebar('prog'); ?>

    <div class="flex-1 ml-64 p-8 min-h-screen bg-[#f8fafc] pb-16">
        <header class="mb-8 flex justify-between items-start">
            <div>
                <a href="programacion.php" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline">← Volver</a>
                <h1 class="text-2xl font-black text-slate-800 mt-2"><?= $esDuplicar ? 'Nueva programación (copia)' : 'Nueva programación' ?></h1>
                <?php if ($esDuplicar && $dup !== null): ?>
                    <p class="mt-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2 text-[11px] font-bold text-sky-900">
                        Copiando datos del registro interno <strong>#<?= (int) $dupIdOrigen ?></strong> (ID programación origen: <?= htmlspecialchars((string) ($dup['ID_Programacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>). Ajuste lo necesario y guarde: se creará un <strong>nuevo</strong> ID de programación y código interno.
                    </p>
                <?php elseif ($dupId > 0 && lm_es_admin()): ?>
                    <p class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-[11px] font-bold text-amber-900">
                        No se encontró el registro a duplicar. Complete el formulario desde cero o vuelva al listado.
                    </p>
                <?php endif; ?>
                <p class="text-slate-500 text-sm mt-1">Complete los campos según el registro operativo. El <strong>código interno</strong> y el <strong>ID de programación</strong> son asignados por el sistema, no se pueden editar a mano y <strong>no pueden repetirse</strong>: cada registro tiene un par de identificadores único en toda la base de datos.</p>
            </div>
        </header>

        <form action="procesar_programacion.php" method="POST" class="max-w-5xl bg-white border border-slate-100 rounded-[2rem] p-8 shadow-sm space-y-10">
            <?= lm_csrf_field() ?>
            <input type="hidden" name="id_programacion_generado" value="<?= htmlspecialchars($idProgGen, ENT_QUOTES, 'UTF-8') ?>">

            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <h2 class="md:col-span-2 text-[10px] font-black text-emerald-800 uppercase tracking-[0.2em]">Identificadores (solo lectura)</h2>
                <p class="md:col-span-2 text-[11px] text-emerald-900 leading-relaxed border border-emerald-300/60 rounded-xl px-4 py-3 bg-white/70">
                    <strong>Unicidad:</strong> el código interno y el ID de programación son <strong>únicos</strong> en el sistema; <strong>ninguno puede repetirse</strong> entre filas. El código interno lo garantiza la base de datos (clave primaria); el ID de programación se controla para que no coincida con uno ya existente.
                </p>
                <?php if ($esDuplicar && $dup !== null): ?>
                <p class="md:col-span-2 text-[11px] text-emerald-950 font-bold leading-relaxed border border-emerald-400/60 rounded-xl px-4 py-3 bg-white">
                    Al <strong>duplicar</strong> es lo mismo que una <strong>nueva programación</strong>: los dos valores de abajo son los <strong>nuevos</strong> que se grabarán (no se reutilizan los del registro copiado). El resto del formulario trae los datos del origen para que solo cambie lo necesario.
                </p>
                <?php endif; ?>
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
                    <select name="solicitante" id="solicitante_select" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Opcional —</option>
                        <?php
                        $dupSol = $esDuplicar && $dup !== null ? trim((string) ($dup['Solicitante'] ?? '')) : '';
                        foreach ($solicitantes as $s): ?>
                            <option value="<?= htmlspecialchars((string) $s['ID_Solicitante']) ?>"<?= ($dupSol !== '' && (string) $s['ID_Solicitante'] === $dupSol) ? ' selected' : '' ?>><?= htmlspecialchars((string) $s['Solicitante']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($puedeCrearMaestros): ?>
                    <button type="button" id="btn_nuevo_solicitante" class="mt-2 text-[10px] font-black uppercase tracking-wider text-emerald-700 hover:underline">
                        + Crear nuevo solicitante
                    </button>
                    <div id="wrap_nuevo_solicitante" class="mt-2 hidden">
                        <input type="text" id="solicitante_nuevo_texto" name="solicitante_nuevo" class="w-full p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-bold text-slate-800" placeholder="Nombre del nuevo solicitante" autocomplete="off">
                        <p class="mt-1 text-[10px] text-emerald-800 font-semibold leading-snug">Igual que en maestros: registro mínimo (ID automático + nombre). Si la tabla exige más columnas obligatorias, créelo en configuración.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Medio de comunicación</label>
                    <select name="medio_comunicacion" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Opcional —</option>
                        <?php
                        $dupMed = $esDuplicar && $dup !== null ? trim((string) ($dup['Medio_de_Comunicacion'] ?? '')) : '';
                        foreach ($medios as $m): ?>
                            <option value="<?= htmlspecialchars((string) $m['ID_Medio_Comunicacion']) ?>"<?= ($dupMed !== '' && (string) $m['ID_Medio_Comunicacion'] === $dupMed) ? ' selected' : '' ?>><?= htmlspecialchars((string) $m['Medio_de_Comunicacion']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Estado (pedido / registro)</label>
                    <select name="estado_pedido" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <?php if ($estadosPedido !== []): ?>
                            <?php foreach ($estadosPedido as $ep): ?>
                                <?php
                                    $labelEstado = trim((string) ($ep['Estado'] ?? ''));
                                    if ($labelEstado === '') {
                                        continue;
                                    }
                                ?>
                                <option value="<?= htmlspecialchars($labelEstado) ?>" <?php
                                    if ($esDuplicar && $dup !== null) {
                                        echo strcasecmp($labelEstado, trim((string) ($dup['Estado'] ?? ''))) === 0 ? 'selected' : '';
                                    } else {
                                        echo strcasecmp($labelEstado, 'PROGRAMADO') === 0 ? 'selected' : '';
                                    }
                                ?>>
                                    <?= htmlspecialchars($labelEstado) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="PROGRAMADO" selected>PROGRAMADO</option>
                            <option value="ADICIONAL">ADICIONAL</option>
                        <?php endif; ?>
                    </select>
                </div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <h2 class="md:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Planta, producto y actividad</h2>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Planta operativa *</label>
                    <select name="planta_operativa" id="planta_operativa" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <?php foreach ($plantas as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k) ?>"<?= ($esDuplicar && $dupPlantaOp === $k) ? ' selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
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
                        <?php
                        $dupAct = $esDuplicar && $dup !== null ? trim((string) ($dup['Actividad'] ?? '')) : '';
                        foreach ($actividades as $a): ?>
                            <option value="<?= htmlspecialchars((string) $a['ID_Actividad']) ?>"<?= ($dupAct !== '' && (string) $a['ID_Actividad'] === $dupAct) ? ' selected' : '' ?>><?= htmlspecialchars((string) $a['Actividad']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Cliente *</label>
                    <select name="cliente" id="cliente_select" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" <?= $puedeCrearMaestros ? '' : 'required' ?>>
                        <option value="">— Elegir —</option>
                        <?php
                        $dupCli = $esDuplicar && $dup !== null ? trim((string) ($dup['Cliente'] ?? '')) : '';
                        foreach ($clientes as $c): ?>
                            <option value="<?= htmlspecialchars((string) $c['ID_Cliente']) ?>"<?= ($dupCli !== '' && (string) $c['ID_Cliente'] === $dupCli) ? ' selected' : '' ?>><?= htmlspecialchars((string) $c['Cliente']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($puedeCrearMaestros): ?>
                    <button type="button" id="btn_nuevo_cliente" class="mt-2 text-[10px] font-black uppercase tracking-wider text-emerald-700 hover:underline">
                        + Crear nuevo cliente
                    </button>
                    <div id="wrap_nuevo_cliente" class="mt-2 hidden">
                        <input type="text" id="cliente_nuevo_texto" name="cliente_nuevo" class="w-full p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-bold text-slate-800" placeholder="Nombre del nuevo cliente" autocomplete="off">
                        <p class="mt-1 text-[10px] text-emerald-800 font-semibold leading-snug">Como en <strong>Configuración › Clientes</strong>: solo nombre e ID automático. Si el maestro exige más columnas obligatorias, créelo en maestros; quedará disponible para todos.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <h2 class="md:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Operación y logística</h2>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Fecha operación *</label>
                    <input type="date" name="fecha_operacion" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" value="<?= $esDuplicar ? htmlspecialchars($dupFechaOpYmd, ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Hora</label>
                    <input type="time" name="hora" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" value="<?= $esDuplicar ? htmlspecialchars($dupHoraTime, ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Cantidad *</label>
                    <input type="number" name="cantidad" step="0.01" min="0" required class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" placeholder="0" value="<?= ($esDuplicar && $dup !== null && ($dup['Cantidad'] ?? '') !== '') ? htmlspecialchars((string) $dup['Cantidad'], ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Lote</label>
                    <input type="text" name="lote" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" value="<?= $esDuplicar && $dup !== null ? htmlspecialchars((string) ($dup['Lote'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Ciudad (municipio)</label>
                    <?php if ($municipios !== []): ?>
                    <?php $dupCiudad = $esDuplicar && $dup !== null ? trim((string) ($dup['Ciudad'] ?? '')) : ''; ?>
                    <select name="ciudad" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Opcional —</option>
                        <?php foreach ($municipios as $mun): ?>
                            <?php $mc = (string) ($mun['c'] ?? ''); ?>
                            <option value="<?= htmlspecialchars($mc) ?>"<?= ($dupCiudad !== '' && $mc === $dupCiudad) ? ' selected' : '' ?>><?= htmlspecialchars((string) ($mun['Departamento'] ?? '') . ' — ' . ($mun['Municipio'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="number" name="ciudad" step="1" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" placeholder="Código municipio (c)" value="<?= ($esDuplicar && $dup !== null && ($dup['Ciudad'] ?? '') !== '') ? htmlspecialchars((string) $dup['Ciudad'], ENT_QUOTES, 'UTF-8') : '' ?>">
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Destino</label>
                    <input type="text" name="destino" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" value="<?= $esDuplicar && $dup !== null ? htmlspecialchars((string) ($dup['Destino'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Ubicación</label>
                    <input type="text" name="ubicacion" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" value="<?= $esDuplicar && $dup !== null ? htmlspecialchars((string) ($dup['Ubicacion'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">OPL / vínculo</label>
                    <select name="opl" id="opl_select" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">— Opcional —</option>
                        <?php $dupOpl = $esDuplicar && $dup !== null ? trim((string) ($dup['OPL'] ?? '')) : ''; ?>
                        <?php foreach ($opls as $o): ?>
                            <?php
                                $idOpl = trim((string) ($o['ID_OPL'] ?? ''));
                                $nombreOpl = trim((string) ($o['OPL'] ?? ''));
                                if ($idOpl === '' && $nombreOpl === '') {
                                    continue;
                                }
                                $labelOpl = $nombreOpl !== '' ? $nombreOpl : $idOpl;
                                $optVal = $idOpl !== '' ? $idOpl : $labelOpl;
                                $selOpl = $dupOpl !== '' && ($dupOpl === $optVal || $dupOpl === $idOpl || $dupOpl === $labelOpl);
                            ?>
                            <option value="<?= htmlspecialchars($optVal) ?>"<?= $selOpl ? ' selected' : '' ?>>
                                <?= htmlspecialchars($labelOpl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="opl_rel_aviso" class="mt-1 text-[10px] text-slate-500 font-semibold leading-snug"></p>
                    <?php if ($puedeCrearMaestros): ?>
                    <button type="button" id="btn_nuevo_opl" class="mt-2 text-[10px] font-black uppercase tracking-wider text-emerald-700 hover:underline">
                        + Crear nueva OPL
                    </button>
                    <div id="wrap_nuevo_opl" class="mt-2 hidden space-y-3">
                        <input type="text" id="opl_nuevo_texto" name="opl_nuevo" class="w-full p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-bold text-slate-800" placeholder="Nombre de la nueva OPL" autocomplete="off">
                        <div class="p-4 rounded-2xl border border-emerald-100 bg-emerald-50/50">
                            <p class="text-[10px] font-black text-emerald-800 uppercase tracking-widest mb-1">Relación OPL (logísticos)</p>
                            <p class="text-[10px] font-semibold text-emerald-900 mb-3 leading-relaxed">Igual que en <strong>Configuración › OPL</strong>: opcionalmente relacione esta OPL con un conductor y un vehículo existentes en la tabla puente. Si no completa esto, podrá hacerlo después en maestros.</p>
                            <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Conductor existente</label>
                            <select name="opl_vinculo_conductor" id="opl_vinculo_conductor" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800 mb-2">
                                <option value="">— Opcional —</option>
                                <?php foreach ($conductores as $co): ?>
                                    <option value="<?= htmlspecialchars((string) $co['ID_Conductor']) ?>"><?= htmlspecialchars((string) $co['Conductor']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Vehículo existente</label>
                            <select name="opl_vinculo_vehiculo" id="opl_vinculo_vehiculo" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800 mb-3">
                                <option value="">— Opcional —</option>
                                <?php foreach ($vehiculos as $v): ?>
                                    <option value="<?= htmlspecialchars((string) $v['ID_Vehiculo']) ?>"><?= htmlspecialchars((string) $v['Vehiculo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" name="opl_vinculo_misma_programacion" id="opl_vinculo_misma_programacion" value="1" class="mt-1 rounded border-emerald-300 text-emerald-700 focus:ring-emerald-500">
                                <span class="text-[10px] font-semibold text-emerald-900 leading-snug">Usar el <strong>conductor</strong> y <strong>vehículo</strong> elegidos más abajo en esta misma programación (incluye si los acaba de crear como «nuevos»). Solo aplica si no eligió ambos en los desplegables de arriba.</span>
                            </label>
                        </div>
                        <p class="text-[10px] text-emerald-700 font-semibold">Se guardará la fila en el maestro OPL; el vínculo en logísticos requiere conductor y vehículo (existentes o los de esta pantalla con la casilla).</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Conductor</label>
                    <select name="conductor" id="conductor_select" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">—</option>
                        <?php $dupCond = $esDuplicar && $dup !== null ? trim((string) ($dup['Conductor'] ?? '')) : ''; ?>
                        <?php foreach ($conductores as $co): ?>
                            <option value="<?= htmlspecialchars((string) $co['ID_Conductor']) ?>"<?= ($dupCond !== '' && (string) $co['ID_Conductor'] === $dupCond) ? ' selected' : '' ?>><?= htmlspecialchars((string) $co['Conductor']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($puedeCrearMaestros): ?>
                    <button type="button" id="btn_nuevo_conductor" class="mt-2 text-[10px] font-black uppercase tracking-wider text-emerald-700 hover:underline">
                        + Crear nuevo conductor
                    </button>
                    <div id="wrap_nuevo_conductor" class="mt-2 hidden">
                        <input type="text" id="conductor_nuevo_texto" name="conductor_nuevo" class="w-full p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-bold text-slate-800" placeholder="Nombre del nuevo conductor">
                        <p class="mt-1 text-[10px] text-emerald-700 font-semibold">Se guardará en la tabla de conductores y quedará disponible para todos.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Vehículo</label>
                    <select name="vehiculo" id="vehiculo_select" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <option value="">—</option>
                        <?php $dupVeh = $esDuplicar && $dup !== null ? trim((string) ($dup['Vehiculo'] ?? '')) : ''; ?>
                        <?php foreach ($vehiculos as $v): ?>
                            <option value="<?= htmlspecialchars((string) $v['ID_Vehiculo']) ?>"<?= ($dupVeh !== '' && (string) $v['ID_Vehiculo'] === $dupVeh) ? ' selected' : '' ?>><?= htmlspecialchars((string) $v['Vehiculo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($puedeCrearMaestros): ?>
                    <button type="button" id="btn_nuevo_vehiculo" class="mt-2 text-[10px] font-black uppercase tracking-wider text-emerald-700 hover:underline">
                        + Crear nuevo vehículo
                    </button>
                    <div id="wrap_nuevo_vehiculo" class="mt-2 hidden">
                        <input type="text" id="vehiculo_nuevo_texto" name="vehiculo_nuevo" class="w-full p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-bold text-slate-800" placeholder="Nombre o placa del nuevo vehículo" autocomplete="off">
                        <p class="mt-1 text-[10px] text-emerald-800 font-semibold leading-snug">Paridad con <strong>Configuración › Vehículos</strong>: registro mínimo (ID + texto). Campos adicionales del maestro debe cargarlos en configuración si hace falta.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <h2 class="md:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Estado y contacto</h2>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Estado actividad</label>
                    <select name="estado_actividad" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800">
                        <?php if ($estadosActividad !== []): ?>
                            <?php foreach ($estadosActividad as $ea): ?>
                                <?php
                                    $labelEstadoActividad = trim((string) ($ea['Estado_Actividad'] ?? ''));
                                    if ($labelEstadoActividad === '') {
                                        continue;
                                    }
                                ?>
                                <option value="<?= htmlspecialchars($labelEstadoActividad) ?>" <?php
                                    if ($esDuplicar && $dup !== null) {
                                        echo strcasecmp($labelEstadoActividad, trim((string) ($dup['Estado_Actividad'] ?? ''))) === 0 ? 'selected' : '';
                                    } else {
                                        echo strcasecmp($labelEstadoActividad, 'PROGRAMADO') === 0 ? 'selected' : '';
                                    }
                                ?>><?= htmlspecialchars($labelEstadoActividad) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php $dupEstAct = $esDuplicar && $dup !== null ? mb_strtoupper(trim((string) ($dup['Estado_Actividad'] ?? '')), 'UTF-8') : ''; ?>
                            <option value="PROGRAMADO"<?= (!$esDuplicar || $dupEstAct === '' || $dupEstAct === 'PROGRAMADO') ? ' selected' : '' ?>>PROGRAMADO</option>
                            <option value="EJECUTADO"<?= ($esDuplicar && $dupEstAct === 'EJECUTADO') ? ' selected' : '' ?>>EJECUTADO</option>
                            <option value="CANCELADO"<?= ($esDuplicar && $dupEstAct === 'CANCELADO') ? ' selected' : '' ?>>CANCELADO</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Teléfono</label>
                    <input type="text" name="telefono" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800" value="<?= $esDuplicar && $dup !== null ? htmlspecialchars((string) ($dup['Telefono'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Observaciones</label>
                    <textarea name="observaciones" rows="3" class="w-full p-3 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800"><?= $esDuplicar && $dup !== null ? htmlspecialchars((string) ($dup['Observaciones'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?></textarea>
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
            var oplRel = <?= $oplRelJson ?>;
            var conductoresChoices = <?= $conductoresChoicesJson ?>;
            var vehiculosChoices = <?= $vehiculosChoicesJson ?>;
            var planta = document.getElementById('planta_operativa');
            var prod = document.getElementById('producto_select');
            var tc = document.getElementById('tipo_cuarteo');
            var wrapC = document.getElementById('wrap_cuarteo');
            var selOpl = document.getElementById('opl_select');
            var selVeh = document.getElementById('vehiculo_select');
            var avisoOpl = document.getElementById('opl_rel_aviso');
            var puedeCrearMaestros = <?= $puedeCrearMaestros ? 'true' : 'false' ?>;
            var selectConductor = document.getElementById('conductor_select');
            var curProd = <?= json_encode($dupProd, JSON_UNESCAPED_UNICODE) ?>;
            var curTc = <?= json_encode($dupTipoCuarteo, JSON_UNESCAPED_UNICODE) ?>;
            var dupConductorPost = <?= json_encode($esDuplicar && $dup !== null ? trim((string) ($dup['Conductor'] ?? '')) : '', JSON_UNESCAPED_UNICODE) ?>;
            var dupVehiculoPost = <?= json_encode($esDuplicar && $dup !== null ? trim((string) ($dup['Vehiculo'] ?? '')) : '', JSON_UNESCAPED_UNICODE) ?>;
            function repoblarSelect(select, items, idsPermitidos, labelVacio) {
                if (!select) return;
                var prev = select.value;
                select.innerHTML = '';
                var o0 = document.createElement('option');
                o0.value = '';
                o0.textContent = labelVacio;
                select.appendChild(o0);
                var allow = null;
                if (idsPermitidos && idsPermitidos.length) {
                    allow = {};
                    idsPermitidos.forEach(function (id) { allow[String(id)] = true; });
                }
                items.forEach(function (it) {
                    if (!it || !it.id) return;
                    if (allow && !allow[String(it.id)]) return;
                    var o = document.createElement('option');
                    o.value = it.id;
                    o.textContent = it.nom || it.id;
                    select.appendChild(o);
                });
                if (prev) {
                    if (!allow || allow[String(prev)]) {
                        for (var i = 0; i < select.options.length; i++) {
                            if (select.options[i].value === prev) {
                                select.value = prev;
                                break;
                            }
                        }
                    }
                }
            }
            function refreshOplFiltros() {
                if (!selectConductor || !selVeh) return;
                if (!oplRel || !oplRel.usaFiltro) {
                    repoblarSelect(selectConductor, conductoresChoices, null, '—');
                    repoblarSelect(selVeh, vehiculosChoices, null, '—');
                    if (avisoOpl) avisoOpl.textContent = '';
                    return;
                }
                var opl = selOpl && selOpl.value ? String(selOpl.value).trim() : '';
                if (!opl) {
                    repoblarSelect(selectConductor, conductoresChoices, null, '—');
                    repoblarSelect(selVeh, vehiculosChoices, null, '—');
                    if (avisoOpl) avisoOpl.textContent = '';
                    return;
                }
                var bucket = oplRel.porOpl && oplRel.porOpl[opl] ? oplRel.porOpl[opl] : null;
                if (!bucket) {
                    repoblarSelect(selectConductor, conductoresChoices, null, '—');
                    repoblarSelect(selVeh, vehiculosChoices, null, '—');
                    if (avisoOpl) {
                        avisoOpl.textContent = 'Esta OPL aún no tiene enlaces registrados; puede elegir cualquier conductor y vehículo.';
                    }
                    return;
                }
                var cF = (bucket.c && bucket.c.length) ? bucket.c : null;
                var vF = (bucket.v && bucket.v.length) ? bucket.v : null;
                repoblarSelect(selectConductor, conductoresChoices, cF, '—');
                repoblarSelect(selVeh, vehiculosChoices, vF, '—');
                if (avisoOpl) {
                    if (cF || vF) {
                        avisoOpl.textContent = 'Listas filtradas según la OPL seleccionada.';
                    } else {
                        avisoOpl.textContent = '';
                    }
                }
            }
            function wireCrearNuevo(btnId, wrapId, txtId, selectEl, onToggle) {
                var btn = document.getElementById(btnId);
                var wrap = document.getElementById(wrapId);
                var txt = document.getElementById(txtId);
                if (!btn || !wrap || !txt) return;
                btn.addEventListener('click', function () {
                    var estabaOculto = wrap.classList.contains('hidden');
                    wrap.classList.toggle('hidden', !estabaOculto);
                    if (!estabaOculto) {
                        txt.value = '';
                        if (selectEl === selOpl || selectEl === selVeh) {
                            refreshOplFiltros();
                        }
                    }
                    if (typeof onToggle === 'function') {
                        onToggle(estabaOculto);
                    }
                    if (estabaOculto) {
                        txt.focus();
                    }
                });
                if (selectEl) {
                    selectEl.addEventListener('change', function () {
                        if (selectEl.value !== '') {
                            txt.value = '';
                        }
                    });
                }
                txt.addEventListener('input', function () {
                    if (txt.value.trim() !== '' && selectEl) {
                        selectEl.value = '';
                    }
                    if (selectEl === selOpl || selectEl === selVeh) {
                        refreshOplFiltros();
                    }
                });
            }
            function refillProd() {
                var k = planta.value;
                var list = prodMap[k] || [];
                prod.innerHTML = '';
                var ok = false;
                list.forEach(function (p) {
                    var o = document.createElement('option');
                    o.value = p;
                    o.textContent = p;
                    if (curProd && p === curProd) {
                        o.selected = true;
                        ok = true;
                    }
                    prod.appendChild(o);
                });
                if (!ok && list.length) {
                    prod.selectedIndex = 0;
                }
                if (curProd && !ok) {
                    curProd = '';
                }
            }
            function refillCuarteo() {
                var k = planta.value;
                var list = cuMap[k] || [];
                tc.innerHTML = '<option value="">—</option>';
                list.forEach(function (x) {
                    var o = document.createElement('option');
                    o.value = x;
                    o.textContent = x;
                    if (curTc && x === curTc) {
                        o.selected = true;
                    }
                    tc.appendChild(o);
                });
                var productoActual = (prod.value || '').trim().toUpperCase();
                var mostrarCuarteo = productoActual === 'CANALES' && list.length > 0;
                if (!mostrarCuarteo) {
                    tc.value = '';
                }
                wrapC.style.display = mostrarCuarteo ? 'block' : 'none';
            }
            planta.addEventListener('change', function () {
                curProd = '';
                curTc = '';
                refillProd();
                refillCuarteo();
            });
            prod.addEventListener('change', refillCuarteo);
            function limpiarOplNuevoExtras() {
                var s1 = document.getElementById('opl_vinculo_conductor');
                var s2 = document.getElementById('opl_vinculo_vehiculo');
                var cb = document.getElementById('opl_vinculo_misma_programacion');
                if (s1) s1.value = '';
                if (s2) s2.value = '';
                if (cb) cb.checked = false;
            }
            wireCrearNuevo('btn_nuevo_cliente', 'wrap_nuevo_cliente', 'cliente_nuevo_texto', document.getElementById('cliente_select'));
            wireCrearNuevo('btn_nuevo_solicitante', 'wrap_nuevo_solicitante', 'solicitante_nuevo_texto', document.getElementById('solicitante_select'));
            wireCrearNuevo('btn_nuevo_opl', 'wrap_nuevo_opl', 'opl_nuevo_texto', selOpl, function (abrioDesdeOculto) {
                if (!abrioDesdeOculto) {
                    limpiarOplNuevoExtras();
                }
            });
            wireCrearNuevo('btn_nuevo_vehiculo', 'wrap_nuevo_vehiculo', 'vehiculo_nuevo_texto', selVeh);
            wireCrearNuevo('btn_nuevo_conductor', 'wrap_nuevo_conductor', 'conductor_nuevo_texto', selectConductor);
            refillProd();
            refillCuarteo();
            if (selOpl) selOpl.addEventListener('change', refreshOplFiltros);
            refreshOplFiltros();
            if (dupConductorPost && selectConductor) {
                selectConductor.value = dupConductorPost;
            }
            if (dupVehiculoPost && selVeh) {
                selVeh.value = dupVehiculoPost;
            }
            var formProg = document.querySelector('form[action="procesar_programacion.php"]');
            if (formProg && puedeCrearMaestros) {
                formProg.addEventListener('submit', function (ev) {
                    var cs = document.getElementById('cliente_select');
                    var ct = document.getElementById('cliente_nuevo_texto');
                    if (!cs || !ct) return;
                    var ok = (cs.value && String(cs.value).trim() !== '') || (ct.value && ct.value.trim() !== '');
                    if (!ok) {
                        ev.preventDefault();
                        window.alert('Seleccione un cliente o indique el nombre del cliente nuevo.');
                    }
                });
            }
        })();
        </script>
        <?php mostrarFooter(); ?>
    </div>
</body>
</html>
