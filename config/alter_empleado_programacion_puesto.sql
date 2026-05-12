-- Manejo de personal: puesto asignado en la fila de programación (misma lista que empleado).
-- Ejecutar una vez en la base del proyecto. Si aparece «Duplicate column name», ya está aplicado.

SET NAMES utf8mb4;

ALTER TABLE empleado_programacion
  ADD COLUMN Puesto_Trabajo VARCHAR(64) DEFAULT NULL AFTER Producto;
