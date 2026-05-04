<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/programacion_catalogos.php';
require_once __DIR__ . '/config/programacion_opl_logistica.php';

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
$opl = trim((string) ($_POST['opl'] ?? ''));
$destino = trim((string) ($_POST['destino'] ?? ''));
$cantidad = $_POST['cantidad'] ?? '';
$fechaOp = trim((string) ($_POST['fecha_operacion'] ?? ''));
$hora = trim((string) ($_POST['hora'] ?? ''));
$tipoCuarteo = trim((string) ($_POST['tipo_cuarteo'] ?? ''));
$observaciones = trim((string) ($_POST['observaciones'] ?? ''));
$solicitante = trim((string) ($_POST['solicitante'] ?? ''));
$medioCom = trim((string) ($_POST['medio_comunicacion'] ?? ''));
$estadoPedido = trim((string) ($_POST['estado_pedido'] ?? 'PROGRAMADO'));
$lote = trim((string) ($_POST['lote'] ?? ''));
$ciudadRaw = trim((string) ($_POST['ciudad'] ?? ''));
$ubicacion = trim((string) ($_POST['ubicacion'] ?? ''));
$conductor = trim((string) ($_POST['conductor'] ?? ''));
$conductorNuevo = trim((string) ($_POST['conductor_nuevo'] ?? ''));
$vehiculo = trim((string) ($_POST['vehiculo'] ?? ''));
$estadoAct = trim((string) ($_POST['estado_actividad'] ?? 'PROGRAMADO'));
$cantOk = trim((string) ($_POST['cantidad_correcta'] ?? ''));
$prodOk = trim((string) ($_POST['producto_correcto'] ?? ''));
$entregaTiempo = trim((string) ($_POST['entrega_tiempo'] ?? ''));
$dirOk = trim((string) ($_POST['direccion_correcta'] ?? ''));
$pedidoPerf = trim((string) ($_POST['pedido_perfecto'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));

if ($plantaOp === '' || $cliente === '' || $actividad === '' || $fechaOp === '' || $cantidad === '' || $cantidad === null) {
    die('Faltan datos obligatorios (planta, cliente, actividad, fecha de operación o cantidad).');
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
    try {
        $buscarExistente = $pdo->prepare('SELECT ID_Conductor FROM conductor WHERE UPPER(TRIM(Conductor)) = UPPER(TRIM(?)) LIMIT 1');
        $buscarExistente->execute([$conductorNuevo]);
        $idExistente = $buscarExistente->fetchColumn();
        if ($idExistente !== false && $idExistente !== null && trim((string) $idExistente) !== '') {
            $conductor = trim((string) $idExistente);
        } else {
            $max = $pdo->query("SELECT MAX(CAST(ID_Conductor AS UNSIGNED)) FROM conductor WHERE ID_Conductor REGEXP '^[0-9]+$'")->fetchColumn();
            $nuevoId = ($max !== null && $max !== false && $max !== '') ? (string) ((int) $max + 1) : substr(bin2hex(random_bytes(4)), 0, 8);
            $insertConductor = $pdo->prepare('INSERT INTO conductor (ID_Conductor, Conductor) VALUES (?, ?)');
            $insertConductor->execute([$nuevoId, $conductorNuevo]);
            $conductor = $nuevoId;
        }
    } catch (Throwable) {
        // Si falla alta de conductor, conserva el valor elegido y sigue el flujo normal.
    }
}

$oplsMaestro = [];
try {
    $oplsMaestro = $pdo->query('SELECT ID_OPL, OPL FROM opl ORDER BY OPL ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
}
$relO = programacion_opl_relaciones($pdo, $oplsMaestro);
if ($relO['usaFiltro'] && trim($opl) !== '' && $conductorNuevo === '') {
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
