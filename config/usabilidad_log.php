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
    static $listo = false;
    if ($listo) {
        return true;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS app_usabilidad_evento (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tipo ENUM('login','pagina') NOT NULL,
                modulo VARCHAR(96) NOT NULL,
                id_user VARCHAR(64) NULL,
                rol VARCHAR(80) NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_creado (creado_en),
                INDEX idx_tipo_mod_fecha (tipo, modulo, creado_en),
                INDEX idx_user_fecha (id_user, creado_en)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        error_log('lm_usabilidad_ensure_table CREATE: ' . $e->getMessage());

        return false;
    }

    // Tablas creadas antes con id_user INT: ampliar para aceptar el mismo formato que User.ID_User (ej. US-0001).
    static $migradoTipoId = false;
    if (!$migradoTipoId) {
        $migradoTipoId = true;
        try {
            if (lm_usabilidad_tabla_exist($pdo)) {
                $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
                $st = $pdo->prepare(
                    'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
                );
                $st->execute([$db, 'app_usabilidad_evento', 'id_user']);
                $tipo = (string) $st->fetchColumn();
                if ($tipo !== '' && stripos($tipo, 'int') !== false) {
                    $pdo->exec('ALTER TABLE app_usabilidad_evento MODIFY id_user VARCHAR(64) NULL');
                }
            }
        } catch (Throwable $e) {
            error_log('lm_usabilidad_ensure_table ALTER id_user: ' . $e->getMessage());
        }
    }

    $listo = true;

    return true;
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

    $uidRaw = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
    if ($uidRaw === '') {
        return;
    }

    $mod = lm_usabilidad_modulo_actual();
    if ($mod === 'login' || $mod === 'tablero_usabilidad_datos') {
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
        $st->execute([$mod, $uidRaw, $rol !== '' ? $rol : null, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null]);
    } catch (Throwable $e) {
        error_log('lm_usabilidad_registrar_peticion: ' . $e->getMessage());
    }
}

function lm_usabilidad_registrar_login(PDO $pdo, string|int $idUser, string $rol): void
{
    $idStr = trim((string) $idUser);
    if ($idStr === '' || !lm_usabilidad_ensure_table($pdo)) {
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
        $st->execute([$idStr, $rolT !== '' ? $rolT : null, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null]);
    } catch (Throwable $e) {
        error_log('lm_usabilidad_registrar_login: ' . $e->getMessage());
    }
}

/**
 * Métricas agregadas para el tablero de usabilidad (Super Admin).
 *
 * @return array{
 *   tabla_ok: bool,
 *   total_eventos: int,
 *   total_logins: int,
 *   total_paginas: int,
 *   usuarios_activos: int,
 *   sesiones_por_dia: list<array{dia: string, c: int}>,
 *   modulos: list<array{modulo: string, c: int, etiqueta: string}>,
 *   usuarios_top: list<array{nombre: string, c: int}>,
 *   error: ?string
 * }
 */
