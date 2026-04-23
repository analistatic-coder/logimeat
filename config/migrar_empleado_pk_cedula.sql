-- Migra tabla empleado: elimina id_interno y deja PRIMARY KEY en ID_Empleado (cédula / identificador de negocio).
-- Las tablas empleado_descanso y empleado_programacion referencian ID_Empleado (texto), no id_interno; no requieren cambio.
-- Ejecutar en db_logimeat: mysql -u root db_logimeat < config/migrar_empleado_pk_cedula.sql

SET NAMES utf8mb4;

ALTER TABLE empleado
  DROP PRIMARY KEY,
  DROP INDEX uk_empleado_negocio,
  ADD PRIMARY KEY (ID_Empleado),
  DROP COLUMN id_interno;
