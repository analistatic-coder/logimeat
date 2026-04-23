-- Empleados iniciales de ejemplo (Colbeef / logística). Ajuste o amplíe según su nómina real.
SET NAMES utf8mb4;

INSERT INTO empleado (ID_Empleado, Tipo_Documento, Numero_Documento, Nombre_Completo, Cargo, Area, Telefono, Email, Fecha_Ingreso, Activo, Observaciones) VALUES
('EMP-0001', 'CC', '1000000001', 'EJEMPLO GARCIA LOPEZ', 'COORDINADOR LOGISTICA', 'DESPOSTE', '3000000001', 'ejemplo.garcia@colbeef.com', '01/01/2020', 'SI', 'Registro demo'),
('EMP-0002', 'CC', '1000000002', 'MARIA RODRIGUEZ PEREZ', 'AUXILIAR LOGISTICA', 'DESPOSTE', '3000000002', 'maria.rodriguez@colbeef.com', '15/03/2021', 'SI', NULL),
('EMP-0003', 'CC', '1000000003', 'CARLOS MENDOZA RUIZ', 'GESTOR LOGISTICA DESPOSTE', 'DESPOSTE', '3000000003', 'carlos.mendoza@colbeef.com', '10/06/2019', 'SI', NULL),
('EMP-0004', 'CC', '1000000004', 'ANDREA RUBIANO CASTRO', 'AUXILIAR CALIDAD', 'CALIDAD', '3000000004', 'andrea.rubiano@colbeef.com', '01/09/2022', 'SI', NULL),
('EMP-0005', 'CC', '1000000005', 'NICOLAS RODRIGUEZ HERNANDEZ', 'ANALISTA LOGISTICA', 'LOGISTICA', '3000000005', 'nicolas.rodriguez@colbeef.com', '20/11/2018', 'SI', NULL),
('EMP-0006', 'CC', '1000000006', 'COORDINACION LOGISTICA CENTRAL', 'COORDINACION', 'LOGISTICA', '3000000006', 'logistica.central@colbeef.com', '01/01/2018', 'SI', 'Cuenta operativa demo');

INSERT INTO empleado_descanso (ID_Descanso, ID_Empleado, Tipo, Fecha_Inicio, Fecha_Fin, Observaciones) VALUES
('DES-0001', 'EMP-0002', 'VACACIONES', '01/07/2026', '15/07/2026', 'Ejemplo'),
('DES-0002', 'EMP-0004', 'DESCANSO', '10/02/2026', '10/02/2026', 'Día personal');

INSERT INTO empleado_programacion (ID_Programacion, ID_Empleado, Dia_Semana, Hora_Entrada, Hora_Salida, Turno, Observaciones) VALUES
('PRG-0001', 'EMP-0001', 'LUNES', '06:00', '14:00', 'MAÑANA', NULL),
('PRG-0002', 'EMP-0001', 'MARTES', '06:00', '14:00', 'MAÑANA', NULL),
('PRG-0003', 'EMP-0002', 'LUNES', '14:00', '22:00', 'TARDE', NULL),
('PRG-0004', 'EMP-0003', 'LUNES A VIERNES', '05:00', '13:00', 'MAÑANA', 'Turno corrido ejemplo');
