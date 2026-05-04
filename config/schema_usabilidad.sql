-- Auditoría de usabilidad (LogiMeat). También puede crearse automáticamente desde PHP al primer evento.
-- Opcional manual: ejecutar desde MySQL.

CREATE TABLE IF NOT EXISTS app_usabilidad_evento (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('login','pagina') NOT NULL,
  modulo VARCHAR(96) NOT NULL,
  id_user INT NULL,
  rol VARCHAR(80) NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_creado (creado_en),
  INDEX idx_tipo_mod_fecha (tipo, modulo, creado_en),
  INDEX idx_user_fecha (id_user, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
