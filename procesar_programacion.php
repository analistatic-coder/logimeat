<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/programacion_catalogos.php';
require_once __DIR__ . '/config/programacion_opl_logistica.php';
require_once __DIR__ . '/config/logisticos_vinculo_maestro.php';

/**
 * Busca por nombre o inserta fila mínima (id + nombre) en maestro. Solo tablas/columnas fijas.
 */
function programacion_alta_maestro_id_nombre(PDO $pdo, string $tabla, string $colId, string $colNombre, string $texto): ?string
{
    $tablasPerm = [
        'clientes' => ['id' => 'ID_Cliente', 'nom' => 'Cliente'],
        'solicitante' => ['id' => 'ID_Solicitante', 'nom' => 'Solicitante'],
        'opl' => ['id' => 'ID_OPL', 'nom' => 'OPL'],
        'vehiculo' => ['id' => 'ID_Vehiculo', 'nom' => 'Vehiculo'],
        'conductor' => ['id' => 'ID_Conductor', 'nom' => 'Conductor'],
    ];
    if (!isset($tablasPerm[$tabla]) || $tablasPerm[$tabla]['id'] !== $colId || $tablasPerm[$tabla]['nom'] !== $colNombre) {
        return null;
    }
    $texto = trim($texto);
    if ($texto === '') {
        return null;
    }
    try {
        $q = $pdo->prepare("SELECT `$colId` FROM `$tabla` WHERE UPPER(TRIM(`$colNombre`)) = UPPER(TRIM(?)) LIMIT 1");
        $q->execute([$texto]);
        $ex = $q->fetchColumn();
        if ($ex !== false && $ex !== null && trim((string) $ex) !== '') {
            return trim((string) $ex);
        }
        $max = $pdo->query("SELECT MAX(CAST(`$colId` AS UNSIGNED)) FROM `$tabla` WHERE CAST(`$colId` AS CHAR) REGEXP '^[0-9]+$'")->fetchColumn();
        $nuevoId = ($max !== null && $max !== false && $max !== '') ? (string) ((int) $max + 1) : substr(bin2hex(random_bytes(4)), 0, 8);
        $ins = $pdo->prepare("INSERT INTO `$tabla` (`$colId`, `$colNombre`) VALUES (?, ?)");
        $ins->execute([$nuevoId, $texto]);

        return $nuevoId;
    } catch (Throwable) {
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nueva_programacion.php');
    exit();
}

if (!lm_csrf_validar($_POST['_csrf'] ?? null)) {
    die('Solicitud no válida o sesión de seguridad caducada. Vuelva a abrir «Nueva programación» e intente de nuevo.');
}

$idProg = trim((string) ($_POST['id_programacion_generado'] ?? ''));
if (!programacion_id_programacion_valido($idProg)) {
    die('Identificador de programación inválido o manipulado. Vuelva a cargar «Nueva programación».');
}

try {
    $dup = $pdo->prepare('SELECT 1 FROM Programacion WHERE ID_Programacion = ? LIMIT 1');
    $dup->execute([$idProg]);
    if ($dup->fetchColumn()) {
        die('Ese ID de programación ya existe (los identificadores no pueden repetirse). Abra de nuevo «Nueva programación» para generar otro código único.');
    }
} catch (Throwable) {
}

$plantaOp = trim((string) ($_POST['planta_operativa'] ?? ''));
$producto = trim((string) ($_POST['producto'] ?? ''));
$actividad = trim((string) ($_POST['actividad'] ?? ''));
$cliente = trim((string) ($_POST['cliente'] ?? ''));
$clienteNuevo = trim((string) ($_POST['cliente_nuevo'] ?? ''));
$opl = trim((string) ($_POST['opl'] ?? ''));
$oplNuevo = trim((string) ($_POST['opl_nuevo'] ?? ''));
$destino = trim((string) ($_POST['destino'] ?? ''));
$cantidad = $_POST['cantidad'] ?? '';
$fechaOp = trim((string) ($_POST['fecha_operacion'] ?? ''));
$hora = trim((string) ($_POST['hora'] ?? ''));
$tipoCuarteo = trim((string) ($_POST['tipo_cuarteo'] ?? ''));
$observaciones = trim((string) ($_POST['observaciones'] ?? ''));
$solicitante = trim((string) ($_POST['solicitante'] ?? ''));
$solicitanteNuevo = trim((string) ($_POST['solicitante_nuevo'] ?? ''));
$medioCom = trim((string) ($_POST['medio_comunicacion'] ?? ''));
$estadoPedido = trim((string) ($_POST['estado_pedido'] ?? 'PROGRAMADO'));
$lote = trim((string) ($_POST['lote'] ?? ''));
$ciudadRaw = trim((string) ($_POST['ciudad'] ?? ''));
$ubicacion = trim((string) ($_POST['ubicacion'] ?? ''));
$conductor = trim((string) ($_POST['conductor'] ?? ''));
$conductorNuevo = trim((string) ($_POST['conductor_nuevo'] ?? ''));
$vehiculo = trim((string) ($_POST['vehiculo'] ?? ''));
$vehiculoNuevo = trim((string) ($_POST['vehiculo_nuevo'] ?? ''));
$estadoAct = trim((string) ($_POST['estado_actividad'] ?? 'PROGRAMADO'));
$cantOk = trim((string) ($_POST['cantidad_correcta'] ?? ''));
$prodOk = trim((string) ($_POST['producto_correcto'] ?? ''));
$entregaTiempo = trim((string) ($_POST['entrega_tiempo'] ?? ''));
$dirOk = trim((string) ($_POST['direccion_correcta'] ?? ''));
$pedidoPerf = trim((string) ($_POST['pedido_perfecto'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));

if ($plantaOp === '' || $actividad === '' || $fechaOp === '' || $cantidad === '' || $cantidad === null) {
    die('Faltan datos obligatorios (planta, actividad, fecha de operación o cantidad).');
}

if ($clienteNuevo !== '' && lm_es_admin()) {
    $idC = programacion_alta_maestro_id_nombre($pdo, 'clientes', 'ID_Cliente', 'Cliente', $clienteNuevo);
    if ($idC !== null) {
        $cliente = $idC;
    }
}
if ($solicitanteNuevo !== '' && lm_es_admin()) {
    $idSol = programacion_alta_maestro_id_nombre($pdo, 'solicitante', 'ID_Solicitante', 'Solicitante', $solicitanteNuevo);
    if ($idSol !== null) {
        $solicitante = $idSol;
    }
}
if ($cliente === '') {
    die('Falta el cliente: elija uno del listado o cree uno nuevo (solo administradores).');
}

if ($oplNuevo !== '' && lm_es_admin()) {
    $idO = programacion_alta_maestro_id_nombre($pdo, 'opl', 'ID_OPL', 'OPL', $oplNuevo);
    if ($idO !== null) {
        $opl = $idO;
        unset($_SESSION['lm_opl_rel_cache_v1']);
    }
}

if ($vehiculoNuevo !== '' && lm_es_admin()) {
    $idV = programacion_alta_maestro_id_nombre($pdo, 'vehiculo', 'ID_Vehiculo', 'Vehiculo', $vehiculoNuevo);
    if ($idV !== null) {
        $vehiculo = $idV;
    }
}

if (!programacion_es_producto_valido($plantaOp, $producto)) {
    die('El producto no corresponde a la planta seleccionada.');
}

$tcList = programacion_tipos_cuarteo_por_planta()[$plantaOp] ?? [];
$requiereCuarteo = mb_strtoupper($producto, 'UTF-8') === 'CANALES';
if (!$requiereCuarteo) {
    $tipoCuarteo = '';
}
if ($tipoCuarteo !== '' && $tcList !== [] && !in_array($tipoCuarteo, $tcList, true)) {
    die('Tipo de cuarteo no válido para esta planta.');
}

if ($tcList === [] || !$requiereCuarteo) {
    $tipoCuarteo = '';
}

$idPlantaMaestro = programacion_id_maestro_desde_grupo($plantaOp);
if ($idPlantaMaestro === null) {
    die('Planta operativa no válida.');
}

$fechaReg = date('d/m/Y H:i:s');
$fechaOpFmt = date('d/m/Y', strtotime($fechaOp));

$nullIfEmpty = static function (string $v): ?string {
    return trim($v) === '' ? null : $v;
};

$ciudadVal = null;
if ($ciudadRaw !== '' && is_numeric($ciudadRaw)) {
    $ciudadVal = $ciudadRaw;
}

$estadoPedidoPermitidos = ['PROGRAMADO', 'ADICIONAL'];
try {
    $rowsEstado = $pdo->query('SELECT Estado FROM estado ORDER BY Estado ASC')->fetchAll(PDO::FETCH_COLUMN);
    if (is_array($rowsEstado) && $rowsEstado !== []) {
        $estadoPedidoPermitidos = array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $rowsEstado), static fn (string $v): bool => $v !== ''));
    }
} catch (Throwable) {
}

