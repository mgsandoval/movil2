<?php
// api/bitacora/listar.php
ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') 
{
    http_response_code(200);
    exit();
}

try 
{
    $fechaInicio = $_GET['fecha_inicio'] ?? '';
    $fechaFin = $_GET['fecha_fin'] ?? '';

    if (empty($fechaInicio) || empty($fechaFin)) 
    {
        http_response_code(400);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Faltan parámetros: fecha_inicio y fecha_fin son requeridos'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) 
    {
        http_response_code(400);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Formato de fecha inválido. Use YYYY-MM-DD'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $conexion = Database::getInstance();
    
    if (!$conexion) 
    {
        http_response_code(503);
        $response = [
            'exito' => false,
            'mensaje' => 'No se pudo conectar a la base de datos'
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    $sql = "
        SELECT 
            b.bitacora_id,
            b.usuario_id,
            b.dispo_unique_id,
            b.ip_origen,
            b.bitacora_accion,
            b.tabla_afectada,
            b.registro_id,
            b.datos_anteriores,
            b.datos_nuevos,
            b.bitacora_estado,
            b.bitacora_fecha,
            u.usuario_nombre
        FROM tbl_bitacora b
        LEFT JOIN tbl_usuario u ON b.usuario_id = u.usuario_id
        WHERE DATE(b.bitacora_fecha) BETWEEN ? AND ?
        ORDER BY b.bitacora_fecha DESC
        LIMIT 500
    ";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) 
    {
        http_response_code(500);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error al preparar la consulta',
            'error' => $conexion->error
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt->bind_param('ss', $fechaInicio, $fechaFin);

    if (!$stmt->execute()) 
    {
        http_response_code(500);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error al ejecutar la consulta',
            'error' => $stmt->error
        ], JSON_UNESCAPED_UNICODE);
        $stmt->close();
        exit();
    }

    $resultado = $stmt->get_result();

    $bitacora = [];
    while ($fila = $resultado->fetch_assoc()) 
    {
        $bitacora[] = $fila;
    }

    $stmt->close();

    echo json_encode([
        'exito' => true,
        'mensaje' => 'Consulta realizada correctamente',
        'total' => count($bitacora),
        'fecha_inicio' => $fechaInicio,
        'fecha_fin' => $fechaFin,
        'datos' => $bitacora
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} 
catch (Exception $e) 
{
    http_response_code(500);
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Error inesperado en el servidor',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>