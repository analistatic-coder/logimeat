<?php
declare(strict_types=1);

/**
 * Auditoría opcional de usabilidad: ingresos (login) y vistas de página.
 * La tabla se crea automáticamente si no existe (primer uso tras despliegue).
 */

/** @var true */
function lm_usabilidad_tabla_exist(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM app_usabilidad_evento LIMIT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function lm_usabilidad_ensure_table(PDO $pdo): bool
{
    static $ensured = null;
    if ($ensured !== null) {
        return $ensured;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS app_usabilidad_evento (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tipo ENUM('login','pagina') NOT NULL,
                modulo VARCHAR(96) NOT NULL,
                id_user INT NULL,
                rol VARCHAR(80) NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_creado (creado_en),
                INDEX idx_tipo_mod_fecha (tipo, modulo, creado_en),
                INDEX idx_user_fecha (id_user, creado_en)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ensured = true;
    } catch (Throwable) {
        $ensured = false;
    }

    return $ensured;
}

function lm_usabilidad_modulo_actual(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $base = strtolower(preg_replace('/\.php$/', '', basename($script)));
    if ($base === '') {
        $base = 'desconocido';
    }
    if ($base === 'gestion_tabla') {
        $t = isset($_GET['tabla']) ? preg_replace('/[^a-zA-Z0-9_]/', '', (string) $_GET['tabla']) : '';
        if ($t !== '') {
            return 'gestion_tabla:' . $t;
        }
    }

    return $base;
}

/**
 * Registra una visita de página (una vez por petición HTTP autenticada).
 */
function lm_usabilidad_registrar_peticion_autenticada(PDO $pdo): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    if (!lm_usabilidad_ensure_table($pdo)) {
        return;
    }

    $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($uid <= 0) {
        return;
    }

    $mod = lm_usabilidad_modulo_actual();
    if ($mod === 'login') {
        return;
    }

    $rol = trim((string) ($_SESSION['rol'] ?? ''));
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (strlen($ip) > 45) {
        $ip = substr($ip, 0, 45);
    }
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (strlen($ua) > 250) {
        $ua = substr($ua, 0, 250);
    }

    try {
        $st = $pdo->prepare(
            'INSERT INTO app_usabilidad_evento (tipo, modulo, id_user, rol, ip, user_agent) VALUES (\'pagina\', ?, ?, ?, ?, ?)'
        );
        $st->execute([$mod, $uid, $rol !== '' ? $rol : null, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null]);
    } catch (Throwable) {
        // no bloquear la app
    }
}

function lm_usabilidad_registrar_login(PDO $pdo, int $idUser, string $rol): void
{
    if ($idUser <= 0 || !lm_usabilidad_ensure_table($pdo)) {
        return;
    }
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (strlen($ip) > 45) {
        $ip = substr($ip, 0, 45);
    }
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (strlen($ua) > 250) {
        $ua = substr($ua, 0, 250);
    }
    $rolT = trim($rol);
    try {
        $st = $pdo->prepare(
            'INSERT INTO app_usabilidad_evento (tipo, modulo, id_user, rol, ip, user_agent) VALUES (\'login\', \'sesion\', ?, ?, ?, ?)'
        );
        $st->execute([$idUser, $rolT !== '' ? $rolT : null, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null]);
    } catch (Throwable) {
    }
}

/** Etiqueta legible para gráficos y tablas. */
function lm_usabilidad_etiqueta_modulo(string $modulo): string
{
    if (preg_match('/^gestion_tabla:([a-zA-Z0-9_]+)$/', $modulo, $m)) {
        $maestros = [
            'clientes' => 'Clientes',
            'corte' => 'Cortes de canales',
            'zona' => 'Zonas',
            'departamento' => 'Departamentos',
            'municipio' => 'Municipios',
            'producto' => 'Productos',
            'tipo_de_cuarteo' => 'Tipo de cuarteo',
            'opl' => 'OPL',
            'vehiculo' => 'Vehículos',
            'conductor' => 'Conductores',
            'user' => 'Usuarios',
            'actividad' => 'Actividades',
            'planta' => 'Plantas',
            'logisticos' => 'Logísticos',
            'empleado' => 'Empleados',
            'empleado_descanso' => 'Descansos',
            'empleado_programacion' => 'Manejo de personal',
        ];
        $key = strtolower($m[1]);

        return 'Config: ' . ($maestros[$key] ?? $m[1]);
    }

    $map = [
        'sesion' => 'Inicio de sesión',
        'index' => 'Dashboard',
        'programacion' => 'Programación',
        'nueva_programacion' => 'Nueva programación',
        'editar_programacion' => 'Editar programación',
        'procesar_programacion' => 'Guardar programación',
        'view_data' => 'Calendario',
        'eventos_calendario' => 'API calendario',
        'logistica' => 'Conductores / vehículos',
        'otif' => 'Calidad OTIF',
        'tablero_descansos' => 'Tablero personal',
        'personal_descanso_form' => 'Formulario descanso',
        'personal_programacion_form' => 'Formulario personal',
        'maestros' => 'Configuración (menú)',
        'gestion_tabla' => 'Maestros (tabla)',
        'empleados_importar' => 'Importar empleados',
        'cambiar_password' => 'Cambiar contraseña',
        'tablero_usabilidad' => 'Este tablero',
    ];

    return $map[$modulo] ?? ucfirst(str_replace(['_', ':'], [' ', ': '], $modulo));
}
