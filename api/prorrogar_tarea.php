<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id     = isset($data['id']) ? (int)$data['id'] : 0;
$fecha2 = isset($data['fecha_limite_2']) ? trim((string)$data['fecha_limite_2']) : '';
$motivo = trim($data['motivo'] ?? '');
$retira = ($fecha2 === '' || $fecha2 === 'null');

if ($id <= 0) {
    echo json_encode(['success'=>false,'error'=>'ID de tarea inválido.']); exit;
}

$st = mysqli_prepare($conn, "SELECT * FROM tareas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

$permiso = tk_puede_prorrogar($row);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

$quien   = $_SESSION['user_name'] ?? 'Administrador';
$quienId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

mysqli_begin_transaction($conn);
try {
    if ($retira) {
        if ($row['fecha_limite_2'] === null) {
            mysqli_rollback($conn);
            echo json_encode(['success'=>false,'error'=>'Esta tarea no tiene una 2ª fecha que retirar.']); exit;
        }
        $st = mysqli_prepare($conn,
            "UPDATE tareas
                SET fecha_limite_2=NULL, prorroga_motivo=NULL,
                    prorroga_por=?, prorroga_por_id=?, prorroga_at=NOW()
              WHERE id=?");
        mysqli_stmt_bind_param($st, 'sii', $quien, $quienId, $id);
        if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
        mysqli_stmt_close($st);

        tk_historial($conn, $id, 'prorroga_retirada',
            'Se retira la 2ª fecha (' . $row['fecha_limite_2'] . '). '
          . 'La tarea vuelve a medirse contra ' . $row['fecha_limite'] . '.');

        mysqli_commit($conn);
        echo json_encode(['success'=>true,'fecha_limite_2'=>null,
                          'plazo_vigente'=>$row['fecha_limite']]);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $fecha2)) {
        mysqli_rollback($conn);
        echo json_encode(['success'=>false,'error'=>'2ª fecha inválida.']); exit;
    }
    if (strlen($fecha2) === 16) $fecha2 .= ':00';
    if (strtotime($fecha2) <= strtotime($row['fecha_limite'])) {
        mysqli_rollback($conn);
        echo json_encode(['success'=>false,
            'error'=>'La 2ª fecha debe ser posterior a la fecha límite original.']); exit;
    }
    if ($motivo === '') {
        mysqli_rollback($conn);
        echo json_encode(['success'=>false,
            'error'=>'Indica el motivo de la prórroga. Una prórroga sin motivo es indistinguible de un error de digitación.']); exit;
    }
    if (mb_strlen($motivo) > 255) $motivo = mb_substr($motivo, 0, 255);

    $st = mysqli_prepare($conn,
        "UPDATE tareas
            SET fecha_limite_2=?, prorroga_motivo=?,
                prorroga_por=?, prorroga_por_id=?, prorroga_at=NOW()
          WHERE id=?");
    mysqli_stmt_bind_param($st, 'sssii', $fecha2, $motivo, $quien, $quienId, $id);
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
    mysqli_stmt_close($st);

    $anterior = $row['fecha_limite_2'] !== null
              ? ' Sustituye a la 2ª fecha anterior (' . $row['fecha_limite_2'] . ').' : '';
    tk_historial($conn, $id, 'prorroga',
        'Nueva fecha de entrega: ' . $fecha2 . '. Motivo: ' . $motivo . '.' . $anterior);

    mysqli_commit($conn);
    echo json_encode(['success'=>true,'fecha_limite_2'=>$fecha2,'plazo_vigente'=>$fecha2]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo prorrogar: ' . $e->getMessage()]);
}
