<?php
// api/facturas/eliminar.php
// Elimina una factura (los detalles se borran en cascada por la FK).
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido. Use POST.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['factura_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Falta factura_id']);
    exit();
}
$facturaId = (int)$input['factura_id'];

$conexion = Database::getInstance();
if (!$conexion) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo conectar a la base de datos.']);
    exit();
}

$stmt = $conexion->prepare("DELETE FROM tbl_factura_encabezado WHERE factura_id = ?");
$stmt->bind_param('i', $facturaId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar', 'error' => $stmt->error]);
    $stmt->close();
    $conexion->close();
    exit();
}

$afectadas = $stmt->affected_rows;
$stmt->close();
$conexion->close();

echo json_encode([
    'status'     => $afectadas > 0 ? 'success' : 'warning',
    'message'    => $afectadas > 0 ? 'Factura eliminada correctamente' : 'No se encontró la factura',
    'factura_id' => $facturaId
], JSON_UNESCAPED_UNICODE);
?>
