-- Campos operativos en programación de personal + catálogo de actividades adicionales
-- Ejecutar en db_logimeat si no usa el script PHP de migración.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS programacion_actividad_extra (
  id_interno INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Nombre VARCHAR(128) NOT NULL,
  PRIMARY KEY (id_interno),
  UNIQUE KEY uk_prog_act_nom (Nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE empleado_programacion
  ADD COLUMN Actividad VARCHAR(128) DEFAULT NULL AFTER Turno,
  ADD COLUMN Planta VARCHAR(32) DEFAULT NULL AFTER Actividad,
  ADD COLUMN Producto VARCHAR(128) DEFAULT NULL AFTER Planta;
