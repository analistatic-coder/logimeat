<?php
declare(strict_types=1);

/**
 * Conexión DB (Laravel/LAMP-style):
 * - Toma valores de variables de entorno si existen.
 * - Si no existen, usa valores por defecto para Laragon/local server.
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db = getenv('DB_NAME') ?: 'db_logimeat';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';
$port = (int) (getenv('DB_PORT') ?: '3306');
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};port={$port};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // No exponer detalles sensibles en pantalla cuando está en servidor.
    error_log('DB connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Error de conexión a la base de datos.');
}
