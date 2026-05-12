-- Laragon / MySQL: para que aparezca «Puesto de trabajo» al crear/editar empleado en Configuración.
-- Abra HeidiSQL (o Terminal → mysql), seleccione la base de datos del proyecto y ejecute UNA vez:
--
--   mysql -u root -p nombre_bd < config/alter_empleado_puesto_laragon.sql
--
-- Si MySQL devuelve «Duplicate column name», la columna ya existe: no haga falta repetir.

SET NAMES utf8mb4;

ALTER TABLE empleado
  ADD COLUMN Puesto_Trabajo VARCHAR(64) DEFAULT NULL;
