<?php
// config/consultas.php
require_once __DIR__ . '/db.php';

function obtenerResumenProgramacion($pdo) {
    $sql = "SELECT 
                p.Fecha_de_Operacion, 
                c.Cliente, 
                pr.Producto, 
                p.Cantidad, 
                p.Estado_Actividad 
            FROM Programacion p
            LEFT JOIN Clientes c ON p.Cliente = c.ID_Cliente
            LEFT JOIN Producto pr ON p.Producto = pr.ID_Producto
            ORDER BY p.id_interno DESC 
            LIMIT 10";
    return $pdo->query($sql)->fetchAll();
}
?>