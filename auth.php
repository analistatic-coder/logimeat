<?php
session_start();

function lm_rol_actual(): string
{
    return trim((string) ($_SESSION['rol'] ?? 'Operativo'));
}

function lm_es_super_admin(): bool
{
    return strcasecmp(lm_rol_actual(), 'Super Admin') === 0;
}

function lm_es_admin(): bool
{
    $rol = strtoupper(lm_rol_actual());

    return lm_es_super_admin() || $rol === 'ADMINISTRADOR' || $rol === 'ADMIN';
}

function lm_es_operativo(): bool
{
    return !lm_es_admin();
}

/**
 * 1. SEGURIDAD: CADUCIDAD DE SESIÓN (15 MINUTOS)
 */
$timeout = 900; // 900 segundos = 15 minutos

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['ultima_actividad'])) {
    $sesion_viva = time() - $_SESSION['ultima_actividad'];
    if ($sesion_viva > $timeout) {
        session_unset();
        session_destroy();
        header("Location: login.php?error=sesion_expirada");
        exit();
    }
}
$_SESSION['ultima_actividad'] = time();


/**
 * 2. SIDEBAR: MENÚ LATERAL FIJO
 */
function mostrarSidebar($activePage = '') {
    $rol = lm_rol_actual();
    $verTablero = !lm_es_operativo();
    $verConfiguracion = lm_es_admin();
    ?>
    <aside class="fixed inset-y-0 left-0 w-64 bg-slate-900 text-slate-300 flex flex-col shadow-2xl z-50">
        <div class="p-8">
            <div class="flex items-center gap-3 mb-10">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white font-black shadow-lg shadow-blue-900/50">L</div>
                <span class="text-xl font-black text-white tracking-tighter">LogiMeat <span class="text-blue-500 text-xs">v3</span></span>
            </div>

            <nav class="space-y-2">
                <a href="index.php" class="flex items-center gap-3 p-4 rounded-2xl transition-all <?= $activePage == 'home' ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'hover:bg-slate-800' ?>">
                    <span>📊</span> <span class="text-sm font-bold">Dashboard</span>
                </a>
                <a href="programacion.php" class="flex items-center gap-3 p-4 rounded-2xl transition-all <?= $activePage == 'prog' ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'hover:bg-slate-800' ?>">
                    <span>📅</span> <span class="text-sm font-bold">Programación</span>
                </a>
                <a href="view_data.php" class="flex items-center gap-3 p-4 rounded-2xl transition-all <?= $activePage == 'cal' ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'hover:bg-slate-800' ?>">
                    <span>🗓</span> <span class="text-sm font-bold">Calendario</span>
                </a>
                <a href="logistica.php" class="flex items-center gap-3 p-4 rounded-2xl transition-all <?= $activePage == 'log' ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'hover:bg-slate-800' ?>">
                    <span>🚛</span> <span class="text-sm font-bold">Conductores / Vehículos</span>
                </a>
                <a href="otif.php" class="flex items-center gap-3 p-4 rounded-2xl transition-all <?= $activePage == 'otif' ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'hover:bg-slate-800' ?>">
                    <span>🎯</span> <span class="text-sm font-bold">Calidad OTIF</span>
                </a>
                <?php if($verTablero): ?>
                <a href="tablero_descansos.php" class="flex items-center gap-3 p-4 rounded-2xl transition-all <?= $activePage == 'tablero' ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'hover:bg-slate-800' ?>">
                    <span>📋</span> <span class="text-sm font-bold">Tablero personal</span>
                </a>
                <?php endif; ?>
                
                <?php if($verConfiguracion): ?>
                <div class="pt-6 mt-6 border-t border-slate-800">
                    <p class="px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Ajustes</p>
                    <a href="maestros.php" class="flex items-center gap-3 p-4 rounded-2xl transition-all <?= $activePage == 'maestros' ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'hover:bg-slate-800' ?>">
                        <span>⚙️</span> <span class="text-sm font-bold">Configuración</span>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
        </div>

        <div class="mt-auto p-6 border-t border-slate-800 bg-slate-900/50">
            <div class="flex items-center gap-3 mb-4 px-2">
                <div class="w-8 h-8 bg-slate-700 rounded-full flex items-center justify-center text-xs">👤</div>
                <div class="overflow-hidden">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-[9px] text-slate-500 font-black uppercase tracking-tighter"><?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <a href="cambiar_password.php" class="flex items-center justify-center gap-2 p-3 w-full mb-2 bg-slate-800/80 text-slate-200 rounded-xl text-[11px] font-bold hover:bg-slate-700 hover:text-white transition-all border border-slate-700/80">
                🔑 Cambiar contraseña
            </a>
            <a href="login.php?action=logout" class="flex items-center justify-center gap-2 p-3 w-full bg-red-500/10 text-red-500 rounded-xl text-xs font-bold hover:bg-red-500 hover:text-white transition-all">
                CERRAR SESIÓN
            </a>
        </div>
    </aside>
    <?php
}

/**
 * 3. FOOTER: PIE DE PÁGINA CON CRÉDITOS
 * Ajustado para evitar desbordamientos laterales.
 */
function mostrarFooter() {
    ?>
    <div class="clear-both w-full h-1"></div>
    
    <footer class="ml-64 mt-auto py-10 border-t border-slate-100 bg-white">
        <div class="max-w-7xl mx-auto px-10 flex flex-col md:flex-row justify-between items-center gap-4">
            
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-slate-800 rounded flex items-center justify-center text-white text-[10px] font-black">L</div>
                <span class="text-sm font-bold text-slate-700 tracking-tight">LogiMeat ERP</span>
            </div>

            <div class="text-center">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">
                    Colbeef SAS - Derechos Reservados 2026
                </p>
            </div>

            <div class="text-right">
                <p class="text-[10px] text-slate-400 font-medium">
                    Programado por: <span class="text-slate-600 font-bold">Daniel Almeida Jaimes</span>
                </p>
            </div>
            
        </div>
    </footer>
    <?php
}