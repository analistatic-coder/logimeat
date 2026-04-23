<?php
declare(strict_types=1);

/**
 * Importa CSV desde $baseDir.
 * Mapa por defecto: 01_empleados, 02_descansos, 03_programacion.
 * Para subcarpeta Descansos: usar $mapaArchivos con descansos.csv y programacion_semana.csv.
 *
 * @param array<string, string>|null $mapaArchivos nombre_archivo => empleado|empleado_descanso|empleado_programacion
 * @return array{mensajes: string[], errores: string[]}
 */
function importarEmpleadosDesdeCarpeta(PDO $pdo, string $baseDir, ?array $mapaArchivos = null): array
{
    $mensajes = [];
    $errores = [];

    $normalizarEncabezado = static function (string $h): string {
        $h = trim($h);
        if (str_starts_with($h, "\xEF\xBB\xBF")) {
            $h = substr($h, 3);
        }
        return mb_strtolower(str_replace([' ', '-'], '_', $h));
    };

    $leerCsv = static function (string $path): array {
        $fp = fopen($path, 'r');
        if (!$fp) {
            return [];
        }
        $rows = [];
        while (($r = fgetcsv($fp)) !== false) {
            $rows[] = $r;
        }
        fclose($fp);
        return $rows;
    };

    $indiceColumna = static function (array $headers, array $aliases) use ($normalizarEncabezado): ?int {
        $norm = array_map($normalizarEncabezado, $headers);
        foreach ($aliases as $a) {
            $na = $normalizarEncabezado($a);
            $i = array_search($na, $norm, true);
            if ($i !== false) {
                return (int) $i;
            }
        }
        return null;
    };

    $generarIdNegocio = static function (): string {
        return substr(bin2hex(random_bytes(4)), 0, 8);
    };

    if (!is_dir($baseDir)) {
        $errores[] = 'No existe la carpeta: ' . $baseDir;
        return ['mensajes' => $mensajes, 'errores' => $errores];
    }

    $mapa = $mapaArchivos ?? [
        '01_empleados.csv' => 'empleado',
        '02_descansos.csv' => 'empleado_descanso',
        '03_programacion.csv' => 'empleado_programacion',
    ];

    foreach ($mapa as $archivo => $tabla) {
        $ruta = $baseDir . DIRECTORY_SEPARATOR . $archivo;
        if (!is_readable($ruta)) {
            $mensajes[] = "Omitido (no encontrado): $archivo";
            continue;
        }
        $data = $leerCsv($ruta);
        if (count($data) < 2) {
            $errores[] = "$archivo: sin datos.";
            continue;
        }
        $headers = $data[0];
        $n = 0;
        $pdo->beginTransaction();
        try {
            if ($tabla === 'empleado') {
                $ixE = $indiceColumna($headers, ['ID_Empleado', 'id_empleado', 'Codigo', 'codigo_empleado']);
                $ixN = $indiceColumna($headers, ['Nombre_Completo', 'nombre_completo', 'Nombre', 'nombre', 'Empleado']);
                if ($ixE === null || $ixN === null) {
                    throw new RuntimeException("$archivo: faltan columnas ID_Empleado y/o Nombre_Completo (o alias).");
                }
                $ixTd = $indiceColumna($headers, ['Tipo_Documento', 'tipo_documento']);
                $ixNd = $indiceColumna($headers, ['Numero_Documento', 'numero_documento', 'Cedula', 'cedula', 'Documento']);
                $ixC = $indiceColumna($headers, ['Cargo', 'cargo', 'Puesto']);
                $ixA = $indiceColumna($headers, ['Area', 'area', 'Planta', 'Departamento']);
                $ixT = $indiceColumna($headers, ['Telefono', 'telefono', 'Celular']);
                $ixM = $indiceColumna($headers, ['Email', 'email', 'Correo']);
                $ixFi = $indiceColumna($headers, ['Fecha_Ingreso', 'fecha_ingreso', 'Ingreso']);
                $ixAc = $indiceColumna($headers, ['Activo', 'activo']);
                $ixOb = $indiceColumna($headers, ['Observaciones', 'observaciones', 'Notas']);
                // Solo inserción de filas nuevas (clave de negocio ID_Empleado). No DELETE/UPDATE: no altera IDs ni rompe referencias en descansos/programación.
                $sql = 'INSERT IGNORE INTO empleado (ID_Empleado,Tipo_Documento,Numero_Documento,Nombre_Completo,Cargo,Area,Telefono,Email,Fecha_Ingreso,Activo,Observaciones) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
                $st = $pdo->prepare($sql);
                for ($i = 1; $i < count($data); $i++) {
                    $row = $data[$i];
                    if (!isset($row[$ixE]) || trim((string) $row[$ixE]) === '') {
                        continue;
                    }
                    $st->execute([
                        trim((string) $row[$ixE]),
                        $ixTd !== null ? trim((string) ($row[$ixTd] ?? '')) : null,
                        $ixNd !== null ? trim((string) ($row[$ixNd] ?? '')) : null,
                        trim((string) $row[$ixN]),
                        $ixC !== null ? trim((string) ($row[$ixC] ?? '')) : null,
                        $ixA !== null ? trim((string) ($row[$ixA] ?? '')) : null,
                        $ixT !== null ? trim((string) ($row[$ixT] ?? '')) : null,
                        $ixM !== null ? trim((string) ($row[$ixM] ?? '')) : null,
                        $ixFi !== null ? trim((string) ($row[$ixFi] ?? '')) : null,
                        $ixAc !== null ? trim((string) ($row[$ixAc] ?? '')) : 'SI',
                        $ixOb !== null ? trim((string) ($row[$ixOb] ?? '')) : null,
                    ]);
                    $n++;
                }
            } elseif ($tabla === 'empleado_descanso') {
                $ixEmp = $indiceColumna($headers, ['ID_Empleado', 'id_empleado']);
                $ixTipo = $indiceColumna($headers, ['Tipo', 'tipo', 'Motivo']);
                $ixIni = $indiceColumna($headers, ['Fecha_Inicio', 'fecha_inicio', 'Desde', 'Inicio']);
                $ixFin = $indiceColumna($headers, ['Fecha_Fin', 'fecha_fin', 'Hasta', 'Fin']);
                $ixOb = $indiceColumna($headers, ['Observaciones', 'observaciones']);
                $ixId = $indiceColumna($headers, ['ID_Descanso', 'id_descanso']);
                $ixAnio = $indiceColumna($headers, ['Anio', 'anio', 'Año', 'Ano', 'Year']);
                $ixSem = $indiceColumna($headers, ['Numero_Semana', 'numero_semana', 'Semana', 'No_Semana', 'N_Semana']);
                if ($ixEmp === null) {
                    throw new RuntimeException("$archivo: falta columna ID_Empleado.");
                }
                $sql = 'INSERT IGNORE INTO empleado_descanso (ID_Descanso,ID_Empleado,Tipo,Fecha_Inicio,Fecha_Fin,Observaciones,Anio,Numero_Semana) VALUES (?,?,?,?,?,?,?,?)';
                $st = $pdo->prepare($sql);
                for ($i = 1; $i < count($data); $i++) {
                    $row = $data[$i];
                    if (!isset($row[$ixEmp]) || trim((string) $row[$ixEmp]) === '') {
                        continue;
                    }
                    $idD = ($ixId !== null && trim((string) ($row[$ixId] ?? '')) !== '')
                        ? trim((string) $row[$ixId])
                        : $generarIdNegocio();
                    $anioVal = null;
                    $semVal = null;
                    if ($ixAnio !== null && trim((string) ($row[$ixAnio] ?? '')) !== '') {
                        $anioVal = (int) $row[$ixAnio];
                    }
                    if ($ixSem !== null && trim((string) ($row[$ixSem] ?? '')) !== '') {
                        $semVal = (int) $row[$ixSem];
                    }
                    $st->execute([
                        $idD,
                        trim((string) $row[$ixEmp]),
                        $ixTipo !== null ? trim((string) ($row[$ixTipo] ?? '')) : null,
                        $ixIni !== null ? trim((string) ($row[$ixIni] ?? '')) : null,
                        $ixFin !== null ? trim((string) ($row[$ixFin] ?? '')) : null,
                        $ixOb !== null ? trim((string) ($row[$ixOb] ?? '')) : null,
                        $anioVal ?: null,
                        $semVal ?: null,
                    ]);
                    $n++;
                }
            } else {
                $ixEmp = $indiceColumna($headers, ['ID_Empleado', 'id_empleado']);
                $ixDia = $indiceColumna($headers, ['Dia_Semana', 'dia_semana', 'Dia', 'dia']);
                $ixHe = $indiceColumna($headers, ['Hora_Entrada', 'hora_entrada', 'Entrada']);
                $ixHs = $indiceColumna($headers, ['Hora_Salida', 'hora_salida', 'Salida']);
                $ixTu = $indiceColumna($headers, ['Turno', 'turno']);
                $ixAct = $indiceColumna($headers, ['Actividad', 'actividad']);
                $ixPla = $indiceColumna($headers, ['Planta', 'planta']);
                $ixProd = $indiceColumna($headers, ['Producto', 'producto']);
                $ixOb = $indiceColumna($headers, ['Observaciones', 'observaciones']);
                $ixPr = $indiceColumna($headers, ['ID_Programacion', 'id_programacion']);
                $ixAnio = $indiceColumna($headers, ['Anio', 'anio', 'Año', 'Ano', 'Year']);
                $ixSem = $indiceColumna($headers, ['Numero_Semana', 'numero_semana', 'Semana', 'No_Semana', 'N_Semana']);
                if ($ixEmp === null) {
                    throw new RuntimeException("$archivo: falta columna ID_Empleado.");
                }
                $sql = 'INSERT IGNORE INTO empleado_programacion (ID_Programacion,ID_Empleado,Dia_Semana,Hora_Entrada,Hora_Salida,Turno,Actividad,Planta,Producto,Observaciones,Anio,Numero_Semana) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
                $st = $pdo->prepare($sql);
                for ($i = 1; $i < count($data); $i++) {
                    $row = $data[$i];
                    if (!isset($row[$ixEmp]) || trim((string) $row[$ixEmp]) === '') {
                        continue;
                    }
                    $idP = ($ixPr !== null && trim((string) ($row[$ixPr] ?? '')) !== '')
                        ? trim((string) $row[$ixPr])
                        : $generarIdNegocio();
                    $anioVal = null;
                    $semVal = null;
                    if ($ixAnio !== null && trim((string) ($row[$ixAnio] ?? '')) !== '') {
                        $anioVal = (int) $row[$ixAnio];
                    }
                    if ($ixSem !== null && trim((string) ($row[$ixSem] ?? '')) !== '') {
                        $semVal = (int) $row[$ixSem];
                    }
                    $st->execute([
                        $idP,
                        trim((string) $row[$ixEmp]),
                        $ixDia !== null ? trim((string) ($row[$ixDia] ?? '')) : null,
                        $ixHe !== null ? trim((string) ($row[$ixHe] ?? '')) : null,
                        $ixHs !== null ? trim((string) ($row[$ixHs] ?? '')) : null,
                        $ixTu !== null ? trim((string) ($row[$ixTu] ?? '')) : null,
                        $ixAct !== null ? trim((string) ($row[$ixAct] ?? '')) : null,
                        $ixPla !== null ? trim((string) ($row[$ixPla] ?? '')) : null,
                        $ixProd !== null ? trim((string) ($row[$ixProd] ?? '')) : null,
                        $ixOb !== null ? trim((string) ($row[$ixOb] ?? '')) : null,
                        $anioVal ?: null,
                        $semVal ?: null,
                    ]);
                    $n++;
                }
            }
            $pdo->commit();
            $mensajes[] = "$archivo → $tabla: $n filas procesadas (INSERT IGNORE).";
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errores[] = $e->getMessage();
        }
    }

    return ['mensajes' => $mensajes, 'errores' => $errores];
}

