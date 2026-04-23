<?php
require_once 'auth.php';
require_once 'conexion.php';

// 1. SEGURIDAD Y PARÁMETROS DE RUTA
$es_admin = ($_SESSION['rol'] === 'Administrador');
$tabla_get = $_GET['tabla'] ?? 'clientes'; 
$tablas_permitidas = [
    'clientes', 'corte', 'departamento', 'municipio', 'opl', 'producto', 'tipo_de_cuarteo', 'zona', 'vehiculo', 'conductor',
    'user', 'actividad', 'planta', 'logisticos',
    'empleado', 'empleado_descanso', 'empleado_programacion',
];

if (!in_array(strtolower($tabla_get), $tablas_permitidas)) {
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $es_admin) {
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
            
            // Limpiamos datos que no van directo a columnas
            unset($datos_post['action'], $datos_post['id_interno_hidden'], $datos_post['id_interno']);
            
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
            } else {
                if ($columna_pk && isset($datos_post[$columna_pk])) {
                    unset($datos_post[$columna_pk]);
                }
                $columnas_sql = array_keys($datos_post);
                $set_query = implode('=?, ', $columnas_sql) . '=?';
                $colPk = str_replace('`', '``', $columna_pk);
                $sql = "UPDATE `$tabla_get` SET $set_query WHERE `$colPk` = ?";
                $params = array_values($datos_post);
                $params[] = $id_interno_referencia;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
        }
        header("Location: gestion_tabla.php?tabla=$tabla_get&msg=success");
        exit();
    } catch (PDOException $e) { $error_msg = "Error: " . $e->getMessage(); }
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Maestros | <?= $titulo_modulo ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
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
            
            <header class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-extrabold text-slate-800 tracking-tighter italic uppercase"><?= $titulo_modulo ?></h2>
                <?php if($es_admin): ?>
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
            mostrarModal();
        }

        function prepararEdicion(datosFila) {
            document.getElementById('modalTitulo').innerText = "Editar Registro";
            document.getElementById('formAction').value = "editar";
            const pk = columnaPk || 'id_interno';
            const idInt = datosFila[pk] ?? datosFila[pk.toLowerCase()] ?? datosFila.ID_INTERNO ?? datosFila.id_interno;
            document.getElementById('formIdInternoHidden').value = idInt;
            
            renderizarCampos(datosFila, true);
            mostrarModal();
        }

        function renderizarCampos(datosActuales, esEdicion) {
            const contenedor = document.getElementById('camposDinamicos');
            contenedor.innerHTML = "";
            
            columnasLista.forEach(col => {
                const label = document.createElement('label');
                label.className = "block text-[9px] font-black text-slate-400 uppercase ml-2 mb-1 mt-4 tracking-widest";
                label.innerText = col.replace('_', ' ');
                
                const input = document.createElement('input');
                input.type = "text";
                input.name = col;
                input.value = datosActuales[col] || "";
                input.className = "w-full p-3 border border-slate-100 rounded-xl text-xs font-bold uppercase outline-none focus:ring-2 focus:ring-blue-500/20 transition-all";
                
                // --- REGLAS DE BLOQUEO Y ESTILO ---
                if (columnaPk && col.toLowerCase() === String(columnaPk).toLowerCase()) {
                    if (empleadoCedulaComoPk && !esEdicion) {
                        input.placeholder = 'Cédula (ID empleado)';
                    } else {
                        input.readOnly = true;
                        input.tabIndex = -1;
                        input.autocomplete = 'off';
                        input.classList.add('id-interno-style');
                    }
                } else if (col === nombreColIDNegocio) {
                    input.readOnly = true;
                    input.tabIndex = -1;
                    input.classList.add('input-locked');
                }

                contenedor.appendChild(label);
                contenedor.appendChild(input);
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