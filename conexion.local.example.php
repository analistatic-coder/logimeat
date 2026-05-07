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

    // --- Correo SMTP Colbeef (LogiMeat: restablecer contraseña) ---
    // Servidor real de Trace Beef / SIRT — puerto 465 = SSL (smtp_encryption => 'ssl').
    // Copie estas claves a conexion.local.php del servidor y ponga la contraseña real
    // (ese archivo no se sube a Git).
    //
    // 'mail_from' => 'no-responder-sirt@colbeef.com.co',
    // 'mail_from_name' => 'Sistema Trace Beef',
    // 'smtp_host' => 'mail.colbeef.com.co',
    // 'smtp_port' => 465,
    // 'smtp_encryption' => 'ssl',
    // 'smtp_user' => 'no-responder-sirt@colbeef.com.co',
    // 'smtp_pass' => 'PONGA_LA_CLAVE_AQUI',
    //
    // Otros ejemplos: Microsoft 365 → smtp.office365.com, 587, 'tls'
    // Relé interno puerto 25 sin cifrado → smtp_encryption => ''

    // Opcional: si la app vive en subcarpeta del DocumentRoot (ej. logimeat)
    // 'web_base' => 'logimeat',
];