/**
 * Importa desde …/manejo de empleados/Descansos/: descansos.csv y programacion_semana.csv
 */
function importarDescansosDashboardDesdeCarpeta(PDO $pdo, string $baseManejoEmpleados): array
{
    $sub = $baseManejoEmpleados . DIRECTORY_SEPARATOR . 'Descansos';

    return importarEmpleadosDesdeCarpeta($pdo, $sub, [
        'descansos.csv' => 'empleado_descanso',
        'programacion_semana.csv' => 'empleado_programacion',
    ]);
}

/**
 * Importa CSV tipo Prog_15.csv desde …/manejo de empleados/programacion/
 * El número de semana ISO se toma del nombre del archivo (15 → semana 15).
 * Columnas iguales que 03_programacion.csv / programacion_semana.csv.
 *
 * @return array{mensajes: string[], errores: string[]}
 */
function importarArchivosProgSemanaDesdeCarpeta(PDO $pdo, string $baseManejoEmpleados, int $anioPorDefecto = 2026): array
{
    $mensajes = [];
    $errores = [];
    $dir = $baseManejoEmpleados . DIRECTORY_SEPARATOR . 'programacion';
    if (!is_dir($dir)) {
        $mensajes[] = 'Carpeta programacion/ no encontrada (opcional).';

        return ['mensajes' => $mensajes, 'errores' => $errores];
    }

    $normalizarEncabezado = static function (string $h): string {
        $h = trim($h);
        if (str_starts_with($h, "\xEF\xBB\xBF")) {
            $h = substr($h, 3);
        }

        return mb_strtolower(str_replace([' ', '-'], '_', $h));
    };

    $leerCsv = static function (string $path): array {
        $fp = fopen($path, 'r');
        if (!$fp) {
            return [];
        }
        $rows = [];
        while (($r = fgetcsv($fp)) !== false) {
            $rows[] = $r;
        }
        fclose($fp);

        return $rows;
    };

    $indiceColumna = static function (array $headers, array $aliases) use ($normalizarEncabezado): ?int {
        $norm = array_map($normalizarEncabezado, $headers);
        foreach ($aliases as $a) {
            $na = $normalizarEncabezado($a);
            $i = array_search($na, $norm, true);
            if ($i !== false) {
                return (int) $i;
            }
        }

        return null;
    };

    $generarIdNegocio = static function (): string {
        return substr(bin2hex(random_bytes(4)), 0, 8);
    };

    $archivos = glob($dir . DIRECTORY_SEPARATOR . 'Prog_*.csv') ?: [];

    foreach ($archivos as $ruta) {
        $base = basename($ruta);
        if (!preg_match('/^Prog_(\d+)\.csv$/i', $base, $mm)) {
            continue;
        }

        $semanaArchivo = (int) $mm[1];
        $data = $leerCsv($ruta);
        if (count($data) < 2) {
            $errores[] = "$base: sin datos.";

            continue;
        }
        $headers = $data[0];
        $ixEmp = $indiceColumna($headers, ['ID_Empleado', 'id_empleado']);
        $ixDia = $indiceColumna($headers, ['Dia_Semana', 'dia_semana', 'Dia', 'dia']);
        $ixHe = $indiceColumna($headers, ['Hora_Entrada', 'hora_entrada', 'Entrada']);
        $ixHs = $indiceColumna($headers, ['Hora_Salida', 'hora_salida', 'Salida']);
        $ixTu = $indiceColumna($headers, ['Turno', 'turno']);
        $ixAct = $indiceColumna($headers, ['Actividad', 'actividad']);
        $ixPla = $indiceColumna($headers, ['Planta', 'planta']);
        $ixProd = $indiceColumna($headers, ['Producto', 'producto']);
        $ixOb = $indiceColumna($headers, ['Observaciones', 'observaciones']);
        $ixPr = $indiceColumna($headers, ['ID_Programacion', 'id_programacion']);
        $ixAnio = $indiceColumna($headers, ['Anio', 'anio', 'Año', 'Ano', 'Year']);
        $ixSem = $indiceColumna($headers, ['Numero_Semana', 'numero_semana', 'Semana', 'No_Semana', 'N_Semana']);
        if ($ixEmp === null) {
            $errores[] = "$base: falta columna ID_Empleado.";

            continue;
        }

        $sql = 'INSERT IGNORE INTO empleado_programacion (ID_Programacion,ID_Empleado,Dia_Semana,Hora_Entrada,Hora_Salida,Turno,Actividad,Planta,Producto,Observaciones,Anio,Numero_Semana) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
        $n = 0;
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare($sql);
            for ($i = 1; $i < count($data); $i++) {
                $row = $data[$i];
                if (!isset($row[$ixEmp]) || trim((string) $row[$ixEmp]) === '') {
                    continue;
                }
                $idP = ($ixPr !== null && trim((string) ($row[$ixPr] ?? '')) !== '')
                    ? trim((string) $row[$ixPr])
                    : $generarIdNegocio();
                $anioVal = $anioPorDefecto;
                if ($ixAnio !== null && trim((string) ($row[$ixAnio] ?? '')) !== '') {
                    $anioVal = (int) $row[$ixAnio];
                }
                $semVal = $semanaArchivo;
                if ($ixSem !== null && trim((string) ($row[$ixSem] ?? '')) !== '') {
                    $semVal = (int) $row[$ixSem];
                }
                $st->execute([
                    $idP,
                    trim((string) $row[$ixEmp]),
                    $ixDia !== null ? trim((string) ($row[$ixDia] ?? '')) : null,
                    $ixHe !== null ? trim((string) ($row[$ixHe] ?? '')) : null,
                    $ixHs !== null ? trim((string) ($row[$ixHs] ?? '')) : null,
                    $ixTu !== null ? trim((string) ($row[$ixTu] ?? '')) : null,
                    $ixAct !== null ? trim((string) ($row[$ixAct] ?? '')) : null,
                    $ixPla !== null ? trim((string) ($row[$ixPla] ?? '')) : null,
                    $ixProd !== null ? trim((string) ($row[$ixProd] ?? '')) : null,
                    $ixOb !== null ? trim((string) ($row[$ixOb] ?? '')) : null,
                    $anioVal,
                    $semVal,
                ]);
                $n++;
            }
            $pdo->commit();
            $mensajes[] = "$base → empleado_programacion: $n filas procesadas (INSERT IGNORE; semana $semanaArchivo).";
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errores[] = $base . ': ' . $e->getMessage();
        }
    }

    if ($mensajes === [] && $errores === []) {
        $mensajes[] = 'programacion/: no se encontraron archivos Prog_N.csv.';
    }

    return ['mensajes' => $mensajes, 'errores' => $errores];
}

