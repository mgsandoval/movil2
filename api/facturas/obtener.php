<?php
// api/facturas/obtener.php
// Devuelve un encabezado de factura con sus detalles.
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

// Encabezado
$stmt = $conexion->prepare("
    SELECT e.*, u.usuario_nombrecomp
    FROM tbl_factura_encabezado e
    LEFT JOIN tbl_usuario u ON e.usuario_id = u.usuario_id
    WHERE e.factura_id = ? LIMIT 1
");
$stmt->bind_param('i', $facturaId);
$stmt->execute();
$encabezado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$encabezado) {
    echo json_encode(['status' => 'warning', 'message' => 'Factura no encontrada', 'data' => null]);
    $conexion->close();
    exit();
}

// Detalles
$stmtDet = $conexion->prepare("
    SELECT * FROM tbl_factura_detalle WHERE factura_id = ? ORDER BY detalle_id
");
$stmtDet->bind_param('i', $facturaId);
$stmtDet->execute();
$resDet = $stmtDet->get_result();
$detalles = [];
while ($fila = $resDet->fetch_assoc()) { $detalles[] = $fila; }
$stmtDet->close();

echo json_encode([
    'status' => 'success',
    'data'   => ['encabezado' => $encabezado, 'detalles' => $detalles]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$conexion->close();
?>
