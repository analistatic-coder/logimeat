<?php
// eventos_calendario.php
require_once 'conexion.php';

// Consulta para traer los datos necesarios del calendario
// Unimos con Clientes para mostrar el nombre en el evento
$sql = "SELECT 
            p.id_interno,
            p.Fecha_de_Operacion, 
            p.Hora,
            c.Cliente, 
            p.Planta
        FROM Programacion p
        LEFT JOIN Clientes c ON p.Cliente = c.ID_Cliente";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

$eventos = [];

foreach ($rows as $row) {
    // 1. Convertir fecha de formato d/m/Y (como está en tu SQL) a Y-m-d (formato ISO para JS)
    $fecha_limpia = str_replace('/', '-', $row['Fecha_de_Operacion']);
    $start_date = date("Y-m-d", strtotime($fecha_limpia));

    // 2. Definir color según la Planta (Punto 2 de tu requerimiento)
    // Asumiendo que 1 es Beneficio y 2 es Desposte según tus archivos previos
    $color = ($row['Planta'] == '1') ? '#10b981' : '#3b82f6'; // Verde vs Azul
    $planta_nombre = ($row['Planta'] == '1') ? 'BENEFICIO' : 'DESPOSTE';

    $eventos[] = [
        'id'    => $row['id_interno'],
        'title' => $row['Hora'] . " | " . ($row['Cliente'] ?? 'Sin Cliente'),
        'start' => $start_date,
        'color' => $color,
        'description' => "Planta: " . $planta_nombre
    ];
}

// Devolver el resultado como JSON para que el calendario lo lea
header('Content-Type: application/json');
echo json_encode($eventos);