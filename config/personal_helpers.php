<?php
declare(strict_types=1);

/**
 * Utilidades para semana ISO, programación personal y cruces descanso/programación.
 */

function lm_sin_acentos(string $s): string
{
    static $map = [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        'Ñ' => 'N', 'ñ' => 'n',
    ];
    return strtr($s, $map);
}

function lm_normalizar_dia_semana(string $dia): string
{
    $d = mb_strtoupper(trim(lm_sin_acentos($dia)));
    return preg_replace('/\s+/', ' ', $d) ?? $d;
}

/** Lunes de la semana ISO (00:00:00). */
function lm_lunes_semana_iso(int $anio, int $numeroSemana): DateTimeImmutable
{
    $d = new DateTimeImmutable();
    $d = $d->setISODate($anio, $numeroSemana);
    return $d->setTime(0, 0, 0);
}

/**
 * Fechas concretas (una por día de turno) para una fila de programación.
 *
 * @return list<DateTimeImmutable>
 */
function lm_fechas_programacion_desde_dia(string $diaTexto, int $anio, int $numeroSemana): array
{
    $d = lm_normalizar_dia_semana($diaTexto);
    $lun = lm_lunes_semana_iso($anio, $numeroSemana);
    $map = [
        'LUNES' => 0,
        'MARTES' => 1,
        'MIERCOLES' => 2,
        'JUEVES' => 3,
        'VIERNES' => 4,
        'SABADO' => 5,
        'DOMINGO' => 6,
    ];
    if ($d === 'LUNES A VIERNES') {
        $out = [];
        for ($i = 0; $i < 5; $i++) {
            $out[] = $lun->modify('+' . $i . ' days');
        }
        return $out;
    }
    if (!isset($map[$d])) {
        return [];
    }
    return [$lun->modify('+' . $map[$d] . ' days')];
}

function lm_parse_fecha(?string $s): ?DateTimeImmutable
{
    if ($s === null) {
        return null;
    }
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
        $dt = DateTimeImmutable::createFromFormat('!d/m/Y', sprintf('%02d/%02d/%04d', (int) $m[1], (int) $m[2], (int) $m[3]));
        return $dt ?: null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        return $dt ?: null;
    }
    $ts = strtotime($s);
    if ($ts !== false) {
        $x = (new DateTimeImmutable())->setTimestamp($ts);
        return $x->setTime(0, 0, 0);
    }
    return null;
}

/**
 * Rango inclusivo; si falta fin, un solo día.
 *
 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}|null
 */
function lm_rango_descanso(?string $ini, ?string $fin): ?array
{
    $a = lm_parse_fecha($ini ?? '');
    if ($a === null) {
        return null;
    }
    $b = lm_parse_fecha($fin ?? '');
    if ($b === null) {
        $b = $a;
    }
    if ($b < $a) {
        $tmp = $a;
        $a = $b;
        $b = $tmp;
    }
    return [$a, $b];
}

function lm_solapan_fechas(DateTimeImmutable $a1, DateTimeImmutable $a2, DateTimeImmutable $b1, DateTimeImmutable $b2): bool
{
    $ad1 = $a1->format('Y-m-d');
    $ad2 = $a2->format('Y-m-d');
    $bd1 = $b1->format('Y-m-d');
    $bd2 = $b2->format('Y-m-d');

    return $ad1 <= $bd2 && $bd1 <= $ad2;
}

function lm_anio_semana_iso(DateTimeImmutable $d): array
{
    $w = (int) $d->format('o');
    $s = (int) $d->format('W');
    return [$w, $s];
}

/**
 * ¿La semana ISO [anio,semana] solapa el rango [ini,fin] (fechas inclusivas)?
 */
function lm_descanso_visible_en_semana(?string $fechaIni, ?string $fechaFin, int $anio, int $numeroSemana): bool
{
    $r = lm_rango_descanso($fechaIni, $fechaFin);
    if ($r === null) {
        return false;
    }
    [$iniD, $finD] = $r;
    $lun = lm_lunes_semana_iso($anio, $numeroSemana);
    $dom = $lun->modify('+6 days');
    return lm_solapan_fechas($iniD, $finD, $lun, $dom);
}

