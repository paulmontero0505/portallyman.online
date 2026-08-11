<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API PÚBLICA (sin login) · Identificar colaborador por DNI
   GET ?dni=12345678
   Solo devuelve los datos básicos del colaborador (para el módulo de
   Sugerencias Tallyman). A diferencia de buscar_colaborador.php, no
   depende de que exista un turno vigente.
   ═══════════════════════════════════════════════════════════════════════ */
require_once(__DIR__ . '/../../includes/db.php');

header('Content-Type: application/json');

$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';
if (!preg_match('/^\d{8}$/', $dni)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un DNI válido (8 dígitos).']); exit;
}

$stmt = mysqli_prepare($conn,
    "SELECT id, codigo, dni, nombre, funcion_principal
       FROM colaboradores WHERE dni = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $dni);
mysqli_stmt_execute($stmt);
$colab = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$colab) {
    echo json_encode(['success' => false, 'error' => 'No se encontró un colaborador activo con ese DNI.']); exit;
}

echo json_encode([
    'success'     => true,
    'colaborador' => [
        'id'      => (int)$colab['id'],
        'codigo'  => $colab['codigo'],
        'dni'     => $colab['dni'],
        'nombre'  => $colab['nombre'],
        'funcion' => $colab['funcion_principal'],
    ],
]);
