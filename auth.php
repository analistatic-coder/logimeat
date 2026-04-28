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
    <aside id="appSidebar" class="fixed inset-y-0 left-0 w-64 bg-slate-900 text-slate-300 flex flex-col shadow-2xl z-50 transition-transform duration-200">
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
            <a href="http://192.168.20.205:8000/site.html" class="flex items-center justify-center gap-2 p-3 w-full mb-2 bg-slate-800/80 text-slate-200 rounded-xl text-[11px] font-bold hover:bg-slate-700 hover:text-white transition-all border border-slate-700/80">
                ↩ Volver a WORKBEEF
            </a>
            <a href="login.php?action=logout" class="flex items-center justify-center gap-2 p-3 w-full bg-red-500/10 text-red-500 rounded-xl text-xs font-bold hover:bg-red-500 hover:text-white transition-all">
                CERRAR SESIÓN
            </a>
        </div>
    </aside>
    <div id="sidebarHotspot" class="fixed left-0 top-0 h-full w-2 z-[65]" title="Mostrar menú"></div>
    <button id="sidebarPinBtn" type="button" class="fixed left-3 top-3 z-[70] px-2.5 py-1.5 rounded-full bg-slate-900/85 text-white text-[10px] font-black shadow-lg hover:bg-slate-700 transition-colors backdrop-blur-sm" title="Fijar / auto-ocultar menú">
        FIJAR
    </button>
    <script>
    (function () {
        var KEY_HIDDEN = 'lm_sidebar_hidden';
        var KEY_PINNED = 'lm_sidebar_pinned';
        function applySidebarState(hidden) {
            var sidebar = document.getElementById('appSidebar');
            if (!sidebar) return;

            sidebar.style.transform = hidden ? 'translateX(-100%)' : 'translateX(0)';

            document.querySelectorAll('body > div.flex-1').forEach(function (el) {
                el.style.marginLeft = hidden ? '0' : '16rem';
                el.style.width = hidden ? '100%' : 'calc(100% - 16rem)';
            });

            var footer = document.getElementById('appFooter');
            if (footer) {
                footer.style.marginLeft = hidden ? '0' : '16rem';
            }
        }

        function initSidebarToggle() {
            var pinBtn = document.getElementById('sidebarPinBtn');
            var sidebar = document.getElementById('appSidebar');
            var hotspot = document.getElementById('sidebarHotspot');
            if (!sidebar || !hotspot || !pinBtn) return;

            var pinned = localStorage.getItem(KEY_PINNED) === '1';
            var hidden = localStorage.getItem(KEY_HIDDEN) === '1';
            if (!pinned && localStorage.getItem(KEY_HIDDEN) === null) hidden = true;

            function updatePinUi() {
                pinBtn.textContent = pinned ? 'FIJO' : 'AUTO';
                pinBtn.title = pinned ? 'Menú fijo' : 'Menú auto-oculto';
            }

            updatePinUi();
            applySidebarState(hidden);

            hotspot.addEventListener('mouseenter', function () {
                if (pinned) return;
                hidden = false;
                applySidebarState(hidden);
            });
            sidebar.addEventListener('mouseleave', function () {
                if (pinned) return;
                hidden = !hidden;
                hidden = true;
                applySidebarState(hidden);
            });

            pinBtn.addEventListener('click', function () {
                pinned = !pinned;
                if (pinned) hidden = false;
                localStorage.setItem(KEY_PINNED, pinned ? '1' : '0');
                localStorage.setItem(KEY_HIDDEN, hidden ? '1' : '0');
                updatePinUi();
                applySidebarState(hidden);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebarToggle, { once: true });
        } else {
            initSidebarToggle();
        }
    })();
    </script>
    <div id="lm-app-loading" class="lm-app-loading" role="status" aria-live="polite" aria-busy="true">
        <div class="lm-app-loading-card">
            <div class="lm-app-loading-spinner" aria-hidden="true"></div>
            <p class="lm-app-loading-text">Cargando…</p>
        </div>
    </div>
    <style>
        #lm-app-loading.lm-app-loading-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.22s ease, visibility 0.22s ease;
        }
        #lm-app-loading {
            position: fixed;
            inset: 0;
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            opacity: 1;
            visibility: visible;
            transition: opacity 0.22s ease, visibility 0.22s ease;
        }
        .lm-app-loading-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            padding: 1.75rem 2.25rem;
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }
        .lm-app-loading-spinner {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 9999px;
            border: 4px solid rgba(16, 185, 129, 0.2);
            border-top-color: #10b981;
            animation: lm-app-loading-spin 0.7s linear infinite;
        }
        .lm-app-loading-text {
            margin: 0;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            font-size: 0.8125rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #475569;
        }
        @keyframes lm-app-loading-spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <script>
    (function () {
        var el = document.getElementById('lm-app-loading');
        if (!el) return;

        function show() {
            el.classList.remove('lm-app-loading-hidden');
            el.setAttribute('aria-busy', 'true');
        }
        function hide() {
            el.classList.add('lm-app-loading-hidden');
            el.setAttribute('aria-busy', 'false');
        }

        window.lmShowLoading = show;
        window.lmHideLoading = hide;

        function scheduleHide() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    requestAnimationFrame(function () { requestAnimationFrame(hide); });
                }, { once: true });
            } else {
                requestAnimationFrame(function () { requestAnimationFrame(hide); });
            }
        }
        scheduleHide();
        window.addEventListener('load', hide);
        window.addEventListener('pageshow', function (ev) {
            if (ev.persisted) hide();
        });

        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
            var href = a.getAttribute('href');
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
            try {
                var u = new URL(a.href, window.location.href);
                if (u.origin !== window.location.origin) return;
            } catch (err) {
                return;
            }
            show();
        }, true);

        document.addEventListener('submit', function (e) {
            if (e.target && e.target.tagName === 'FORM') {
                show();
            }
        }, true);
    })();
    </script>
    <?php
}

/**
 * 3. FOOTER: PIE DE PÁGINA CON CRÉDITOS
 * Ajustado para evitar desbordamientos laterales.
 */
function mostrarFooter() {
    ?>
    <div class="clear-both w-full h-1"></div>
    
    <footer id="appFooter" class="ml-64 mt-auto py-10 border-t border-slate-100 bg-white">
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