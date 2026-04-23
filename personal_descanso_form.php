<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/personal_helpers.php';

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    header('Location: index.php');
    exit();
}

$idInterno = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$retAnio = isset($_GET['anio']) ? max(2020, min(2035, (int) $_GET['anio'])) : 2026;
$retSem = isset($_GET['semana']) ? max(1, min(53, (int) $_GET['semana'])) : 16;

$error = '';

$row = [
    'ID_Empleado' => '',
    'Tipo' => '',
    'Fecha_Inicio' => '',
    'Fecha_Fin' => '',
    'Observaciones' => '',
    'ID_Descanso' => '',
];

if ($idInterno > 0) {
    $st = $pdo->prepare('SELECT * FROM empleado_descanso WHERE id_interno = ?');
    $st->execute([$idInterno]);
    $ex = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ex) {
        header('Location: tablero_descansos.php?anio=' . $retAnio . '&semana=' . $retSem);
        exit();
    }
    $row = array_merge($row, $ex);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'eliminar' && $idInterno > 0) {
        $pdo->prepare('DELETE FROM empleado_descanso WHERE id_interno = ?')->execute([$idInterno]);
        header('Location: tablero_descansos.php?anio=' . $retAnio . '&semana=' . $retSem . '&msg=desc_ok');
        exit();
    }

    if ($action === 'refrescar') {
        $row['ID_Empleado'] = trim((string) ($_POST['ID_Empleado'] ?? ''));
        $row['Tipo'] = trim((string) ($_POST['Tipo'] ?? ''));
        $row['Fecha_Inicio'] = trim((string) ($_POST['Fecha_Inicio'] ?? ''));
        $row['Fecha_Fin'] = trim((string) ($_POST['Fecha_Fin'] ?? ''));
        $row['Observaciones'] = trim((string) ($_POST['Observaciones'] ?? ''));
    } elseif ($action === 'guardar') {
        $row['ID_Empleado'] = trim((string) ($_POST['ID_Empleado'] ?? ''));
        $row['Tipo'] = trim((string) ($_POST['Tipo'] ?? ''));
        $row['Fecha_Inicio'] = trim((string) ($_POST['Fecha_Inicio'] ?? ''));
        $row['Fecha_Fin'] = trim((string) ($_POST['Fecha_Fin'] ?? ''));
        $row['Observaciones'] = trim((string) ($_POST['Observaciones'] ?? ''));

        $rg = lm_rango_descanso($row['Fecha_Inicio'], $row['Fecha_Fin']);
        if ($rg === null) {
            $error = 'Indique Fecha_Inicio válida (d/m/AAAA).';
        } elseif ($row['ID_Empleado'] === '') {
            $error = 'Seleccione un empleado.';
        } else {
            [$ini, $fin] = $rg;
            $errVal = lm_validar_rango_descanso_libre($pdo, $row['ID_Empleado'], $ini, $fin, $idInterno > 0 ? $idInterno : null);
            if ($errVal !== null) {
                $error = $errVal;
            } else {
                [$y, $w] = lm_anio_semana_iso($ini);
                $idDesc = trim((string) ($row['ID_Descanso'] ?? ''));
                if ($idDesc === '') {
                    $idDesc = substr(bin2hex(random_bytes(4)), 0, 8);
                }

                if ($idInterno > 0) {
                    $pdo->prepare('UPDATE empleado_descanso SET ID_Empleado=?, Tipo=?, Fecha_Inicio=?, Fecha_Fin=?, Observaciones=?, Anio=?, Numero_Semana=? WHERE id_interno=?')
                        ->execute([$row['ID_Empleado'], $row['Tipo'] ?: null, $row['Fecha_Inicio'], $row['Fecha_Fin'] ?: null, $row['Observaciones'] ?: null, $y, $w, $idInterno]);
                } else {
                    $pdo->prepare('INSERT INTO empleado_descanso (ID_Descanso,ID_Empleado,Tipo,Fecha_Inicio,Fecha_Fin,Observaciones,Anio,Numero_Semana) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$idDesc, $row['ID_Empleado'], $row['Tipo'] ?: null, $row['Fecha_Inicio'], $row['Fecha_Fin'] ?: null, $row['Observaciones'] ?: null, $y, $w]);
                }
                header('Location: tablero_descansos.php?anio=' . $retAnio . '&semana=' . $retSem . '&msg=desc_ok');
                exit();
            }
        }
    }
}

$rgPreview = lm_rango_descanso($row['Fecha_Inicio'], $row['Fecha_Fin']);
$iniPrev = $rgPreview ? $rgPreview[0] : null;
$finPrev = $rgPreview ? $rgPreview[1] : null;

if ($iniPrev && $finPrev) {
    $disponibles = lm_empleados_disponibles_descanso($pdo, $iniPrev, $finPrev, $idInterno > 0 ? $idInterno : null, $row['ID_Empleado'] !== '' ? $row['ID_Empleado'] : null);
} else {
    $q = $pdo->query("SELECT ID_Empleado, Nombre_Completo FROM empleado ORDER BY Nombre_Completo");
    $disponibles = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Descanso / ausencia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex min-h-screen">
    <?php mostrarSidebar('tablero'); ?>
    <div class="flex-1 flex flex-col ml-64 min-h-screen p-8">
        <header class="mb-8">
            <a href="tablero_descansos.php?anio=<?= (int) $retAnio ?>&semana=<?= (int) $retSem ?>" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline">← Tablero personal</a>
            <h1 class="text-2xl font-black text-slate-800 mt-2"><?= $idInterno ? 'Editar descanso' : 'Nuevo descanso o ausencia' ?></h1>
            <p class="text-slate-500 text-sm mt-1">Si el empleado ya tiene turno o descanso en algún día del rango, no podrá guardarse. Use «Actualizar lista» tras cambiar las fechas.</p>
        </header>

        <?php if ($error): ?>
            <p class="mb-4 text-sm font-bold text-red-600"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-8 max-w-xl space-y-4">
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
                <?php if (!$iniPrev): ?>
                    <p class="text-[10px] text-amber-600 font-bold mt-1">Indique fechas válidas y pulse «Actualizar lista» para ver solo empleados libres en ese rango.</p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Tipo</label>
                <input type="text" name="Tipo" value="<?= htmlspecialchars((string) $row['Tipo']) ?>" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold" placeholder="Ej. Vacaciones, incapacidad">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Desde (d/m/AAAA)</label>
                    <input type="text" name="Fecha_Inicio" value="<?= htmlspecialchars((string) $row['Fecha_Inicio']) ?>" required class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Hasta (d/m/AAAA)</label>
                    <input type="text" name="Fecha_Fin" value="<?= htmlspecialchars((string) $row['Fecha_Fin']) ?>" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-bold" placeholder="Opcional; si vacío = un día">
                </div>
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
        <?php mostrarFooter(); ?>
    </div>
</body>
</html>
