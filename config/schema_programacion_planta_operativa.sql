-- Columna operativa BENEFICIO|DESPOSTE|CELFRIO (independiente de Destino y OPL/vínculo)
-- Ejecutar si aún no aplicó: php scripts/aplicar_migration_planta_operativa.php

ALTER TABLE Programacion
  ADD COLUMN Planta_Operativa VARCHAR(16) NULL COMMENT 'BENEFICIO|DESPOSTE|CELFRIO' AFTER Planta;
