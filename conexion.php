<?php
/**
 * ARCHIVO DE CONEXIÓN - SERVIDOR PRODUCCIÓN (IP 20.205)
 * Configurado con usuario root y contraseña root.
 */

$host = '127.0.0.1'; 
$db   = 'db_logimeat';
$user = 'root';
$pass = 'root'; // Contraseña actualizada según tu indicación
$port = 3306;   // Puerto estándar de MySQL
$charset = 'utf8mb4';

// Estructura DSN para PDO
$dsn = "mysql:host=$host;dbname=$db;port=$port;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true, 
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // Si necesitas verificar la conexión en pantalla, puedes descomentar la siguiente línea:
     // echo "Conexión exitosa al servidor 20.205";
} catch (\PDOException $e) {
     // Error detallado para depuración inicial en el servidor
     die("ERROR DE CONEXIÓN EN EL SERVIDOR: " . $e->getMessage());
}
?>