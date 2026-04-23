<?php
/**
 * Importa datos desde C:\proyectos\logimeat_datos (SQL generados desde Excel).
 * Asigna id_interno consecutivo (1..n) según el orden de filas del archivo.
 * Uso (CLI): php migrar_datos.php
 * Opciones: php migrar_datos.php --dry-run
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/conexion.php';

/** @var PDO $pdo */

$dryRun = in_array('--dry-run', $argv ?? [], true);

$datosDir = dirname($baseDir) . DIRECTORY_SEPARATOR . 'logimeat_datos';

$jobs = [
    ['nivel 1' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Departamento_Departamento.sql', 'departamento'],
    ['nivel 1' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Municipio_Municipio.sql', 'municipio'],
    ['nivel 1' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Estado_Estado.sql', 'estado'],
    ['nivel 1' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Estado_Actividad_Estado_Actividad.sql', 'estado_actividad'],
    ['nivel 1' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Medio de Comunicacion_Medio de Comunicacion.sql', 'medio_de_comunicacion'],
    ['nivel 1' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Nivel_Nivel.sql', 'nivel'],
    ['nivel 2' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Actividad_Actividad.sql', 'actividad'],
    ['nivel 2' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Corte_Corte.sql', 'corte'],
    ['nivel 2' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Planta_Planta.sql', 'planta'],
    ['nivel 2' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Tipo de Cuarteo_Tipo de Cuarteo.sql', 'tipo_de_cuarteo'],
    ['nivel 2' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Producto_Producto.sql', 'producto'],
    ['nivel 3' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'OPL_OPL.sql', 'opl'],
    ['nivel 3' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Logisticos_Logisticos.sql', 'logisticos'],
    ['nivel 3' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Solicitante_Solicitante.sql', 'solicitante'],
    ['nivel 3' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Conductor_Conductor.sql', 'conductor'],
    ['nivel 3' . DIRECTORY_SEPARATOR . 'Converted files' . DIRECTORY_SEPARATOR . 'Vehiculo_Vehiculo.sql', 'vehiculo'],
    ['nivel 4' . DIRECTORY_SEPARATOR . 'Clientes.sql', 'clientes'],
    ['nivel 4' . DIRECTORY_SEPARATOR . 'User.sql', 'user'],
    ['nivel 5' . DIRECTORY_SEPARATOR . 'Programacion.sql', 'programacion'],
];

function stripCreateTableBlocks(string $sql): string
{
    return preg_replace('/CREATE\s+TABLE\s+[^;]+;/is', '', $sql) ?? '';
}

function parseInsertFile(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se puede leer: $path");
    }
    $sql = trim(stripCreateTableBlocks($raw));
    if ($sql === '') {
        throw new RuntimeException("Archivo vacío tras quitar CREATE: $path");
    }
    if (!preg_match('/INSERT\s+INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*(.*)/is', $sql, $m)) {
        throw new RuntimeException("No se encontró INSERT INTO en: $path");
    }
    $sourceTable = $m[1];
    $colsPart = $m[2];
    $valuesPart = trim($m[3]);
    if (str_ends_with($valuesPart, ';')) {
        $valuesPart = substr($valuesPart, 0, -1);
    }
    $columns = array_map('trim', explode(',', $colsPart));
    $columns = array_map(fn ($c) => trim($c, '` '), $columns);
    $rows = parseValuesTuples($valuesPart);
    return ['source_table' => $sourceTable, 'columns' => $columns, 'rows' => $rows];
}

function parseValuesTuples(string $valuesPart): array
{
    $rows = [];
    $n = strlen($valuesPart);
    $i = 0;
    while ($i < $n) {
        while ($i < $n && ctype_space($valuesPart[$i])) {
            $i++;
        }
        if ($i >= $n) {
            break;
        }
        if ($valuesPart[$i] !== '(') {
            throw new RuntimeException("Se esperaba '(' en posición $i");
        }
        $tupleEnd = findMatchingParen($valuesPart, $i);
        $tupleStr = substr($valuesPart, $i, $tupleEnd - $i + 1);
        $inner = substr($tupleStr, 1, -1);
        $rows[] = splitTopLevelComma($inner);
        $i = $tupleEnd + 1;
        while ($i < $n && ctype_space($valuesPart[$i])) {
            $i++;
        }
        if ($i < $n && $valuesPart[$i] === ',') {
            $i++;
        }
    }
    return $rows;
}

function findMatchingParen(string $s, int $start): int
{
    $depth = 0;
    $n = strlen($s);
    $inQuote = false;
    $quote = '';
    for ($i = $start; $i < $n; $i++) {
        $c = $s[$i];
        if ($inQuote) {
            if ($c === $quote) {
                if ($quote === "'" && $i + 1 < $n && $s[$i + 1] === "'") {
                    $i++;
                    continue;
                }
                $inQuote = false;
            }
            continue;
        }
        if ($c === "'" || $c === '"') {
            $inQuote = true;
            $quote = $c;
            continue;
        }
        if ($c === '(') {
            $depth++;
        } elseif ($c === ')') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }
    throw new RuntimeException('Paréntesis sin cerrar');
}

/** @return list<scalar|null> */
function splitTopLevelComma(string $inner): array
{
    $parts = [];
    $buf = '';
    $depth = 0;
    $inQuote = false;
    $quote = '';
    $len = strlen($inner);
    for ($i = 0; $i < $len; $i++) {
        $c = $inner[$i];
        if ($inQuote) {
            if ($c === $quote) {
                if ($quote === "'" && $i + 1 < $len && $inner[$i + 1] === "'") {
                    $buf .= "''";
                    $i++;
                    continue;
                }
                $buf .= $c;
                $inQuote = false;
                $quote = '';
            } else {
                $buf .= $c;
            }
            continue;
        }
        if ($c === "'" || $c === '"') {
            $inQuote = true;
            $quote = $c;
            $buf .= $c;
            continue;
        }
        if ($c === '(') {
            $depth++;
            $buf .= $c;
            continue;
        }
        if ($c === ')') {
            $depth--;
            $buf .= $c;
            continue;
        }
        if ($c === ',' && $depth === 0) {
            $parts[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if ($buf !== '') {
        $parts[] = trim($buf);
    }
    return array_map('parseSqlScalar', $parts);
}

function parseSqlScalar(string $v): string|int|float|null
{
    $v = trim($v);
    if ($v === '' || strtoupper($v) === 'NULL') {
        return null;
    }
    if (strlen($v) >= 2 && $v[0] === "'" && substr($v, -1) === "'") {
        return str_replace("''", "'", substr($v, 1, -1));
    }
    if (is_numeric($v)) {
        return str_contains($v, '.') || str_contains(strtolower($v), 'e') ? (float) $v : (int) $v;
    }
    return $v;
}

/** ID_Programacion vacío o repetido: AUTO_n o sufijo __2 */
function dedupeProgramacionIds(array $columns, array $rows): array
{
    $idx = array_search('ID_Programacion', $columns, true);
    if ($idx === false) {
        return $rows;
    }
    $seen = [];
    foreach ($rows as $k => $row) {
        $id = normalizeValueForDb($row[$idx]);
        if ($id === null || $id === '') {
            $rows[$k][$idx] = 'AUTO_' . ($k + 1);
            $seen[$rows[$k][$idx]] = 1;
            continue;
        }
        if (!isset($seen[$id])) {
            $seen[$id] = 1;
            continue;
        }
        $seen[$id]++;
        $rows[$k][$idx] = $id . '__' . $seen[$id];
    }
    return $rows;
}

/** Evita violación de UNIQUE: segundo y siguientes usan ID_Cliente__2, __3, ... */
function dedupeClienteIds(array $columns, array $rows): array
{
    $idx = array_search('ID_Cliente', $columns, true);
    if ($idx === false) {
        return $rows;
    }
    $seen = [];
    foreach ($rows as $k => $row) {
        $id = normalizeValueForDb($row[$idx]);
        if ($id === null || $id === '') {
            $id = 'SIN_ID_' . ($k + 1);
            $rows[$k][$idx] = $id;
            $seen[$id] = 1;
            continue;
        }
        if (!isset($seen[$id])) {
            $seen[$id] = 1;
            continue;
        }
        $seen[$id]++;
        $rows[$k][$idx] = $id . '__' . $seen[$id];
    }
    return $rows;
}

/** Ajusta filas para logisticos: añade ID_Logistico desde Identificacion si falta. */
function augmentLogisticosRows(array $columns, array $rows): array
{
    $hasId = in_array('ID_Logistico', $columns, true);
    if ($hasId) {
        return [$columns, $rows];
    }
    $idxIdent = array_search('Identificacion', $columns, true);
    if ($idxIdent === false) {
        throw new RuntimeException('logisticos: falta Identificacion para generar ID_Logistico');
    }
    $newCols = array_merge(['ID_Logistico'], $columns);
    $newRows = [];
    foreach ($rows as $r) {
        $ident = $r[$idxIdent] ?? null;
        $idLog = $ident !== null && $ident !== '' ? (string) $ident : uniqid('LOG_', true);
        $newRows[] = array_merge([$idLog], $r);
    }
    return [$newCols, $newRows];
}

/** Convierte valores a string para columnas VARCHAR en PDO (evita problemas de tipo). */
function normalizeValueForDb(mixed $v): ?string
{
    if ($v === null) {
        return null;
    }
    if (is_bool($v)) {
        return $v ? '1' : '0';
    }
    if (is_float($v) && floor($v) == $v) {
        return (string) (int) $v;
    }
    return (string) $v;
}

$truncateOrder = [
    'programacion', 'user', 'clientes', 'vehiculo', 'conductor', 'solicitante', 'logisticos',
    'opl', 'producto', 'tipo_de_cuarteo', 'planta', 'corte', 'actividad', 'nivel',
    'medio_de_comunicacion', 'estado_actividad', 'estado', 'municipio', 'departamento',
];

if (!$dryRun) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($truncateOrder as $tbl) {
        try {
            $pdo->exec("TRUNCATE TABLE `$tbl`");
        } catch (Throwable $e) {
            fwrite(STDERR, "TRUNCATE $tbl: " . $e->getMessage() . "\n");
            throw $e;
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

$totalRows = 0;

foreach ($jobs as [$rel, $targetTable]) {
    $path = $datosDir . DIRECTORY_SEPARATOR . $rel;
    if (!is_readable($path)) {
        fwrite(STDERR, "Omitido (no existe): $path\n");
        continue;
    }

    $parsed = parseInsertFile($path);
    $columns = $parsed['columns'];
    $rows = $parsed['rows'];

    if ($targetTable === 'logisticos') {
        [$columns, $rows] = augmentLogisticosRows($columns, $rows);
    }
    if ($targetTable === 'clientes') {
        $rows = dedupeClienteIds($columns, $rows);
    }
    if ($targetTable === 'programacion') {
        $rows = dedupeProgramacionIds($columns, $rows);
    }

    $placeholders = '(' . implode(',', array_fill(0, count($columns) + 1, '?')) . ')';
    $colList = '`id_interno`,' . implode(',', array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', $columns));
    $sql = "INSERT INTO `$targetTable` ($colList) VALUES $placeholders";

    $stmt = $pdo->prepare($sql);
    $n = 0;
    if (!$dryRun) {
        $pdo->beginTransaction();
    }
    try {
        foreach ($rows as $idx => $rowVals) {
            if (count($rowVals) !== count($columns)) {
                throw new RuntimeException(
                    "Fila $idx: columnas=" . count($columns) . " valores=" . count($rowVals) . " en $path"
                );
            }
            $idInterno = $idx + 1;
            $params = [$idInterno];
            foreach ($rowVals as $v) {
                $params[] = normalizeValueForDb($v);
            }
            if (!$dryRun) {
                $stmt->execute($params);
            }
            $n++;
        }
        if (!$dryRun) {
            $pdo->commit();
            $pdo->exec("ALTER TABLE `$targetTable` AUTO_INCREMENT = " . (count($rows) + 1));
        }
    } catch (Throwable $e) {
        if (!$dryRun && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $totalRows += $n;
    echo "OK $targetTable ($n filas) <- " . basename($path) . "\n";
}

echo $dryRun ? "Dry-run: $totalRows filas parseadas (sin escribir).\n" : "Listo: $totalRows filas importadas con id_interno 1..n por tabla.\n";
