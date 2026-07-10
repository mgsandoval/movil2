<?php
// api/facturas/listar.php
// Lista los encabezados de factura. Filtros opcionales: usuario_id, categoria, rango de fechas.
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

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$where  = [];
$params = [];
$tipos  = '';

if (!empty($input['usuario_id'])) { $where[] = 'e.usuario_id = ?';        $params[] = (int)$input['usuario_id']; $tipos .= 'i'; }
if (!empty($input['categoria']))  { $where[] = 'e.factura_categoria = ?'; $params[] = $input['categoria'];       $tipos .= 's'; }
if (!empty($input['fecha_inicio'])) { $where[] = 'DATE(e.factura_fecha) >= ?'; $params[] = $input['fecha_inicio']; $tipos .= 's'; }
if (!empty($input['fecha_fin']))    { $where[] = 'DATE(e.factura_fecha) <= ?'; $params[] = $input['fecha_fin'];    $tipos .= 's'; }

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT e.*, u.usuario_nombrecomp
    FROM tbl_factura_encabezado e
    LEFT JOIN tbl_usuario u ON e.usuario_id = u.usuario_id
    $sqlWhere
    ORDER BY e.factura_fecha DESC, e.factura_id DESC
    LIMIT 500
";

$conexion = Database::getInstance();
if (!$conexion) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo conectar a la base de datos.']);
    exit();
}

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al preparar la consulta', 'error' => $conexion->error]);
    exit();
}
if ($params) { $stmt->bind_param($tipos, ...$params); }

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar', 'error' => $stmt->error]);
    exit();
}

$resultado = $stmt->get_result();
$facturas = [];
while ($fila = $resultado->fetch_assoc()) { $facturas[] = $fila; }

echo json_encode([
    'status'  => 'success',
    'message' => 'Consulta realizada correctamente',
    'total'   => count($facturas),
    'data'    => $facturas
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$stmt->close();
$conexion->close();
?>
