<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/logisticos_vinculo_maestro.php';

// 1. SEGURIDAD Y PARÁMETROS DE RUTA
$es_admin = lm_es_admin();
$es_super_admin = lm_es_super_admin();
$tabla_get = strtolower(trim((string) ($_GET['tabla'] ?? 'clientes')));
$tablas_permitidas = [
    'clientes', 'corte', 'departamento', 'municipio', 'opl', 'producto', 'tipo_de_cuarteo', 'zona', 'vehiculo', 'conductor',
    'user', 'actividad', 'planta', 'logisticos',
    'empleado', 'empleado_descanso', 'empleado_programacion',
];

if (!in_array($tabla_get, $tablas_permitidas, true)) {
    header("Location: maestros.php?error=no_autorizado");
    exit();
}
if ($tabla_get === 'user' && !$es_super_admin) {
    header("Location: maestros.php?error=no_autorizado");
    exit();
}

$titulo_modulo = strtoupper(str_replace('_', ' ', $tabla_get));

// 2. DETECCIÓN AUTOMÁTICA DE IDs (Técnico y de Negocio)
$stmtCol = $pdo->query("DESCRIBE `$tabla_get`");
$columnas_info = $stmtCol->fetchAll(PDO::FETCH_ASSOC);
$columna_id_maestro = '';
$tiene_id_interno = false;
$columna_pk = null;

foreach($columnas_info as $c) {
    $campo = $c['Field'];
    if(strtolower($campo) === 'id_interno') $tiene_id_interno = true;
    if (($c['Key'] ?? '') === 'PRI' && $columna_pk === null) {
        $columna_pk = $campo;
    }
    
    // Identificamos el ID de negocio (ID_Cliente, ID_OPL, Identificacion...)
    if((strpos(strtoupper($campo), 'ID_') === 0 || strtoupper($campo) === 'IDENTIFICACION') && strtoupper($campo) !== 'ID_INTERNO') {
        if(!$columna_id_maestro) $columna_id_maestro = $campo;
    }
}
if ($columna_pk === null) {
    $columna_pk = 'id_interno';
}

$es_tabla_empleado = strtolower($tabla_get) === 'empleado';
$empleado_cedula_como_pk = $es_tabla_empleado && strtoupper((string) $columna_pk) === 'ID_EMPLEADO';
$camposEmpleadoLower = $es_tabla_empleado
    ? array_map(static fn (string $f): string => strtolower($f), array_column($columnas_info, 'Field'))
    : [];
$empleadoTienePuestoTrabajo = $es_tabla_empleado && in_array('puesto_trabajo', $camposEmpleadoLower, true);
$es_tabla_user = strtolower($tabla_get) === 'user';

/**
 * Genera el siguiente ID de negocio (servidor; no confiar en el formulario).
 */
function calcularSiguienteIdNegocio(PDO $pdo, string $tabla, string $col): string {
    $q = $pdo->query("SELECT `$col` FROM `$tabla`");
    $vals = $q ? $q->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    $vals = array_filter($vals, static fn ($v) => $v !== null && $v !== '');

    if ($vals === []) {
        return strtoupper($col) === 'ID_USER' ? 'US-0001' : '1';
    }

    $maxUs = 0;
    foreach ($vals as $v) {
        $s = (string) $v;
        if (preg_match('/^US-(\d+)$/i', $s, $m)) {
            $maxUs = max($maxUs, (int) $m[1]);
        }
    }
    if ($maxUs > 0) {
        return 'US-' . str_pad((string) ($maxUs + 1), 4, '0', STR_PAD_LEFT);
    }

    $soloDigitos = true;
    foreach ($vals as $v) {
        if (!preg_match('/^\d+$/', (string) $v)) {
            $soloDigitos = false;
            break;
        }
    }
    if ($soloDigitos && $vals !== []) {
        $max = $pdo->query("SELECT MAX(CAST(`$col` AS UNSIGNED)) FROM `$tabla` WHERE `$col` REGEXP '^[0-9]+$'")->fetchColumn();
        $n = ($max !== null && $max !== false && $max !== '') ? (int) $max + 1 : 1;
        return (string) $n;
    }

    return substr(bin2hex(random_bytes(4)), 0, 8);
}

