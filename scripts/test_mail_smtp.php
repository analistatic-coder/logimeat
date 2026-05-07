<?php
declare(strict_types=1);

/**
 * Prueba envío SMTP usando la misma config que LogiMeat (conexion.local.php).
 *
 * Uso en el servidor (desde la raíz del proyecto):
 *   php scripts/test_mail_smtp.php destinatario@colbeef.com
 */

if (PHP_SAPI !== 'cli') {
    exit('Solo CLI.');
}

$dest = trim((string) ($argv[1] ?? ''));
if ($dest === '' || !filter_var($dest, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/test_mail_smtp.php correo@ejemplo.com\n");
    exit(1);
}

chdir(dirname(__DIR__));
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config/mail_send.php';

$asunto = 'LogiMeat — prueba SMTP ' . date('Y-m-d H:i:s');
$cuerpo = "Si recibe este mensaje, el SMTP configurado en conexion.local.php funciona.\r\n";

$r = lm_mail_send_app($dest, $asunto, $cuerpo);
if ($r['ok']) {
    echo "OK: mensaje enviado a {$dest}\n";
    exit(0);
}

echo 'ERROR: ' . ($r['error'] ?? 'desconocido') . "\n";
exit(2);
