<?php
/* Cierre de actividades sin registros durante turnos consecutivos. */

function tm_turno_es_noche(string $turno): bool
{
    $turno = strtoupper(trim($turno));
    return $turno === 'N' || $turno === 'NOCHE';
}

function tm_fin_turno_nave(string $fecha, string $turno): ?DateTimeImmutable
{
    $base = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    if (!$base) return null;
    return tm_turno_es_noche($turno)
        ? $base->modify('+1 day')->setTime(7, 0)
        : $base->setTime(19, 0);
}

/**
 * Cierra BERTH tras dos turnos ausentes y YARD tras cuatro. Devuelve la cantidad
 * total de actividades cerradas; solo el cierre de BERTH finaliza la nave.
 */
function tm_auto_cerrar_naves_inactivas(PDO $pdo, ?DateTimeImmutable $ahora = null): int
{
    $ahora = $ahora ?: new DateTimeImmutable('now');
    $rows = $pdo->query(
        "SELECT id, nave_id, fecha_turno, turno, status_act
           FROM tallyman_registros
          WHERE ubicacion_tipo='BERTH' AND nave_id IS NOT NULL
          ORDER BY nave_id, fecha_turno DESC,
                   CASE WHEN UPPER(turno) IN ('N','NOCHE') THEN 1 ELSE 0 END DESC,
                   id DESC"
    )->fetchAll();

    $ultimos = [];
    foreach ($rows as $row) {
        $naveId = (int)$row['nave_id'];
        if (!isset($ultimos[$naveId])) $ultimos[$naveId] = $row;
    }

    $cerrarRegistro = $pdo->prepare(
        "UPDATE tallyman_registros
            SET status_act='Culminado', estado_pos='FINISH'
          WHERE id=? AND status_act <> 'Culminado'"
    );
    $cerrarNave = $pdo->prepare(
        "UPDATE naves SET estado='Finalizada', etd=?, updated_at=NOW() WHERE id=?"
    );
    $cerradas = 0;

    foreach ($ultimos as $registro) {
        if ($registro['status_act'] === 'Culminado') continue;
        $fin = tm_fin_turno_nave($registro['fecha_turno'], $registro['turno']);
        if (!$fin || $ahora < $fin->modify('+24 hours')) continue;

        $fechaCierre = $fin->format('Y-m-d H:i:s');
        $cerrarRegistro->execute([(int)$registro['id']]);
        if ($cerrarRegistro->rowCount() !== 1) continue;
        $cerrarNave->execute([$fechaCierre, (int)$registro['nave_id']]);
        $cerradas++;
    }

    $rowsYard = $pdo->query(
        "SELECT id, nave_id, nave_patio, fecha_turno, turno, status_act
           FROM tallyman_registros
          WHERE ubicacion_tipo='YARD' AND (nave_id IS NOT NULL OR (nave_patio IS NOT NULL AND nave_patio <> ''))
          ORDER BY fecha_turno DESC,
                   CASE WHEN UPPER(turno) IN ('N','NOCHE') THEN 1 ELSE 0 END DESC,
                   id DESC"
    )->fetchAll();
    $ultimosYard = [];
    foreach ($rowsYard as $row) {
        $clave = $row['nave_patio'] !== null && $row['nave_patio'] !== ''
            ? 'p:' . $row['nave_patio']
            : 'n:' . (int)$row['nave_id'];
        if (!isset($ultimosYard[$clave])) $ultimosYard[$clave] = $row;
    }

    foreach ($ultimosYard as $registro) {
        if ($registro['status_act'] === 'Culminado') continue;
        $fin = tm_fin_turno_nave($registro['fecha_turno'], $registro['turno']);
        if (!$fin || $ahora < $fin->modify('+48 hours')) continue;

        $cerrarRegistro->execute([(int)$registro['id']]);
        if ($cerrarRegistro->rowCount() === 1) $cerradas++;
    }

    return $cerradas;
}
