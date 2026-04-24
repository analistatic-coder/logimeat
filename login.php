<?php
session_start();
require_once 'conexion.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php?msg=off");
    exit();
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['usuario'];
    $pass = $_POST['clave'];
    $stmt = $pdo->prepare("SELECT * FROM User WHERE Nombre = ? AND Clave = ?");
    $stmt->execute([$user, $pass]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $_SESSION['user_id'] = $usuario['ID_User'];
        $_SESSION['nombre'] = $usuario['Nombre'];
        $rolRaw = trim((string) ($usuario['Rol'] ?? ''));
        $rolU = strtoupper($rolRaw);
        $nombreU = strtoupper(trim((string) ($usuario['Nombre'] ?? '')));
        if ($nombreU === 'ANALISTA TIC') {
            $_SESSION['rol'] = 'Super Admin';
        } elseif ($rolU === 'ADMIN' || $rolU === 'ADMINISTRADOR') {
            $_SESSION['rol'] = 'Administrador';
        } elseif ($rolU === 'AUXILIAR' || $rolU === 'OPERATIVO') {
            $_SESSION['rol'] = 'Operativo';
        } else {
            $_SESSION['rol'] = $rolRaw !== '' ? $rolRaw : 'Operativo';
        }
        $_SESSION['ultima_actividad'] = time(); 
        header("Location: index.php");
        exit();
    } else {
        $error = "Acceso denegado. Verifique sus credenciales.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>LogiMeat | Acceso</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-slate-900 flex flex-col items-center justify-center min-h-screen font-['Plus_Jakarta_Sans']">
    <div class="bg-white p-10 rounded-[2.5rem] shadow-2xl w-full max-w-md border border-slate-100">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold italic text-slate-800">Logi<span class="text-blue-600">Meat</span></h1>
            <p class="text-slate-400 text-[10px] mt-2 uppercase tracking-widest font-black">Control de Acceso</p>
        </div>
        <?php if($error || isset($_GET['error'])): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-[10px] font-bold mb-6 text-center border border-red-100 uppercase">
                <?= $error ?: 'Sesión caducada por inactividad' ?>
            </div>
        <?php endif; ?>
        <form method="POST" class="space-y-5">
            <input type="text" name="usuario" placeholder="USUARIO" required class="w-full p-4 bg-slate-50 border rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 uppercase text-sm font-bold">
            <input type="password" name="clave" placeholder="CONTRASEÑA" required class="w-full p-4 bg-slate-50 border rounded-2xl outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="w-full bg-blue-600 text-white p-5 rounded-2xl font-bold hover:bg-blue-700 transition-all uppercase text-xs tracking-widest">Ingresar</button>
        </form>
        <a href="http://192.168.20.205:8000/site.html" class="mt-4 w-full inline-flex items-center justify-center p-4 rounded-2xl bg-slate-100 text-slate-700 text-xs font-black uppercase tracking-widest hover:bg-slate-200 transition-all">
            Volver a WORKBEEF
        </a>
    </div>
    <div class="mt-8 text-center text-slate-500">
        <p class="text-[10px] font-bold uppercase tracking-widest">Colbeef SAS - 2026</p>
        <p class="text-[9px] opacity-50">Programado por Daniel Almeida Jaimes</p>
    </div>
</body>
</html>