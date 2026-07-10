<?php
// api/facturas/actualizar.php
// Actualiza el encabezado y reemplaza los detalles de una factura (en transacción).
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
if (!$input || !isset($input['factura_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Falta factura_id']);
    exit();
}

$facturaId     = (int)$input['factura_id'];
$numero        = $input['factura_numero']          ?? '';
$cai           = $input['factura_cai']             ?? '';
$rtn           = $input['factura_rtn_local']       ?? '';
$nombreLocal   = $input['factura_nombre_local']    ?? '';
$direccion     = $input['factura_direccion']       ?? '';
$fecha         = $input['factura_fecha']           ?? date('Y-m-d H:i:s');
$medioPago     = $input['factura_medio_pago']      ?? 'Efectivo';
$subtotal      = (float)($input['factura_subtotal']        ?? 0);
$descuentos    = (float)($input['factura_descuentos']      ?? 0);
$exonerado     = (float)($input['factura_exonerado']       ?? 0);
$exento        = (float)($input['factura_exento']          ?? 0);
$gravado15     = (float)($input['factura_gravado15']       ?? 0);
$gravado18     = (float)($input['factura_gravado18']       ?? 0);
$isv15         = (float)($input['factura_isv15']           ?? 0);
$isv18         = (float)($input['factura_isv18']           ?? 0);
$gratificacion = (float)($input['factura_gratificacion']   ?? 0);
$total         = (float)($input['factura_total']           ?? 0);
$totalArt      = (int)  ($input['factura_total_articulos'] ?? 0);
$pago          = (float)($input['factura_pago']            ?? 0);
$cambio        = (float)($input['factura_cambio']          ?? 0);
$categoria     = $input['factura_categoria'] ?? 'General';
$detalles      = isset($input['detalles']) && is_array($input['detalles']) ? $input['detalles'] : null;

if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})#', $fecha, $m)) {
    $anio = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
    $fecha = sprintf('%04d-%02d-%02d 00:00:00', $anio, $m[2], $m[1]);
} elseif (preg_match('#^\d{4}-\d{2}-\d{2}$#', $fecha)) {
    $fecha .= ' 00:00:00';
}

$conexion = Database::getInstance();
if (!$conexion) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo conectar a la base de datos.']);
    exit();
}

$conexion->begin_transaction();
try {
    $sql = "UPDATE tbl_factura_encabezado SET
        factura_numero=?, factura_cai=?, factura_rtn_local=?, factura_nombre_local=?, factura_direccion=?,
        factura_fecha=?, factura_medio_pago=?, factura_subtotal=?, factura_descuentos=?, factura_exonerado=?,
        factura_exento=?, factura_gravado15=?, factura_gravado18=?, factura_isv15=?, factura_isv18=?,
        factura_gratificacion=?, factura_total=?, factura_total_articulos=?, factura_pago=?, factura_cambio=?,
        factura_categoria=?
        WHERE factura_id=?";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) throw new Exception('prepare update: ' . $conexion->error);
    // 22 params: 7 strings, 10 decimals, totalArt(i), pago(d), cambio(d), categoria(s), facturaId(i)
    $stmt->bind_param(
        'sssssssddddddddddiddsi',
        $numero, $cai, $rtn, $nombreLocal, $direccion,
        $fecha, $medioPago, $subtotal, $descuentos, $exonerado,
        $exento, $gravado15, $gravado18, $isv15, $isv18,
        $gratificacion, $total, $totalArt, $pago, $cambio,
        $categoria, $facturaId
    );
    if (!$stmt->execute()) throw new Exception('update encabezado: ' . $stmt->error);
    $stmt->close();

    // Reemplazar detalles solo si el cliente los envió
    if ($detalles !== null) {
        $del = $conexion->prepare("DELETE FROM tbl_factura_detalle WHERE factura_id=?");
        $del->bind_param('i', $facturaId);
        $del->execute();
        $del->close();

        if (!empty($detalles)) {
            $sqlDet = "INSERT INTO tbl_factura_detalle
                (factura_id, detalle_producto, detalle_cantidad, detalle_precio_unidad, detalle_descuento, detalle_monto)
                VALUES (?,?,?,?,?,?)";
            $stmtDet = $conexion->prepare($sqlDet);
            foreach ($detalles as $d) {
                $prod  = $d['detalle_producto']      ?? '';
                $cant  = (float)($d['detalle_cantidad']       ?? 1);
                $precio= (float)($d['detalle_precio_unidad']  ?? 0);
                $desc  = (float)($d['detalle_descuento']      ?? 0);
                $monto = isset($d['detalle_monto']) ? (float)$d['detalle_monto'] : ($precio * $cant - $desc);
                $stmtDet->bind_param('isdddd', $facturaId, $prod, $cant, $precio, $desc, $monto);
                if (!$stmtDet->execute()) throw new Exception('insert detalle: ' . $stmtDet->error);
            }
            $stmtDet->close();
        }
    }

    $conexion->commit();
    echo json_encode(['status' => 'success', 'message' => 'Factura actualizada correctamente', 'factura_id' => $facturaId], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar la factura', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
$conexion->close();
?>
