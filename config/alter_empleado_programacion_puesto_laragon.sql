-- Laragon / MySQL: columna «Puesto de trabajo» en manejo de personal (empleado_programacion).
-- Ejecute UNA vez en la base del proyecto:
--
--   mysql -u root -p nombre_bd < config/alter_empleado_programacion_puesto_laragon.sql
--
-- Si aparece «Duplicate column name», la columna ya existe.

SET NAMES utf8mb4;

ALTER TABLE empleado_programacion
  ADD COLUMN Puesto_Trabajo VARCHAR(64) DEFAULT NULL AFTER ID_Empleado;