// 3. CÁLCULO DEL SIGUIENTE CONSECUTIVO (vista previa en modal)
$siguiente_id_valor = '';
if ($columna_id_maestro) {
    $siguiente_id_valor = calcularSiguienteIdNegocio($pdo, $tabla_get, $columna_id_maestro);
}

// Siguiente id_interno (solo vista; el INSERT no lo envía: lo asigna AUTO_INCREMENT en MySQL)
$siguiente_id_interno = null;
if ($tiene_id_interno) {
    $siguiente_id_interno = (int) $pdo->query("SELECT COALESCE(MAX(id_interno), 0) + 1 FROM `$tabla_get`")->fetchColumn();
}

// 4. PROCESAMIENTO POST (CRUD)
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $es_admin) {
    if (!lm_csrf_validar($_POST['_csrf'] ?? null)) {
        header('Location: maestros.php?error=csrf');
        exit();
    }
    try {
        if ($_POST['action'] == 'eliminar_manual') {
            $id_borrar = $_POST['id_a_borrar'];
            $colPk = str_replace('`', '``', $columna_pk);
            $stmt = $pdo->prepare("DELETE FROM `$tabla_get` WHERE `$colPk` = ?");
            $stmt->execute([$id_borrar]);
        } 
        else {
            $datos_post = $_POST;
            $id_interno_referencia = $datos_post['id_interno_hidden'] ?? '';
            $action = $datos_post['action'];
            $vincConductor = trim((string) ($datos_post['vinculo_conductor'] ?? ''));
            $vincVehiculo = trim((string) ($datos_post['vinculo_vehiculo'] ?? ''));
            
            // Limpiamos datos que no van directo a columnas
            unset(
                $datos_post['_csrf'],
                $datos_post['action'],
                $datos_post['id_interno_hidden'],
                $datos_post['id_interno'],
                $datos_post['vinculo_conductor'],
                $datos_post['vinculo_vehiculo']
            );

            if ($action == 'crear') {
                $asignar_id_auto = !($empleado_cedula_como_pk);
                if ($columna_id_maestro && $asignar_id_auto) {
                    $datos_post[$columna_id_maestro] = calcularSiguienteIdNegocio($pdo, $tabla_get, $columna_id_maestro);
                }
                if ($empleado_cedula_como_pk) {
                    $ie = trim((string) ($datos_post['ID_Empleado'] ?? ''));
                    $nd = trim((string) ($datos_post['Numero_Documento'] ?? ''));
                    if ($ie !== '' && $nd === '') {
                        $datos_post['Numero_Documento'] = $ie;
                    }
                    if ($nd !== '' && $ie === '') {
                        $datos_post['ID_Empleado'] = $nd;
                    }
                }
                $columnas_sql = array_keys($datos_post);
                $placeholders = str_repeat('?,', count($columnas_sql) - 1) . '?';
                $sql = "INSERT INTO `$tabla_get` (" . implode(',', $columnas_sql) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($datos_post));
                if (strtolower($tabla_get) === 'opl' && $columna_id_maestro) {
                    $idOplNuevo = trim((string) ($datos_post[$columna_id_maestro] ?? ''));
                    if ($idOplNuevo !== '' && $vincConductor !== '' && $vincVehiculo !== '') {
                        logisticos_maestro_insertar_triple($pdo, $idOplNuevo, $vincConductor, $vincVehiculo);
                    }
                }
            } else {
                $oldPkRef = trim((string) $id_interno_referencia);
                $nuevoPkEmpleado = '';
                if ($empleado_cedula_como_pk && $columna_pk) {
                    $nuevoPkEmpleado = trim((string) ($datos_post[$columna_pk] ?? ''));
                }
                if ($columna_pk && isset($datos_post[$columna_pk])) {
                    unset($datos_post[$columna_pk]);
                }
                $txEmpleadoPk = $empleado_cedula_como_pk && $nuevoPkEmpleado !== '' && $nuevoPkEmpleado !== $oldPkRef;
                if ($txEmpleadoPk) {
                    $pdo->beginTransaction();
                }
                try {
                    if ($txEmpleadoPk) {
                        $pdo->prepare('UPDATE empleado_descanso SET `ID_Empleado`=? WHERE `ID_Empleado`=?')->execute([$nuevoPkEmpleado, $oldPkRef]);
                        $pdo->prepare('UPDATE empleado_programacion SET `ID_Empleado`=? WHERE `ID_Empleado`=?')->execute([$nuevoPkEmpleado, $oldPkRef]);
                        $datos_post[$columna_pk] = $nuevoPkEmpleado;
                    }
                    $columnas_sql = array_keys($datos_post);
                    $set_query = implode('=?, ', $columnas_sql) . '=?';
                    $colPk = str_replace('`', '``', $columna_pk);
                    $sql = "UPDATE `$tabla_get` SET $set_query WHERE `$colPk` = ?";
                    $params = array_values($datos_post);
                    $params[] = $id_interno_referencia;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    if ($txEmpleadoPk) {
                        $pdo->commit();
                    }
                    if (strtolower($tabla_get) === 'opl' && $columna_id_maestro) {
                        $idOplEditado = trim((string) ($datos_post[$columna_id_maestro] ?? ''));
                        if ($idOplEditado !== '' && $vincConductor !== '' && $vincVehiculo !== '') {
                            logisticos_maestro_insertar_triple($pdo, $idOplEditado, $vincConductor, $vincVehiculo);
                        }
                    }
                } catch (PDOException $eUpd) {
                    if ($txEmpleadoPk && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $eUpd;
                }
            }
        }
        header("Location: gestion_tabla.php?tabla=$tabla_get&msg=success");
        exit();
    } catch (PDOException $e) {
        $error_msg = 'Error al guardar: ' . $e->getMessage();
    }
}

