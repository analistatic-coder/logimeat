<?php
declare(strict_types=1);

/**
 * Copie este archivo como `conexion.local.php` en el servidor.
 * Ese archivo NO se versiona y evita conflictos al hacer git pull.
 */
return [
    'host' => '127.0.0.1',
    'db' => 'db_logimeat',
    'user' => 'root',
    'pass' => 'root',
    'port' => 3306,
    'charset' => 'utf8mb4',
    // Opcional: si la app vive en una subcarpeta del DocumentRoot (p. ej. www/logimeat),
    // poner el segmento de URL aquí para que CSS/JS carguen bien (ej. 'logimeat').
    // 'web_base' => 'logimeat',
];
