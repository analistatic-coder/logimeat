<?php
declare(strict_types=1);

require_once 'auth.php';
require_once 'conexion.php';
require_once __DIR__ . '/config/usabilidad_log.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!lm_es_super_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);

    exit;
}

$dias = isset($_GET['dias']) ? max(7, min(365, (int) $_GET['dias'])) : 30;
$stats = lm_usabilidad_estadisticas($pdo, $dias);

echo json_encode([
    'ok' => true,
    'dias' => $dias,
    'actualizado' => date('Y-m-d H:i:s'),
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