$estadoActividadPermitidos = ['PROGRAMADO', 'EJECUTADO', 'CANCELADO'];
try {
    $rowsEstadoActividad = $pdo->query('SELECT Estado_Actividad FROM estado_actividad ORDER BY Estado_Actividad ASC')->fetchAll(PDO::FETCH_COLUMN);
    if (is_array($rowsEstadoActividad) && $rowsEstadoActividad !== []) {
        $estadoActividadPermitidos = array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $rowsEstadoActividad), static fn (string $v): bool => $v !== ''));
    }
} catch (Throwable) {
}

$estadoPedido = in_array($estadoPedido, $estadoPedidoPermitidos, true) ? $estadoPedido : 'PROGRAMADO';
$estadoAct = in_array($estadoAct, $estadoActividadPermitidos, true) ? $estadoAct : 'PROGRAMADO';

if ($conductorNuevo !== '' && lm_es_admin()) {
    $idCond = programacion_alta_maestro_id_nombre($pdo, 'conductor', 'ID_Conductor', 'Conductor', $conductorNuevo);
    if ($idCond !== null) {
        $conductor = $idCond;
    }
}

/** Tras resolver conductor/vehículo: vínculo OPL ↔ logisticos (misma lógica que gestion_tabla al crear OPL). */
if (lm_es_admin() && $oplNuevo !== '' && trim($opl) !== '') {
    $oplVincCond = trim((string) ($_POST['opl_vinculo_conductor'] ?? ''));
    $oplVincVeh = trim((string) ($_POST['opl_vinculo_vehiculo'] ?? ''));
    $oplMismaProg = isset($_POST['opl_vinculo_misma_programacion']) && (string) $_POST['opl_vinculo_misma_programacion'] === '1';
    $vc = ($oplVincCond !== '' && $oplVincVeh !== '') ? $oplVincCond : '';
    $vv = ($oplVincCond !== '' && $oplVincVeh !== '') ? $oplVincVeh : '';
    if ($vc === '' && $vv === '' && $oplMismaProg) {
        $vc = trim((string) $conductor);
        $vv = trim((string) $vehiculo);
    }
    if ($vc !== '' && $vv !== '' && logisticos_maestro_insertar_triple($pdo, trim($opl), $vc, $vv)) {
        unset($_SESSION['lm_opl_rel_cache_v1']);
    }
}

