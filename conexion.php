<?php
declare(strict_types=1);

/**
 * Configuración versionable:
 * - Valores por defecto seguros para desarrollo local.
 * - Puede sobreescribirse por variables de entorno.
 * - Puede sobreescribirse por conexion.local.php (NO versionado).
 */
$config = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db' => getenv('DB_NAME') ?: 'db_logimeat',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: 'root',
    'port' => (int) (getenv('DB_PORT') ?: '3306'),
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];

$localConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'conexion.local.php';
if (is_readable($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

// Endurece despliegue en servidor: evita intentar acceso sin contraseña
// cuando la intención operativa es root/root.
if (!isset($config['pass']) || trim((string) $config['pass']) === '') {
    $config['pass'] = 'root';
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;port=%d;charset=%s',
    (string) $config['host'],
    (string) $config['db'],
    (int) $config['port'],
    (string) $config['charset']
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, (string) $config['user'], (string) $config['pass'], $options);
} catch (\PDOException $e) {
    error_log('DB connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Error de conexión a la base de datos.');
}