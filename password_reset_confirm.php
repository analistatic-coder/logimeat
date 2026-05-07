<?php
declare(strict_types=1);

require_once __DIR__ . '/config/seguridad.php';
lm_seguridad_session_before_start();
session_start();

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config/lm_assets.php';
require_once __DIR__ . '/config/password_reset_helpers.php';

lm_password_reset_ensure_schema($pdo);

$rawToken = trim((string) ($_POST['reset_token'] ?? $_GET['token'] ?? ''));
$error = '';
$mensaje = '';
$valido = false;
$userId = null;
$tokenActivo = '';

if ($rawToken !== '' && preg_match('/^[a-f0-9]{64}$/', $rawToken) === 1) {
    $stmt = $pdo->prepare('SELECT * FROM `password_reset` WHERE `token` = ? AND `used` = 0 AND `expires_at` >= NOW() LIMIT 1');
    $stmt->execute([$rawToken]);
    $rowTok = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($rowTok) {
        $valido = true;
        $userId = (string) $rowTok['user_id'];
        $tokenActivo = $rawToken;
    } else {
        $error = 'El enlace no es válido o ha caducado. Solicite uno nuevo desde el inicio de sesión.';
    }
} elseif ($rawToken !== '') {
    $error = 'Enlace no válido.';
}

if ($valido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lm_csrf_validar($_POST['_csrf'] ?? null)) {
        $error = 'Sesión de formulario caducada. Intente de nuevo.';
    } else {
        $postedToken = trim((string) ($_POST['reset_token'] ?? ''));
        if ($postedToken !== $tokenActivo) {
            $error = 'Token no coincide. Use el enlace del correo o solicite uno nuevo.';
            $valido = false;
        } else {
            $pass1 = (string) ($_POST['clave_nueva'] ?? '');
            $pass2 = (string) ($_POST['clave_nueva2'] ?? '');
            if ($pass1 === '' || strlen($pass1) < 6) {
                $error = 'La contraseña debe tener al menos 6 caracteres.';
            } elseif ($pass1 !== $pass2) {
                $error = 'La confirmación no coincide.';
            } elseif ($userId !== null) {
                try {
                    $pdo->beginTransaction();
                    $upd = $pdo->prepare('UPDATE `User` SET `Clave` = ? WHERE `ID_User` = ?');
                    $upd->execute([$pass1, $userId]);
                    $updTok = $pdo->prepare('UPDATE `password_reset` SET `used` = 1 WHERE `token` = ?');
                    $updTok->execute([$tokenActivo]);
                    $pdo->commit();
                    header('Location: login.php?msg=pass_reset_ok');
                    exit();
                } catch (Throwable) {
                    $pdo->rollBack();
                    $error = 'No se pudo actualizar la contraseña. Intente nuevamente.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiMeat | Nueva contraseña</title>
    <?php lm_head_local_assets(); ?>
</head>
<body class="bg-slate-900 flex flex-col items-center justify-center min-h-screen font-['Plus_Jakarta_Sans'] p-4">
    <div class="bg-white p-10 rounded-[2.5rem] shadow-2xl w-full max-w-md border border-slate-100">
        <h1 class="text-2xl font-extrabold text-slate-800 mb-2">Nueva contraseña</h1>

        <?php if ($mensaje !== ''): ?>
            <div class="bg-emerald-50 text-emerald-800 p-4 rounded-2xl text-[11px] font-bold mb-6 border border-emerald-100">
                <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-[11px] font-bold mb-6 border border-red-100">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($valido): ?>
            <form method="POST" class="space-y-5">
                <?= lm_csrf_field() ?>
                <input type="hidden" name="reset_token" value="<?= htmlspecialchars($tokenActivo, ENT_QUOTES, 'UTF-8') ?>">
                <input type="password" name="clave_nueva" placeholder="Nueva contraseña" required minlength="6"
                       class="w-full p-4 bg-slate-50 border rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 text-sm font-bold">
                <input type="password" name="clave_nueva2" placeholder="Confirmar contraseña" required minlength="6"
                       class="w-full p-4 bg-slate-50 border rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 text-sm font-bold">
                <button type="submit"
                        class="w-full bg-blue-600 text-white p-5 rounded-2xl font-bold hover:bg-blue-700 transition-all uppercase text-xs tracking-widest">
                    Guardar y continuar
                </button>
            </form>
        <?php else: ?>
            <p class="text-center">
                <a href="password_reset_request.php" class="text-[11px] text-blue-600 font-bold hover:underline">Solicitar nuevo enlace</a>
            </p>
        <?php endif; ?>

        <p class="mt-6 text-center">
            <a href="login.php" class="text-[11px] text-slate-500 font-bold hover:underline">Volver al inicio de sesión</a>
        </p>
    </div>
</body>
</html>