/** Incluir fila en tablero de una semana ISO (solape de fechas o coincidencia Anio/Semana). */
function lm_descanso_incluir_en_tablero(array $r, int $anio, int $numeroSemana): bool
{
    if (lm_descanso_visible_en_semana($r['Fecha_Inicio'] ?? null, $r['Fecha_Fin'] ?? null, $anio, $numeroSemana)) {
        return true;
    }
    $a = isset($r['Anio']) ? (int) $r['Anio'] : 0;
    $s = isset($r['Numero_Semana']) ? (int) $r['Numero_Semana'] : 0;

    return $a === $anio && $s === $numeroSemana;
}

/**
 * Fechas de programación cubiertas por una fila (para conflictos).
 *
 * @return list<DateTimeImmutable>
 */
function lm_fechas_cubiertas_por_fila_programacion(array $row): array
{
    $anio = (int) ($row['Anio'] ?? $row['anio'] ?? 0);
    $sem = (int) ($row['Numero_Semana'] ?? $row['numero_semana'] ?? 0);
    $dia = (string) ($row['Dia_Semana'] ?? $row['dia_semana'] ?? '');
    if ($anio < 2000 || $sem < 1 || $sem > 53 || trim($dia) === '') {
        return [];
    }
    return lm_fechas_programacion_desde_dia($dia, $anio, $sem);
}

/**
 * @return list<DateTimeImmutable>
 */
function lm_dias_en_rango(DateTimeImmutable $ini, DateTimeImmutable $fin): array
{
    $out = [];
    $c = $ini;
    while ($c <= $fin) {
        $out[] = $c;
        $c = $c->modify('+1 day');
    }
    return $out;
}

/**
 * Motivo si el empleado no puede asignarse ese día; null si libre.
 * Cruza descansos y programación (excluyendo filas por id_interno al editar).
 */
function lm_motivo_ocupacion_dia(
    PDO $pdo,
    string $idEmpleado,
    DateTimeImmutable $dia,
    ?int $excluirDescansoIdInterno,
    ?int $excluirProgIdInterno
): ?string {
    $dStr = $dia->format('Y-m-d');

    $st = $pdo->prepare('SELECT id_interno, Fecha_Inicio, Fecha_Fin FROM empleado_descanso WHERE ID_Empleado = ?');
    $st->execute([$idEmpleado]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $idi = (int) $r['id_interno'];
        if ($excluirDescansoIdInterno !== null && $idi === $excluirDescansoIdInterno) {
            continue;
        }
        $rg = lm_rango_descanso($r['Fecha_Inicio'] ?? null, $r['Fecha_Fin'] ?? null);
        if ($rg === null) {
            continue;
        }
        [$a, $b] = $rg;
        if ($dia >= $a && $dia <= $b) {
            return 'Tiene descanso o ausencia registrado este día.';
        }
    }

    $st2 = $pdo->prepare('SELECT id_interno, Dia_Semana, Anio, Numero_Semana FROM empleado_programacion WHERE ID_Empleado = ?');
    $st2->execute([$idEmpleado]);
    while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
        $ipi = (int) $r['id_interno'];
        if ($excluirProgIdInterno !== null && $ipi === $excluirProgIdInterno) {
            continue;
        }
        foreach (lm_fechas_cubiertas_por_fila_programacion($r) as $f) {
            if ($f->format('Y-m-d') === $dStr) {
                return 'Ya tiene turno de programación este día.';
            }
        }
    }

    return null;
}

/**
 * @return string|null Error humano o null si todo el rango está libre.
 */
