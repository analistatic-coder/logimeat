<?php
require_once 'auth.php';
require_once 'conexion.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = $_POST['clave_actual'] ?? '';
    $nueva = $_POST['clave_nueva'] ?? '';
    $nueva2 = $_POST['clave_nueva2'] ?? '';
    $idUser = $_SESSION['user_id'] ?? '';

    if ($idUser === '') {
        $error = 'Sesión inválida.';
    } elseif ($nueva === '' || strlen($nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($nueva !== $nueva2) {
        $error = 'La confirmación no coincide con la nueva contraseña.';
    } elseif ($nueva === $actual) {
        $error = 'La nueva contraseña debe ser distinta a la actual.';
    } else {
        $stmt = $pdo->prepare('SELECT Clave FROM User WHERE ID_User = ?');
        $stmt->execute([$idUser]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['Clave'] ?? '') !== $actual) {
            $error = 'La contraseña actual no es correcta.';
        } else {
            $upd = $pdo->prepare('UPDATE User SET Clave = ? WHERE ID_User = ?');
            $upd->execute([$nueva, $idUser]);
            $mensaje = 'Contraseña actualizada correctamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Cambiar contraseña</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; } </style>
</head>
<body class="flex min-h-screen">

    <?php mostrarSidebar('pwd'); ?>

    <div class="flex-1 flex flex-col ml-64 min-h-screen">
        <main class="p-10 flex-grow max-w-lg">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Cambiar contraseña</h1>
                <p class="text-slate-500 text-sm mt-1">Actualice su contraseña de acceso al sistema.</p>
            </header>

            <?php if ($mensaje): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 text-emerald-800 text-sm font-bold border border-emerald-100 text-center">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-2xl bg-red-50 text-red-700 text-sm font-bold border border-red-100 text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="bg-white p-8 rounded-[2rem] shadow-xl border border-slate-100 space-y-5">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Contraseña actual</label>
                    <input type="password" name="clave_actual" required autocomplete="current-password"
                           class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Nueva contraseña</label>
                    <input type="password" name="clave_nueva" required minlength="6" autocomplete="new-password"
                           class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Confirmar nueva contraseña</label>
                    <input type="password" name="clave_nueva2" required minlength="6" autocomplete="new-password"
                           class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 text-sm font-bold">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white p-4 rounded-2xl font-bold text-sm tracking-wide hover:bg-blue-700 transition-all shadow-lg shadow-blue-200">
                    Guardar nueva contraseña
                </button>
            </form>
        </main>
        <?php mostrarFooter(); ?>
    </div>
</body>
</html>
