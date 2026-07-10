<?php
// api/facturas/reportes.php
// Devuelve métricas agregadas para el dashboard de reportes:
// resumen, totales por mes, por categoría y por empleado.
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
// Acepta POST (opcionalmente con filtros) o GET
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$usuarioId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 0;

$conexion = Database::getInstance();
if (!$conexion) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo conectar a la base de datos.']);
    exit();
}

// Filtro opcional por empleado (aplica a todas las consultas)
$filtroUsuario = $usuarioId > 0 ? "WHERE usuario_id = $usuarioId" : "";
$andUsuario    = $usuarioId > 0 ? "AND e.usuario_id = $usuarioId" : "";

$reporte = [];

// 1) Resumen general
$sqlResumen = "SELECT
    COUNT(*)                    AS total_facturas,
    COALESCE(SUM(factura_total),0)          AS total_gastado,
    COALESCE(SUM(factura_isv15 + factura_isv18),0) AS total_isv,
    COALESCE(AVG(factura_total),0)          AS promedio_factura
    FROM tbl_factura_encabezado $filtroUsuario";
$res = $conexion->query($sqlResumen);
$reporte['resumen'] = $res ? $res->fetch_assoc() : null;

// 2) Totales por mes (últimos 12 meses)
$sqlMes = "SELECT
    DATE_FORMAT(factura_fecha, '%Y-%m') AS mes,
    COUNT(*)               AS cantidad,
    COALESCE(SUM(factura_total),0) AS total
    FROM tbl_factura_encabezado $filtroUsuario
    GROUP BY DATE_FORMAT(factura_fecha, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12";
$res = $conexion->query($sqlMes);
$porMes = [];
if ($res) { while ($r = $res->fetch_assoc()) { $porMes[] = $r; } }
$reporte['por_mes'] = array_reverse($porMes); // cronológico ascendente

// 3) Totales por categoría
$sqlCat = "SELECT
    factura_categoria AS categoria,
    COUNT(*)          AS cantidad,
    COALESCE(SUM(factura_total),0) AS total
    FROM tbl_factura_encabezado $filtroUsuario
    GROUP BY factura_categoria
    ORDER BY total DESC";
$res = $conexion->query($sqlCat);
$porCategoria = [];
if ($res) { while ($r = $res->fetch_assoc()) { $porCategoria[] = $r; } }
$reporte['por_categoria'] = $porCategoria;

// 4) Totales por empleado
$sqlEmp = "SELECT
    COALESCE(u.usuario_nombrecomp, 'Sin asignar') AS empleado,
    COUNT(*)          AS cantidad,
    COALESCE(SUM(e.factura_total),0) AS total
    FROM tbl_factura_encabezado e
    LEFT JOIN tbl_usuario u ON e.usuario_id = u.usuario_id
    WHERE 1=1 $andUsuario
    GROUP BY e.usuario_id, u.usuario_nombrecomp
    ORDER BY total DESC";
$res = $conexion->query($sqlEmp);
$porEmpleado = [];
if ($res) { while ($r = $res->fetch_assoc()) { $porEmpleado[] = $r; } }
$reporte['por_empleado'] = $porEmpleado;

echo json_encode([
    'status' => 'success',
    'data'   => $reporte
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$conexion->close();
?>
