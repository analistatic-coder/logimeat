<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';

if (!lm_es_admin()) {
    header('Location: programacion.php?msj=no_autorizado');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: programacion.php');
    exit();
}

if (!lm_csrf_validar($_POST['_csrf'] ?? null)) {
    header('Location: programacion.php?msj=csrf');
    exit();
}

$id = isset($_POST['id_interno']) ? (int) $_POST['id_interno'] : 0;
if ($id < 1) {
    header('Location: programacion.php?msj=eliminar_invalido');
    exit();
}

try {
    $chk = $pdo->prepare('SELECT id_interno FROM Programacion WHERE id_interno = ? LIMIT 1');
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        header('Location: programacion.php?msj=eliminar_no_existe');
        exit();
    }
    $del = $pdo->prepare('DELETE FROM Programacion WHERE id_interno = ?');
    $del->execute([$id]);
    header('Location: programacion.php?msj=eliminado');
    exit();
} catch (Throwable $e) {
    error_log('eliminar_programacion: ' . $e->getMessage());
    header('Location: programacion.php?msj=eliminar_error');
    exit();
}
