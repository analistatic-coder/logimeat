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

    // --- Correo SMTP (Trace Beef / restablecer contraseña) ---
    // NO use la clave "host" para el mail: en este mismo array "host" es el servidor MySQL.
    // Puerto 465 → smtp_encryption => 'ssl'
    //
    // Opción A (recomendada):
    // 'mail_from' => 'no-responder-sirt@colbeef.com.co',
    // 'mail_from_name' => 'Sistema Trace Beef',
    // 'smtp_host' => 'mail.colbeef.com.co',
    // 'smtp_port' => 465,
    // 'smtp_encryption' => 'ssl',
    // 'smtp_user' => 'no-responder-sirt@colbeef.com.co',
    // 'smtp_pass' => 'PONGA_LA_CLAVE_AQUI',
    //
    // Opción B (mismos nombres que SMTP_CONFIG en Python / Trace Beef):
    // 'from_email' => 'no-responder-sirt@colbeef.com.co',
    // 'from_name' => 'Sistema Trace Beef',
    // 'smtp_host' => 'mail.colbeef.com.co',
    // 'smtp_port' => 465,
    // 'smtp_encryption' => 'ssl',
    // 'username' => 'no-responder-sirt@colbeef.com.co',
    // 'password' => 'PONGA_LA_CLAVE_AQUI',
    //
    // Si SSL falla por certificado interno (probar con criterio de TI):
    // 'smtp_verify_peer' => false,
    //
    // Microsoft 365: smtp.office365.com, 587, smtp_encryption => 'tls'

    // Opcional: si la app vive en subcarpeta del DocumentRoot (ej. logimeat)
    // 'web_base' => 'logimeat',
];