$oplsMaestro = [];
try {
    $oplsMaestro = $pdo->query('SELECT ID_OPL, OPL FROM opl ORDER BY OPL ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
$relO = programacion_opl_relaciones($pdo, $oplsMaestro);
if ($relO['usaFiltro'] && trim($opl) !== '' && $conductorNuevo === '' && $vehiculoNuevo === '') {
    $bk = $relO['porOpl'][trim($opl)] ?? null;
    if ($bk !== null && !programacion_opl_permite_conductor_vehiculo($bk, $conductor, $vehiculo)) {
        die('El conductor o el vehículo no corresponden a la OPL elegida. Elija una combinación permitida o deje ambos vacíos.');
    }
}

try {
    $sql = 'INSERT INTO Programacion (
        ID_Programacion, Fecha_de_Registro, Solicitante, Medio_de_Comunicacion, Estado,
        Cliente, Planta, Planta_Operativa, Actividad, Fecha_de_Operacion, Hora,
        Producto, Tipo_de_Cuarteo, Lote, Cantidad, Ciudad, Destino, Ubicacion,
        OPL, Conductor, Vehiculo, Observaciones,
        Cantidad_Correcta, Producto_Correcto, Entrega_a_Tiempo, Direccion_Correcta, Pedido_Perfecto,
        Estado_Actividad, Telefono
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $idProg,
        $fechaReg,
        $nullIfEmpty($solicitante),
        $nullIfEmpty($medioCom),
        $estadoPedido,
        $cliente,
        $idPlantaMaestro,
        $plantaOp,
        $actividad,
        $fechaOpFmt,
        $hora !== '' ? $hora : null,
        $producto,
        $tipoCuarteo !== '' ? $tipoCuarteo : null,
        $nullIfEmpty($lote),
        $cantidad !== '' ? $cantidad : null,
        $ciudadVal,
        $nullIfEmpty($destino),
        $nullIfEmpty($ubicacion),
        $nullIfEmpty($opl),
        $nullIfEmpty($conductor),
        $nullIfEmpty($vehiculo),
        $nullIfEmpty($observaciones),
        $nullIfEmpty($cantOk),
        $nullIfEmpty($prodOk),
        $nullIfEmpty($entregaTiempo),
        $nullIfEmpty($dirOk),
        $nullIfEmpty($pedidoPerf),
        $estadoAct,
        $nullIfEmpty($telefono),
    ]);

    header('Location: programacion.php?status=success');
    exit();
} catch (PDOException $e) {
    $msg = $e->getMessage();
    $code = (string) $e->getCode();
    if ($code === '23000' || str_contains($msg, 'Duplicate') || str_contains($msg, '1062')) {
        die('No se pudo guardar: un identificador ya existía (no se permiten duplicados). Vuelva a «Nueva programación» e intente otra vez.');
    }
    die('Error al guardar: ' . htmlspecialchars($msg));
} catch (Throwable $e) {
    die('Error al guardar: ' . htmlspecialchars($e->getMessage()));
}
