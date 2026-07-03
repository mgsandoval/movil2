<?php
// api/core/dispositivo.php

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/cors.php';
/**
 * Función helper para respuestas consistentes
 */
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
    // 1️⃣ Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') 
    {
        responder(false, 'Método no permitido. Use POST.', [], 405);
    }

    // 2️⃣ Recibir y validar datos
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

    // 3️⃣ Validar campo obligatorio
    $dispo_unique_id = trim($input['dispo_unique_id'] ?? '');
    if (empty($dispo_unique_id)) 
    {
        responder(false, 'El campo dispo_unique_id es obligatorio', [], 400);
    }

    // 4️⃣ Conectar a la BD
    $conexion = Database::getInstance();
    if (!$conexion) 
    {
        responder(false, 'No se pudo conectar a la base de datos', [], 503);
    }

    // 5️⃣ Preparar datos (con valores por defecto)
    $dispo_nombre_equipo = $input['dispo_nombre_equipo'] ?? null;
    $dispo_marca         = $input['dispo_marca'] ?? null;
    $dispo_modelo        = $input['dispo_modelo'] ?? null;
    $dispo_so            = $input['dispo_so'] ?? null;
    $dispo_so_version    = $input['dispo_so_version'] ?? null;
    $dispo_dir_mac       = $input['dispo_dir_mac'] ?? null;
    $modulo_codigo       = $input['modulo_codigo'] ?? null;

    // 6️⃣ SQL con INSERT ... ON DUPLICATE KEY UPDATE
    // 👇 Si el unique_id existe, actualiza los campos; si no, lo inserta
    $sql = "
        INSERT INTO tbl_dispositivos 
        (dispo_unique_id, dispo_nombre_equipo, dispo_marca, dispo_modelo, 
         dispo_so, dispo_so_version, dispo_dir_mac, dispo_fregistro,dispo_factual)
        VALUES (?, ?, ?, ?, ?, ?, ?,  NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            dispo_nombre_equipo = VALUES(dispo_nombre_equipo),
            dispo_marca         = VALUES(dispo_marca),
            dispo_modelo        = VALUES(dispo_modelo),
            dispo_so            = VALUES(dispo_so),
            dispo_so_version    = VALUES(dispo_so_version),
            dispo_dir_mac       = VALUES(dispo_dir_mac),
            dispo_factual      = NOW()
    ";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) 
    {
        responder(false, 'Error al preparar la consulta SQL', [
            'detalles' => [
                'error_mysql' => mysqli_error($conexion),
                'codigo_error' => mysqli_errno($conexion)
            ]
        ], 500);
    }

    // 7️⃣ Bind de parámetros: 9 caracteres 's' para 9 variables
    mysqli_stmt_bind_param(
        $stmt,
        'sssssss',  // 8 strings
        $dispo_unique_id,
        $dispo_nombre_equipo,
        $dispo_marca,
        $dispo_modelo,
        $dispo_so,
        $dispo_so_version,
        $dispo_dir_mac 
    );

    // 8️⃣ Ejecutar
    if (!mysqli_stmt_execute($stmt)) 
    {
        responder(false, 'Error al ejecutar la consulta', [
            'detalles' => [
                'error_mysql' => mysqli_stmt_error($stmt),
                'codigo_error' => mysqli_stmt_errno($stmt)
            ]
        ], 500);
    }

    // 9️⃣ Determinar si fue INSERT o UPDATE
    // 👇 mysqli_affected_rows devuelve:
    //    1 = INSERT nuevo
    //    2 = UPDATE de existente
    //    0 = Sin cambios (datos iguales)
    $affected_rows = mysqli_affected_rows($conexion);
    
   if ($affected_rows === 1) 
    {
        $operacion = 'INSERT';
        $mensaje_operacion = 'Dispositivo nuevo registrado';
    } elseif ($affected_rows === 2) 
    {
        $operacion = 'UPDATE';
        $mensaje_operacion = 'Dispositivo existente actualizado';
    } elseif ($affected_rows === 0) 
    {
        $operacion = 'SIN_CAMBIOS';
        $mensaje_operacion = 'El dispositivo ya existía con los mismos datos';
    } else 
    {
        $operacion = 'DESCONOCIDO';
        $mensaje_operacion = 'Operación completada';
    }

    mysqli_stmt_close($stmt);

    // 🔟 Responder con información detallada
    responder(true, 'Dispositivo registrado correctamente', [
        'dispo_unique_id' => $dispo_unique_id,
        'operacion' => $operacion,
        'mensaje_operacion' => $mensaje_operacion
    ], 200);

} 
catch (Exception $e) 
{
    responder(false, 'Error inesperado en el servidor', [
        'detalles' => [
            'exception' => $e->getMessage(),
            'archivo' => basename($e->getFile()),
            'linea' => $e->getLine()
        ]
    ], 500);
}
?>