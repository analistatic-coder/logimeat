<?php
/**
 * ARCHIVO DE CONEXIÓN - SERVIDOR LOGIMEAT
 * IP DEL SERVIDOR: 192.168.20.205
 */

$host = '127.0.0.1'; // El código corre DENTRO del servidor, por eso usa 127.0.0.1
$db   = 'db_logimeat';
$user = 'root';
$pass = 'root';      // Contraseña confirmada
$port = 3306; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;port=$port;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Si falla, nos dirá el motivo exacto en el servidor
     die("Error de conexión en 192.168.20.205: " . $e->getMessage());
}
?>