// 5. CONSULTA DE DATOS
$ordenListado = '1 DESC';
if ($es_tabla_empleado && $columna_pk === 'ID_Empleado') {
    $ordenListado = '`ID_Empleado` ASC';
} elseif ($tiene_id_interno) {
    $ordenListado = '`id_interno` DESC';
}
$stmt = $pdo->query("SELECT * FROM `$tabla_get` ORDER BY $ordenListado");
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$columnas_vista = !empty($filas) ? array_keys($filas[0]) : array_column($columnas_info, 'Field');
$opcionesRelacionOpl = ['conductores' => [], 'vehiculos' => []];
$relacionesDetectadasOpl = [];
if ($es_admin && strtolower($tabla_get) === 'opl') {
    try {
        $opcionesRelacionOpl['conductores'] = $pdo->query('SELECT ID_Conductor, Conductor FROM conductor ORDER BY Conductor ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
    }
    try {
        $opcionesRelacionOpl['vehiculos'] = $pdo->query('SELECT ID_Vehiculo, Vehiculo FROM vehiculo ORDER BY Vehiculo ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
    }
    try {
        $oplsVista = $pdo->query('SELECT ID_OPL, OPL FROM opl ORDER BY OPL ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rel = programacion_opl_relaciones($pdo, $oplsVista);
        foreach (($rel['porOpl'] ?? []) as $idOpl => $bucket) {
            $id = trim((string) $idOpl);
            if ($id === '') {
                continue;
            }
            $c = array_values(array_filter(
                array_map(static fn ($x): string => trim((string) $x), $bucket['c'] ?? []),
                static fn (string $x): bool => $x !== ''
            ));
            $v = array_values(array_filter(
                array_map(static fn ($x): string => trim((string) $x), $bucket['v'] ?? []),
                static fn (string $x): bool => $x !== ''
            ));
            $relacionesDetectadasOpl[$id] = ['c' => $c, 'v' => $v];
        }
    } catch (Throwable) {
        $relacionesDetectadasOpl = [];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Maestros | <?= $titulo_modulo ?></title>
    <?php lm_head_local_assets(); ?>
    <style> 
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; overflow-x: hidden; } 
        .modal-active { display: flex !important; }
        /* Estetica mejorada para campos bloqueados */
        .input-locked { background-color: #f8fafc !important; color: #64748b !important; cursor: not-allowed; border: 1px solid #cbd5e1 !important; font-weight: 700; opacity: 0.8; }
        .id-interno-style { background-color: #eff6ff !important; border-left: 4px solid #3b82f6 !important; color: #1e40af !important; font-weight: 800; }
    </style>
</head>
<body class="flex min-h-screen">

    <?php mostrarSidebar('configuracion'); ?>

    <div class="flex-1 flex flex-col ml-64 min-h-screen w-[calc(100%-16rem)]">
        <main class="p-6 flex-grow">
            <?php if ($error_msg !== ''): ?>
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-[11px] font-bold text-red-700">
                    <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[11px] font-bold text-emerald-800">
                    Cambios guardados correctamente.
                </div>
            <?php endif; ?>

            <header class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-extrabold text-slate-800 tracking-tighter italic uppercase"><?= $titulo_modulo ?></h2>
                <?php if ($es_admin): ?>
                <div class="flex gap-2">
                    <button onclick="document.getElementById('modalEliminar').classList.add('modal-active')" class="bg-white border border-red-100 text-red-500 px-4 py-2 rounded-xl font-black text-[10px] uppercase transition-all hover:bg-red-50">🗑️ Eliminar</button>
                    <button onclick="abrirModalCrear()" class="bg-blue-600 text-white px-5 py-2 rounded-xl font-black text-[10px] shadow-lg uppercase transition-all hover:scale-105 active:scale-95">+ Nuevo Registro</button>
                </div>
                <?php endif; ?>
            </header>

            <div class="mb-6 relative">
                <span class="absolute left-5 top-4 text-slate-400 text-lg">🔍</span>
                <input type="text" id="globalSearch" onkeyup="filterMasterTable()" 
                    placeholder="BUSCAR EN <?= $titulo_modulo ?> POR CUALQUIER DATO..." 
                    class="w-full pl-12 pr-6 py-4 bg-white border border-slate-100 rounded-[1.5rem] shadow-sm outline-none text-xs font-bold uppercase transition-all focus:ring-4 focus:ring-blue-500/10">
            </div>

            <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="mainMasterTable" class="w-full text-left text-[10px]">
                        <thead class="bg-slate-50 text-slate-400 uppercase font-black border-b border-slate-100">
                            <tr>
                                <?php foreach($columnas_vista as $col): ?>
                                    <th class="p-4 <?php if(strtolower($col)===strtolower((string)$columna_pk)) echo 'text-blue-500'; ?>">
                                        <?= str_replace('_', ' ', strtoupper($col)) ?>
                                    </th>
                                <?php endforeach; ?>
                                <?php if($es_admin): ?> <th class="p-4 text-center">Acción</th> <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($filas as $f): ?>
                            <tr class="row-data hover:bg-blue-50/50 cursor-pointer transition-all" 
                                <?php if($es_admin): ?> onclick='prepararEdicion(<?= json_encode($f) ?>)' <?php endif; ?>>
                                <?php foreach($f as $col_name => $valor): ?>
                                    <td class="p-4 font-bold <?php if(strtolower((string)$col_name)===strtolower((string)$columna_pk)) echo 'text-blue-600/60'; else echo 'text-slate-700'; ?>">
                                        <?= htmlspecialchars($valor ?? '0') ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php if($es_admin): ?>
                                    <td class="p-4 text-center text-blue-600 font-black tracking-tighter">EDITAR ❯</td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <?php if($es_admin): ?>
    <div id="modalMaestro" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] items-center justify-center p-4">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] p-10 shadow-2xl transition-all scale-95 opacity-0 duration-300" id="modalMaestroContent">
            <h3 id="modalTitulo" class="text-2xl font-extrabold text-slate-800 mb-6 italic uppercase tracking-tighter">Formulario</h3>
            <form method="POST" class="space-y-4">
                <?= lm_csrf_field() ?>
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id_interno_hidden" id="formIdInternoHidden">
                
                <div id="camposDinamicos" class="max-h-[55vh] overflow-y-auto pr-3 custom-scrollbar">
                    </div>

                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="cerrarModal()" class="flex-1 bg-slate-100 p-4 rounded-2xl font-black text-[10px] uppercase text-slate-500 hover:bg-slate-200 transition-all">Cancelar</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white p-4 rounded-2xl font-black text-[10px] uppercase shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">Guardar Registro</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEliminar" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[120] items-center justify-center p-4">
        <div class="bg-white w-full max-w-sm rounded-[2.5rem] p-10 shadow-2xl text-center">
            <h3 class="text-xl font-bold text-slate-800 mb-2 uppercase italic">Eliminar</h3>
            <p class="text-[10px] text-slate-400 font-bold mb-6 uppercase"><?= $empleado_cedula_como_pk ? 'Ingrese la cédula (ID empleado) para confirmar' : 'Ingresa el valor de la clave (p. ej. ID interno) para confirmar' ?></p>
            <form method="POST">
                <?= lm_csrf_field() ?>
                <input type="hidden" name="action" value="eliminar_manual">
                <input type="<?= $empleado_cedula_como_pk ? 'text' : 'number' ?>" name="id_a_borrar" required placeholder="<?= $empleado_cedula_como_pk ? 'Cédula' : 'ID' ?>" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl mb-6 text-center font-black <?= $empleado_cedula_como_pk ? 'text-lg' : 'text-3xl' ?> outline-none focus:ring-4 focus:ring-red-500/10 transition-all">
                <div class="flex gap-3">
                    <button type="button" onclick="this.closest('.hidden').classList.remove('modal-active')" class="flex-1 bg-slate-100 p-4 rounded-2xl font-black text-[10px] uppercase">Cerrar</button>
                    <button type="submit" class="flex-1 bg-red-500 text-white p-4 rounded-2xl font-black text-[10px] uppercase shadow-lg shadow-red-200">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const nombreColIDNegocio = <?= json_encode($columna_id_maestro) ?>;
        const proximoID = <?= json_encode($siguiente_id_valor) ?>;
        const proximoIdInterno = <?= json_encode($siguiente_id_interno) ?>;
        const columnasLista = <?= json_encode($columnas_vista) ?>;
        const columnaPk = <?= json_encode($columna_pk) ?>;
        const empleadoCedulaComoPk = <?= $empleado_cedula_como_pk ? 'true' : 'false' ?>;
        const esTablaEmpleado = <?= $es_tabla_empleado ? 'true' : 'false' ?>;
        const empleadoTienePuestoTrabajo = <?= $empleadoTienePuestoTrabajo ? 'true' : 'false' ?>;
        const puestosTrabajoEmpleado = <?= json_encode(['Visceras', 'Subproductos', 'Canales', 'Pieles', 'Desposte'], JSON_UNESCAPED_UNICODE) ?>;
        const esTablaUser = <?= $es_tabla_user ? 'true' : 'false' ?>;
        const esTablaOpl = <?= strtolower($tabla_get) === 'opl' ? 'true' : 'false' ?>;
        const opcionesRelacionOpl = <?= json_encode($opcionesRelacionOpl, JSON_UNESCAPED_UNICODE) ?>;
        const relacionesDetectadasOpl = <?= json_encode($relacionesDetectadasOpl, JSON_UNESCAPED_UNICODE) ?>;
        const rolesDisponibles = ['Super Admin', 'Administrador', 'Operativo'];
        const estadosDisponibles = ['ACTIVO', 'INACTIVO'];
        const accionesDisponibles = ['TODAS', 'CONSULTAR', 'CREAR', 'EDITAR', 'ELIMINAR'];

        function crearBloqueRelacionOpl(vinculoConductor, vinculoVehiculo, modo) {
            if (!esTablaOpl) return;
            const contenedor = document.getElementById('camposDinamicos');
            if (!contenedor) return;

            const card = document.createElement('div');
            card.className = 'mt-5 p-4 rounded-2xl border border-emerald-100 bg-emerald-50/50';

            const titulo = document.createElement('p');
            titulo.className = 'text-[10px] font-black text-emerald-700 uppercase tracking-widest mb-2';
            titulo.textContent = 'Relación OPL';
            card.appendChild(titulo);

            const hint = document.createElement('p');
            hint.className = 'text-[10px] font-semibold text-emerald-700 mb-3';
            hint.textContent = modo === 'editar'
                ? 'Puede ajustar conductor y vehículo para guardar la relación de esta OPL.'
                : 'Opcional: seleccione conductor y vehículo para guardarlos relacionados con esta OPL.';
            card.appendChild(hint);

            const mkSelect = function (name, labelText, rows, idKey, txtKey, seleccionado) {
                const label = document.createElement('label');
                label.className = 'block text-[9px] font-black text-slate-400 uppercase ml-1 mb-1 tracking-widest';
                label.textContent = labelText;
                const sel = document.createElement('select');
                sel.name = name;
                sel.className = 'w-full p-3 border border-slate-100 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all bg-white mb-3';
                const first = document.createElement('option');
                first.value = '';
                first.textContent = '— Opcional —';
                sel.appendChild(first);
                (rows || []).forEach(function (r) {
                    const id = String(r[idKey] ?? '').trim();
                    if (!id) return;
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = String(r[txtKey] ?? id);
                    if (String(seleccionado || '') === id) {
                        opt.selected = true;
                    }
                    sel.appendChild(opt);
                });
                card.appendChild(label);
                card.appendChild(sel);
            };

            mkSelect('vinculo_conductor', 'Conductor', opcionesRelacionOpl.conductores, 'ID_Conductor', 'Conductor', vinculoConductor);
            mkSelect('vinculo_vehiculo', 'Vehículo', opcionesRelacionOpl.vehiculos, 'ID_Vehiculo', 'Vehiculo', vinculoVehiculo);
            contenedor.appendChild(card);
        }

        function agregarRelacionOplEnCrear() {
            crearBloqueRelacionOpl('', '', 'crear');
        }

        function agregarRelacionOplEnEdicion(datosFila) {
            if (!esTablaOpl) return;
            const idOpl = String(datosFila[nombreColIDNegocio] || '').trim();
            const bucket = idOpl && relacionesDetectadasOpl[idOpl] ? relacionesDetectadasOpl[idOpl] : null;
            const conductorActual = bucket && Array.isArray(bucket.c) && bucket.c.length ? String(bucket.c[0]) : '';
            const vehiculoActual = bucket && Array.isArray(bucket.v) && bucket.v.length ? String(bucket.v[0]) : '';
            crearBloqueRelacionOpl(conductorActual, vehiculoActual, 'editar');
        }

        function abrirModalCrear() {
            document.getElementById('modalTitulo').innerText = "Nuevo Registro";
            document.getElementById('formAction').value = "crear";
            document.getElementById('formIdInternoHidden').value = "";
            
            const datosLimpios = {};
            if (proximoIdInterno !== null && proximoIdInterno !== undefined) {
                const colInt = columnasLista.find(c => String(c).toLowerCase() === 'id_interno');
                if (colInt) datosLimpios[colInt] = String(proximoIdInterno);
            }
            if (nombreColIDNegocio) {
                datosLimpios[nombreColIDNegocio] = empleadoCedulaComoPk ? '' : proximoID;
            }
            
            renderizarCampos(datosLimpios, false);
            agregarRelacionOplEnCrear();
            mostrarModal();
        }

        function prepararEdicion(datosFila) {
            document.getElementById('modalTitulo').innerText = "Editar Registro";
            document.getElementById('formAction').value = "editar";
            const pk = columnaPk || 'id_interno';
            const idInt = datosFila[pk] ?? datosFila[pk.toLowerCase()] ?? datosFila.ID_INTERNO ?? datosFila.id_interno;
            document.getElementById('formIdInternoHidden').value = idInt;
            
            renderizarCampos(datosFila, true);
            agregarRelacionOplEnEdicion(datosFila);
            mostrarModal();
        }

        function renderizarCampos(datosActuales, esEdicion) {
            const contenedor = document.getElementById('camposDinamicos');
            contenedor.innerHTML = "";

            const opcionesPorColumnaUser = {
                rol: rolesDisponibles,
                estado: estadosDisponibles,
                activo: estadosDisponibles,
                acciones: accionesDisponibles,
                accion: accionesDisponibles,
            };
            
            columnasLista.forEach(col => {
                const label = document.createElement('label');
                label.className = "block text-[9px] font-black text-slate-400 uppercase ml-2 mb-1 mt-4 tracking-widest";
                label.innerText = col.replace('_', ' ');
                
                let field = null;
                const colLower = col.toLowerCase();
                if (esTablaEmpleado && colLower === 'activo') {
                    label.innerText = 'Estado (Activo / Inactivo)';
                    const select = document.createElement('select');
                    select.name = col;
                    select.className = "w-full p-3 border border-slate-100 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500/20 transition-all bg-white";
                    const valorActual = String(datosActuales[col] || '').trim();
                    let vNorm = valorActual.toUpperCase();
                    if (vNorm === 'ACTIVO' || vNorm === 'TRUE' || vNorm === '1') vNorm = 'SI';
                    if (vNorm === 'INACTIVO' || vNorm === 'FALSE' || vNorm === '0') vNorm = 'NO';
                    const pares = [['SI', 'ACTIVO'], ['NO', 'INACTIVO']];
                    pares.forEach(function (pair) {
                        const opt = document.createElement('option');
                        opt.value = pair[0];
                        opt.textContent = pair[1];
                        if (pair[0] === vNorm || (!esEdicion && pair[0] === 'SI' && vNorm === '')) {
                            opt.selected = true;
                        }
                        select.appendChild(opt);
                    });
                    field = select;
                } else if (esTablaEmpleado && empleadoTienePuestoTrabajo && colLower === 'puesto_trabajo') {
                    label.innerText = 'Puesto de trabajo';
                    const select = document.createElement('select');
                    select.name = col;
                    select.className = "w-full p-3 border border-slate-100 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500/20 transition-all bg-white";
                    const first = document.createElement('option');
                    first.value = '';
                    first.textContent = '— Elegir —';
                    select.appendChild(first);
                    const valPt = String(datosActuales[col] || '').trim();
                    (puestosTrabajoEmpleado || []).forEach(function (nom) {
                        const opt = document.createElement('option');
                        opt.value = nom;
                        opt.textContent = nom;
                        if (valPt !== '' && valPt === nom) {
                            opt.selected = true;
                        }
                        select.appendChild(opt);
                    });
                    if (valPt !== '' && !(puestosTrabajoEmpleado || []).includes(valPt)) {
                        const optX = document.createElement('option');
                        optX.value = valPt;
                        optX.textContent = valPt;
                        optX.selected = true;
                        select.appendChild(optX);
                    }
                    field = select;
                } else if (esTablaUser && Object.prototype.hasOwnProperty.call(opcionesPorColumnaUser, colLower)) {
                    const select = document.createElement('select');
                    select.name = col;
                    select.className = "w-full p-3 border border-slate-100 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500/20 transition-all bg-white";
                    const valorActual = (datosActuales[col] || '').trim();
                    let normalizado = valorActual;
                    if (colLower === 'rol' && valorActual.toUpperCase() === 'ADMIN') {
                        normalizado = 'Administrador';
                    } else if ((colLower === 'estado' || colLower === 'activo') && valorActual !== '') {
                        if (valorActual.toUpperCase() === 'SI') normalizado = 'ACTIVO';
                        if (valorActual.toUpperCase() === 'NO') normalizado = 'INACTIVO';
                        if (valorActual.toUpperCase() === 'TRUE') normalizado = 'ACTIVO';
                        if (valorActual.toUpperCase() === 'FALSE') normalizado = 'INACTIVO';
                    }
                    let opciones = opcionesPorColumnaUser[colLower].slice();
                    if (normalizado !== '' && !opciones.includes(normalizado)) {
                        opciones.unshift(normalizado);
                    }
                    const defaultCrear = colLower === 'rol' ? 'Operativo' : (colLower === 'acciones' || colLower === 'accion' ? 'TODAS' : 'ACTIVO');
                    opciones.forEach(rol => {
                        const opt = document.createElement('option');
                        opt.value = rol;
                        opt.textContent = rol;
                        if (rol === normalizado || (!esEdicion && rol === defaultCrear && normalizado === '')) {
                            opt.selected = true;
                        }
                        select.appendChild(opt);
                    });
                    field = select;
                } else {
                    const input = document.createElement('input');
                    input.type = "text";
                    input.name = col;
                    input.value = datosActuales[col] || "";
                    input.className = "w-full p-3 border border-slate-100 rounded-xl text-xs font-bold uppercase outline-none focus:ring-2 focus:ring-blue-500/20 transition-all";
                    field = input;
                }
                
                // --- REGLAS DE BLOQUEO Y ESTILO ---
                if (columnaPk && col.toLowerCase() === String(columnaPk).toLowerCase()) {
                    if (empleadoCedulaComoPk) {
                        if (field.tagName === 'INPUT') {
                            field.placeholder = 'Cédula (ID empleado)';
                            field.readOnly = false;
                            field.removeAttribute('readonly');
                            field.tabIndex = 0;
                        }
                    } else {
                        if (field.tagName === 'INPUT') {
                            field.readOnly = true;
                            field.tabIndex = -1;
                            field.autocomplete = 'off';
                        } else {
                            field.disabled = true;
                        }
                        field.classList.add('id-interno-style');
                    }
                } else if (colLower === 'id_interno' && empleadoCedulaComoPk) {
                    if (field.tagName === 'INPUT') {
                        field.readOnly = true;
                        field.tabIndex = -1;
                        field.autocomplete = 'off';
                    }
                    field.classList.add('id-interno-style');
                } else if (col === nombreColIDNegocio) {
                    if (field.tagName === 'INPUT') {
                        field.readOnly = true;
                        field.tabIndex = -1;
                    } else {
                        field.disabled = true;
                    }
                    field.classList.add('input-locked');
                }

                contenedor.appendChild(label);
                contenedor.appendChild(field);
            });
        }

        function mostrarModal() {
            const m = document.getElementById('modalMaestro');
            const c = document.getElementById('modalMaestroContent');
            m.classList.add('modal-active');
            setTimeout(() => {
                c.classList.remove('scale-95', 'opacity-0');
                c.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function cerrarModal() {
            const m = document.getElementById('modalMaestro');
            const c = document.getElementById('modalMaestroContent');
            c.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { m.classList.remove('modal-active'); }, 200);
        }

        function filterMasterTable() {
            const val = document.getElementById('globalSearch').value.toUpperCase();
            document.querySelectorAll('.row-data').forEach(row => {
                row.style.display = row.innerText.toUpperCase().includes(val) ? '' : 'none';
            });
        }
    </script>
</body>
</html>