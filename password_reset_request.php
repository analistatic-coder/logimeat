<?php
declare(strict_types=1);

require_once __DIR__ . '/config/seguridad.php';
lm_seguridad_session_before_start();
session_start();

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config/lm_assets.php';
require_once __DIR__ . '/config/password_reset_helpers.php';
require_once __DIR__ . '/config/mail_send.php';

lm_password_reset_ensure_schema($pdo);

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lm_csrf_validar($_POST['_csrf'] ?? null)) {
        $error = 'Sesión de formulario caducada. Intente de nuevo.';
    } else {
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        if ($usuario === '') {
            $error = 'Indique el nombre de usuario.';
        } else {
            $stmt = $pdo->prepare('SELECT `ID_User`, `Nombre`, `Email` FROM `User` WHERE `Nombre` = ? LIMIT 1');
            $stmt->execute([$usuario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $mensajeGenerico = 'Si el usuario existe y tiene un correo registrado, le enviaremos un enlace para restablecer la contraseña.';
            if (!$row || trim((string) ($row['Email'] ?? '')) === '') {
                $mensaje = $mensajeGenerico;
            } else {
                $userId = (string) $row['ID_User'];
                $email = trim((string) $row['Email']);
                $nombre = (string) ($row['Nombre'] ?? $usuario);

                $token = bin2hex(random_bytes(32));
                $expiresAt = (new DateTimeImmutable('+45 minutes'))->format('Y-m-d H:i:s');

                $pdo->prepare('UPDATE `password_reset` SET `used` = 1 WHERE `user_id` = ? AND `used` = 0')->execute([$userId]);
                $ins = $pdo->prepare('INSERT INTO `password_reset` (`user_id`, `token`, `expires_at`, `used`) VALUES (?, ?, ?, 0)');
                $ins->execute([$userId, $token, $expiresAt]);

                $link = lm_public_page_url('password_reset_confirm.php?token=' . urlencode($token));
                $subject = 'LogiMeat: restablecer contraseña';
                $body = "Hola {$nombre},\r\n\r\n"
                    . "Se solicitó restablecer la contraseña de LogiMeat.\r\n\r\n"
                    . "Abra este enlace en el navegador (caduca en 45 minutos):\r\n"
                    . "{$link}\r\n\r\n"
                    . "Si usted no lo solicitó, ignore este mensaje.\r\n";

                $sent = lm_mail_send_app($email, $subject, $body);
                if (!$sent['ok']) {
                    error_log('LogiMeat password reset mail error: ' . ($sent['error'] ?? ''));
                }

                $mensaje = $mensajeGenerico;
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
    <title>LogiMeat | Restablecer contraseña</title>
    <?php lm_head_local_assets(); ?>
</head>
<body class="bg-slate-900 flex flex-col items-center justify-center min-h-screen font-['Plus_Jakarta_Sans'] p-4">
    <div class="bg-white p-10 rounded-[2.5rem] shadow-2xl w-full max-w-md border border-slate-100">
        <h1 class="text-2xl font-extrabold text-slate-800 mb-2">Restablecer contraseña</h1>
        <p class="text-slate-400 text-[10px] uppercase font-black tracking-widest mb-6">Recibirá un enlace en el correo asociado al usuario</p>

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

        <form method="POST" class="space-y-5">
            <?= lm_csrf_field() ?>
            <input type="text" name="usuario" placeholder="USUARIO" required
                   class="w-full p-4 bg-slate-50 border rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 uppercase text-sm font-bold">
            <button type="submit"
                    class="w-full bg-blue-600 text-white p-5 rounded-2xl font-bold hover:bg-blue-700 transition-all uppercase text-xs tracking-widest">
                Enviar enlace
            </button>
        </form>

        <p class="mt-6 text-center">
            <a href="login.php" class="text-[11px] text-blue-600 font-bold hover:underline">Volver al inicio de sesión</a>
        </p>
    </div>
</body>
</html>