function lm_usabilidad_estadisticas(PDO $pdo, int $dias): array
{
    $base = [
        'tabla_ok' => false,
        'total_eventos' => 0,
        'total_logins' => 0,
        'total_paginas' => 0,
        'usuarios_activos' => 0,
        'sesiones_por_dia' => [],
        'modulos' => [],
        'usuarios_top' => [],
        'error' => null,
    ];

    lm_usabilidad_ensure_table($pdo);
    if (!lm_usabilidad_tabla_exist($pdo)) {
        $base['error'] = 'sin_tabla';

        return $base;
    }

    $dias = max(1, min(366, $dias));
    $fechaDesde = date('Y-m-d H:i:s', strtotime('-' . $dias . ' days'));

    try {
        $totalEventos = (int) $pdo->query('SELECT COUNT(*) FROM app_usabilidad_evento')->fetchColumn();

        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM app_usabilidad_evento WHERE tipo = \'login\' AND creado_en >= ?'
        );
        $st->execute([$fechaDesde]);
        $totalLogins = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM app_usabilidad_evento WHERE tipo = \'pagina\' AND creado_en >= ?'
        );
        $st->execute([$fechaDesde]);
        $totalPaginas = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            'SELECT COUNT(DISTINCT id_user) FROM app_usabilidad_evento
             WHERE tipo = \'pagina\' AND id_user IS NOT NULL AND creado_en >= ?'
        );
        $st->execute([$fechaDesde]);
        $usuariosActivos = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            'SELECT DATE(creado_en) AS dia, COUNT(*) AS c
             FROM app_usabilidad_evento
             WHERE tipo = \'login\' AND creado_en >= ?
             GROUP BY DATE(creado_en)
             ORDER BY dia ASC'
        );
        $st->execute([$fechaDesde]);
        $sesiones = $st->fetchAll(PDO::FETCH_ASSOC);
        $sesionesNorm = [];
        foreach ($sesiones as $r) {
            $sesionesNorm[] = [
                'dia' => (string) ($r['dia'] ?? ''),
                'c' => (int) ($r['c'] ?? 0),
            ];
        }

        $st = $pdo->prepare(
            'SELECT modulo, COUNT(*) AS c
             FROM app_usabilidad_evento
             WHERE tipo = \'pagina\' AND creado_en >= ?
             GROUP BY modulo ORDER BY c DESC LIMIT 18'
        );
        $st->execute([$fechaDesde]);
        $modsRaw = $st->fetchAll(PDO::FETCH_ASSOC);
        $modulos = [];
        foreach ($modsRaw as $r) {
            $m = (string) ($r['modulo'] ?? '');
            $modulos[] = [
                'modulo' => $m,
                'c' => (int) ($r['c'] ?? 0),
                'etiqueta' => lm_usabilidad_etiqueta_modulo($m),
            ];
        }

        $st = $pdo->prepare(
            'SELECT e.id_user,
                    COALESCE(NULLIF(TRIM(MAX(u.Nombre)), \'\'), CONCAT(\'ID \', e.id_user)) AS nombre,
                    COUNT(*) AS c
             FROM app_usabilidad_evento e
             LEFT JOIN `User` u ON TRIM(CAST(u.ID_User AS CHAR)) COLLATE utf8mb4_unicode_ci
                 = TRIM(CAST(e.id_user AS CHAR)) COLLATE utf8mb4_unicode_ci
             WHERE e.tipo = \'pagina\' AND e.id_user IS NOT NULL AND TRIM(e.id_user) <> \'\' AND e.creado_en >= ?
             GROUP BY e.id_user
             ORDER BY c DESC
             LIMIT 12'
        );
        $st->execute([$fechaDesde]);
        $usersRaw = $st->fetchAll(PDO::FETCH_ASSOC);
        $usuariosTop = [];
        foreach ($usersRaw as $r) {
            $usuariosTop[] = [
                'nombre' => (string) ($r['nombre'] ?? ''),
                'c' => (int) ($r['c'] ?? 0),
            ];
        }

        return [
            'tabla_ok' => true,
            'total_eventos' => $totalEventos,
            'total_logins' => $totalLogins,
            'total_paginas' => $totalPaginas,
            'usuarios_activos' => $usuariosActivos,
            'sesiones_por_dia' => $sesionesNorm,
            'modulos' => $modulos,
            'usuarios_top' => $usuariosTop,
            'error' => null,
        ];
    } catch (Throwable $e) {
        error_log('lm_usabilidad_estadisticas: ' . $e->getMessage());

        return [
            'tabla_ok' => true,
            'total_eventos' => 0,
            'total_logins' => 0,
            'total_paginas' => 0,
            'usuarios_activos' => 0,
            'sesiones_por_dia' => [],
            'modulos' => [],
            'usuarios_top' => [],
            'error' => 'consulta',
        ];
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
        'tablero_usabilidad_datos' => 'Actualización tablero (automático)',
    ];

    return $map[$modulo] ?? ucfirst(str_replace(['_', ':'], [' ', ': '], $modulo));
}
