-- Empleados: puesto de trabajo e id_interno técnico (ID_Empleado = cédula sigue siendo clave primaria).
-- Ejecutar una sola vez en la base del proyecto. Si alguna columna ya existe, omita esa sentencia o coméntela.
--   mysql -u USUARIO -p NOMBRE_BD < config/alter_empleado_puesto_idinterno.sql

SET NAMES utf8mb4;

ALTER TABLE empleado
  ADD COLUMN Puesto_Trabajo VARCHAR(64) DEFAULT NULL AFTER Area;

ALTER TABLE empleado
  ADD COLUMN id_interno INT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE;
