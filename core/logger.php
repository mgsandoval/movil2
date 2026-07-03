<?php
//api/core/Logger.php
header('Content-Type: application/json; charset=utf-8'); // 👈 Clave: declarar UTF-8
require_once '../config/database.php';
require_once '../config/cors.php';


//Respuestas con Detalles de Errores para Depuración
function responder($exito, $mensaje, $datos_extra = [], $codigo_http = 200) 
{
    http_response_code($codigo_http);
    echo json_encode(array_merge([
        'exito' => $exito,
        'mensaje' => $mensaje,
        'timestamp' => date('Y-m-d H:i:s')
    ], $datos_extra), JSON_UNESCAPED_UNICODE);
    exit;
}

try 
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') 
    {
        responder(false, 'Método no permitido. Use POST.', [], 405);
    }

    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);

    if (!$input) 
    {
        responder(false, 'Datos inválidos', [
            'detalles' => [
                'json_error' => json_last_error_msg(),
                'raw_input' => substr($inputRaw, 0, 200)
            ]
        ], 400);
    }

    $conexion = Database::getInstance();

    if (!$conexion) 
    {
        responder(false, 'No se pudo conectar a la base de datos', [], 503);
    }

    $usuario_id = $input['usuario_id'] ?? 0;
    $dispo_unique_id = $input['dispo_unique_id'] ?? null;
    $ip_origen = $input['ip_origen'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $accion = $input['accion'] ?? 'DESCONOCIDO';
    $tabla_afectada = $input['tabla_afectada'] ?? null;
    $registro_id = $input['registro_id'] ?? null;
    $datos_anteriores = isset($input['datos_anteriores']) ? json_encode($input['datos_anteriores']) : null;
    $datos_nuevos = isset($input['datos_nuevos']) ? json_encode($input['datos_nuevos']) : null;
    $estado_operacion = $input['estado_operacion'] ?? 'EXITOSO';
    $mensaje_error = $input['mensaje_error'] ?? null;

     $sql = "
        INSERT INTO tbl_bitacora 
        (usuario_id, dispo_unique_id, ip_origen, bitacora_accion, 
         tabla_afectada, registro_id, datos_anteriores, datos_nuevos, 
         bitacora_estado, bitacora_fecha)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmt = mysqli_prepare($conexion,$sql);

    if (!$stmt) 
    {
         responder(false, 'Error al preparar la consulta SQL', [
            'detalles' => [
                'error_mysql' => mysqli_error($conexion),
                'codigo_error' => mysqli_errno($conexion),
                'sql' => preg_replace('/\s+/', ' ', trim($sql)) // SQL en una sola línea
            ]
        ], 500);
    }

    $usuario_id_bind = $usuario_id !== null ? (int)$usuario_id : 0;
    
    $bind_result = mysqli_stmt_bind_param(
        $stmt, 
        'issssssss',  // 👈 10 caracteres: 1 'i' + 9 's'
        $usuario_id_bind,
        $dispo_unique_id,
        $ip_origen,
        $accion,
        $tabla_afectada,
        $registro_id,
        $datos_anteriores,
        $datos_nuevos,
        $estado_operacion
    );

   if (!$bind_result) {
        responder(false, 'Error al vincular parámetros', [
            'detalles' => [
                'error_mysql' => mysqli_stmt_error($stmt),
                'codigo_error' => mysqli_stmt_errno($stmt)
            ]
        ], 500);
    }

    // 7️⃣ Ejecutar la consulta
    if (!mysqli_stmt_execute($stmt)) {
        responder(false, 'Error al ejecutar la consulta', [
            'detalles' => [
                'error_mysql' => mysqli_stmt_error($stmt),
                'codigo_error' => mysqli_stmt_errno($stmt),
                'sql_state' => mysqli_stmt_sqlstate($stmt)
            ]
        ], 500);
    }

    // 8️⃣ Éxito
    $bitacora_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    responder(true, 'Evento registrado correctamente', [
        'bitacora_id' => $bitacora_id
    ], 200);

} 
catch (Exception $e) 
{
   responder(false, 'Error inesperado en el servidor', [
        'detalles' => [
            'exception' => $e->getMessage(),
            'archivo' => basename($e->getFile()),
            'linea' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]
    ], 500);
}
?>