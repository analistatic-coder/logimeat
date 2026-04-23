-- Filtro por semana calendario (ISO) para tablero de descansos y programación
ALTER TABLE empleado_descanso
  ADD COLUMN Anio SMALLINT UNSIGNED NULL AFTER Observaciones,
  ADD COLUMN Numero_Semana TINYINT UNSIGNED NULL AFTER Anio;

ALTER TABLE empleado_programacion
  ADD COLUMN Anio SMALLINT UNSIGNED NULL AFTER Observaciones,
  ADD COLUMN Numero_Semana TINYINT UNSIGNED NULL AFTER Anio;