/**
 * Rutas candidatas para logimeat_personal_UTF8.csv (se usa la primera que exista y sea legible).
 * Incluye raíz del proyecto, carpeta padre y carpeta de datos de empleados.
 *
 * @return list<string>
 */
function rutas_logimeat_personal_utf8_csv(): array
{
    $app = dirname(__DIR__);

    return [
        $app . DIRECTORY_SEPARATOR . 'logimeat_personal_UTF8.csv',
        dirname($app) . DIRECTORY_SEPARATOR . 'logimeat_personal_UTF8.csv',
        $app . DIRECTORY_SEPARATOR . 'logimeat_datos' . DIRECTORY_SEPARATOR . 'manejo de empleados' . DIRECTORY_SEPARATOR . 'logimeat_personal_UTF8.csv',
    ];
}

/** Primera ruta candidata donde existe el archivo, o null. */
function ruta_logimeat_personal_utf8_csv(): ?string
{
    foreach (rutas_logimeat_personal_utf8_csv() as $p) {
        if (is_readable($p)) {
            return $p;
        }
    }

    return null;
}

/** Quita sufijos tipo «.0» típicos de exportación Excel en CSV. */
function normalizar_valor_csv_excel(string $raw): string
{
    $t = trim($raw);
    if ($t === '') {
        return '';
    }
    if (preg_match('/^-?\d+\.0+$/', $t)) {
        return explode('.', $t, 2)[0];
    }

    return $t;
}