function lm_validar_rango_descanso_libre(
    PDO $pdo,
    string $idEmpleado,
    DateTimeImmutable $ini,
    DateTimeImmutable $fin,
    ?int $excluirDescansoIdInterno
): ?string {
    foreach (lm_dias_en_rango($ini, $fin) as $dia) {
        $m = lm_motivo_ocupacion_dia($pdo, $idEmpleado, $dia, $excluirDescansoIdInterno, null);
        if ($m !== null) {
            return $m . ' (' . $dia->format('d/m/Y') . ')';
        }
    }
    return null;
}

/**
 * @return string|null Error si no puede asignarse programación en todas las fechas del día.
 */
function lm_validar_programacion_libre(
    PDO $pdo,
    string $idEmpleado,
    string $diaSemana,
    int $anio,
    int $numeroSemana,
    ?int $excluirDescansoIdInterno,
    ?int $excluirProgIdInterno
): ?string {
    $fechas = lm_fechas_programacion_desde_dia($diaSemana, $anio, $numeroSemana);
    if ($fechas === []) {
        return 'Día de la semana no reconocido. Use LUNES, MARTES, … o LUNES A VIERNES.';
    }
    foreach ($fechas as $dia) {
        $m = lm_motivo_ocupacion_dia($pdo, $idEmpleado, $dia, $excluirDescansoIdInterno, $excluirProgIdInterno);
        if ($m !== null) {
            return $m . ' (' . $dia->format('d/m/Y') . ')';
        }
    }
    return null;
}

/**
 * IDs de empleados activos que pueden usarse en programación para ese día/semana.
 *
 * @return list<array{ID_Empleado: string, Nombre_Completo: string}>
 */
function lm_empleados_disponibles_programacion(
    PDO $pdo,
    int $anio,
    int $numeroSemana,
    string $diaSemana,
    ?int $excluirProgIdInterno,
    ?string $forzarIdEmpleado
): array {
    $q = $pdo->query("SELECT ID_Empleado, Nombre_Completo FROM empleado WHERE Activo IS NULL OR UPPER(TRIM(COALESCE(Activo,''))) IN ('SI','S','1','TRUE','YES') ORDER BY Nombre_Completo");
    $rows = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
    $out = [];
    foreach ($rows as $e) {
        $id = (string) $e['ID_Empleado'];
        if ($forzarIdEmpleado !== null && $id === $forzarIdEmpleado) {
            $out[] = ['ID_Empleado' => $id, 'Nombre_Completo' => (string) $e['Nombre_Completo']];
            continue;
        }
        if (lm_validar_programacion_libre($pdo, $id, $diaSemana, $anio, $numeroSemana, null, $excluirProgIdInterno) === null) {
            $out[] = ['ID_Empleado' => $id, 'Nombre_Completo' => (string) $e['Nombre_Completo']];
        }
    }
    return $out;
}

/**
 * @return list<array{ID_Empleado: string, Nombre_Completo: string}>
 */
function lm_empleados_disponibles_descanso(
    PDO $pdo,
    DateTimeImmutable $ini,
    DateTimeImmutable $fin,
    ?int $excluirDescansoIdInterno,
    ?string $forzarIdEmpleado
): array {
    $q = $pdo->query("SELECT ID_Empleado, Nombre_Completo FROM empleado WHERE Activo IS NULL OR UPPER(TRIM(COALESCE(Activo,''))) IN ('SI','S','1','TRUE','YES') ORDER BY Nombre_Completo");
    $rows = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
    $out = [];
    foreach ($rows as $e) {
        $id = (string) $e['ID_Empleado'];
        if ($forzarIdEmpleado !== null && $id === $forzarIdEmpleado) {
            $out[] = ['ID_Empleado' => $id, 'Nombre_Completo' => (string) $e['Nombre_Completo']];
            continue;
        }
        if (lm_validar_rango_descanso_libre($pdo, $id, $ini, $fin, $excluirDescansoIdInterno) === null) {
            $out[] = ['ID_Empleado' => $id, 'Nombre_Completo' => (string) $e['Nombre_Completo']];
        }
    }
    return $out;
}
