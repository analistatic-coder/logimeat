<?php
/**
 * Laragon: MySQL escucha en 127.0.0.1:3306 (puerto por defecto).
 * El sitio web (Apache/Nginx) usa otro puerto (p. ej. 80); no confundir con 3306.
 *
 * Acceso desde la red local: en Laragon, sitio en www\logimeat; en el navegador
 * usar http://TU_IP_LAN/logimeat/ y permitir Apache en el firewall de Windows si hace falta.
 */
$host = '127.0.0.1';
$db   = 'db_logimeat';
$user = 'root';
$pass = '';
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
     die("Error de conexión: " . $e->getMessage());
}
?>