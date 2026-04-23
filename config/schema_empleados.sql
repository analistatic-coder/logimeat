-- Módulo Personal: empleados, descansos, programación semanal (LogiMeat)
-- Ejecutar en db_logimeat: mysql -u root db_logimeat < config/schema_empleados.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS empleado (
  ID_Empleado VARCHAR(64) NOT NULL,
  Tipo_Documento VARCHAR(32) DEFAULT NULL,
  Numero_Documento VARCHAR(32) DEFAULT NULL,
  Nombre_Completo VARCHAR(255) NOT NULL,
  Cargo VARCHAR(255) DEFAULT NULL,
  Area VARCHAR(128) DEFAULT NULL,
  Telefono VARCHAR(64) DEFAULT NULL,
  Email VARCHAR(255) DEFAULT NULL,
  Fecha_Ingreso VARCHAR(32) DEFAULT NULL,
  Activo VARCHAR(8) DEFAULT 'SI',
  Observaciones TEXT,
  PRIMARY KEY (ID_Empleado),
  KEY idx_empleado_doc (Numero_Documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empleado_descanso (
  id_interno INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ID_Descanso VARCHAR(64) NOT NULL,
  ID_Empleado VARCHAR(64) NOT NULL,
  Tipo VARCHAR(64) DEFAULT NULL,
  Fecha_Inicio VARCHAR(32) DEFAULT NULL,
  Fecha_Fin VARCHAR(32) DEFAULT NULL,
  Observaciones TEXT,
  PRIMARY KEY (id_interno),
  UNIQUE KEY uk_descanso_negocio (ID_Descanso),
  KEY idx_descanso_empleado (ID_Empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programacion_actividad_extra (
  id_interno INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Nombre VARCHAR(128) NOT NULL,
  PRIMARY KEY (id_interno),
  UNIQUE KEY uk_prog_act_nom (Nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empleado_programacion (
  id_interno INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ID_Programacion VARCHAR(64) NOT NULL,
  ID_Empleado VARCHAR(64) NOT NULL,
  Dia_Semana VARCHAR(16) DEFAULT NULL,
  Hora_Entrada VARCHAR(16) DEFAULT NULL,
  Hora_Salida VARCHAR(16) DEFAULT NULL,
  Turno VARCHAR(64) DEFAULT NULL,
  Actividad VARCHAR(128) DEFAULT NULL,
  Planta VARCHAR(32) DEFAULT NULL,
  Producto VARCHAR(128) DEFAULT NULL,
  Observaciones TEXT,
  PRIMARY KEY (id_interno),
  UNIQUE KEY uk_prog_personal (ID_Programacion),
  KEY idx_prog_empleado (ID_Empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