/**
 * Importa empleados desde logimeat_personal_UTF8.csv: la cédula normalizada es ID_Empleado y Numero_Documento.
 * Usa INSERT IGNORE: solo crea registros cuya cédula aún no exista. No borra ni actualiza empleados existentes
 * (el ID de negocio no cambia), de modo que filas en empleado_descanso / empleado_programacion que apunten a ese ID no se ven afectadas.
 *
 * @return array{mensajes: string[], errores: string[]}
 */
function importarEmpleadosDesdeLogimeatPersonalUtf8(PDO $pdo, ?string $path = null): array
{
    $mensajes = [];
    $errores = [];
    $path = $path ?? ruta_logimeat_personal_utf8_csv();
    if ($path === null || !is_readable($path)) {
        $mensajes[] = 'logimeat_personal_UTF8.csv: no encontrado. Coloque el archivo en una de estas rutas: ' . implode(' | ', rutas_logimeat_personal_utf8_csv());

        return ['mensajes' => $mensajes, 'errores' => $errores];
    }

    $fp = fopen($path, 'r');
    if ($fp === false) {
        return ['mensajes' => [], 'errores' => ['No se pudo abrir: ' . $path]];
    }
    $data = [];
    while (($r = fgetcsv($fp)) !== false) {
        $data[] = $r;
    }
    fclose($fp);

    if (count($data) < 2) {
        return ['mensajes' => [], 'errores' => ['logimeat_personal_UTF8.csv: sin datos.']];
    }

    $normalizarEncabezado = static function (string $h): string {
        $h = trim($h);
        if (str_starts_with($h, "\xEF\xBB\xBF")) {
            $h = substr($h, 3);
        }

        return mb_strtolower(str_replace([' ', '-'], '_', $h));
    };

    $headers = array_map($normalizarEncabezado, $data[0]);
    $map = [];
    foreach ($headers as $i => $name) {
        $map[$name] = $i;
    }

    foreach (['id_cedula', 'apellidos_nombre'] as $req) {
        if (!isset($map[$req])) {
            $errores[] = 'logimeat_personal_UTF8.csv: falta columna «' . $req . '».';

            return ['mensajes' => [], 'errores' => $errores];
        }
    }

    $ixId = $map['id_cedula'];
    $ixNom = $map['apellidos_nombre'];
    $ixLugar = $map['lugar_expedicion'] ?? null;
    $ixFe = $map['fecha_expedicion'] ?? null;
    $ixDep = $map['departamento'] ?? null;
    $ixArea = $map['area'] ?? null;
    $ixFi = $map['fecha_ingreso'] ?? null;
    $ixSexo = $map['sexo'] ?? null;
    $ixRh = $map['rh'] ?? null;
    $ixCel = $map['celular'] ?? null;
    $ixEps = $map['eps'] ?? null;
    $ixEst = $map['estado'] ?? null;

    // Solo filas nuevas por ID_Empleado (cédula); no modifica PK de negocio ni tablas vinculadas por ID_Empleado.
    $sql = 'INSERT IGNORE INTO empleado (ID_Empleado,Tipo_Documento,Numero_Documento,Nombre_Completo,Cargo,Area,Telefono,Email,Fecha_Ingreso,Activo,Observaciones) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
    $st = $pdo->prepare($sql);

    $insertados = 0;
    $omitidos = 0;

    try {
        $pdo->beginTransaction();
        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            $idRaw = isset($row[$ixId]) ? trim((string) $row[$ixId]) : '';
            $id = normalizar_valor_csv_excel($idRaw);
            if ($id === '') {
                continue;
            }
            $nombre = isset($row[$ixNom]) ? trim((string) $row[$ixNom]) : '';
            if ($nombre === '') {
                continue;
            }

            $cargo = $ixDep !== null && isset($row[$ixDep]) ? trim((string) $row[$ixDep]) : '';
            $area = $ixArea !== null && isset($row[$ixArea]) ? trim((string) $row[$ixArea]) : '';
            $tel = '';
            if ($ixCel !== null && isset($row[$ixCel])) {
                $tel = normalizar_valor_csv_excel(trim((string) $row[$ixCel]));
            }

            $fechaIng = null;
            if ($ixFi !== null && isset($row[$ixFi]) && trim((string) $row[$ixFi]) !== '') {
                $fechaIng = trim((string) $row[$ixFi]);
            }

            $estado = $ixEst !== null && isset($row[$ixEst]) ? strtoupper(trim((string) $row[$ixEst])) : '';
            $activo = str_contains($estado, 'ACTIV') ? 'SI' : 'NO';

            $obs = [];
            if ($ixLugar !== null && isset($row[$ixLugar]) && trim((string) $row[$ixLugar]) !== '') {
                $obs[] = 'Expedición: ' . trim((string) $row[$ixLugar]);
            }
            if ($ixFe !== null && isset($row[$ixFe]) && trim((string) $row[$ixFe]) !== '') {
                $obs[] = 'Fecha exp. doc.: ' . trim((string) $row[$ixFe]);
            }
            if ($ixSexo !== null && isset($row[$ixSexo]) && trim((string) $row[$ixSexo]) !== '') {
                $obs[] = 'Sexo: ' . trim((string) $row[$ixSexo]);
            }
            if ($ixRh !== null && isset($row[$ixRh]) && trim((string) $row[$ixRh]) !== '') {
                $obs[] = 'Rh: ' . trim((string) $row[$ixRh]);
            }
            if ($ixEps !== null && isset($row[$ixEps]) && trim((string) $row[$ixEps]) !== '') {
                $obs[] = 'EPS: ' . trim((string) $row[$ixEps]);
            }
            $obsStr = implode(' | ', $obs);

            $st->execute([
                $id,
                'CC',
                $id,
                $nombre,
                $cargo !== '' ? $cargo : null,
                $area !== '' ? $area : null,
                $tel !== '' ? $tel : null,
                null,
                $fechaIng,
                $activo,
                $obsStr !== '' ? $obsStr : null,
            ]);
            if ($st->rowCount() > 0) {
                $insertados++;
            } else {
                $omitidos++;
            }
        }
        $pdo->commit();
        $mensajes[] = "logimeat_personal_UTF8.csv ({$path}): {$insertados} nuevos (ID_Empleado = cédula); {$omitidos} ya existían (INSERT IGNORE; no se alteran relaciones).";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errores[] = 'logimeat_personal_UTF8.csv: ' . $e->getMessage();
    }

    return ['mensajes' => $mensajes, 'errores' => $errores];
}
