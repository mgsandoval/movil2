<?php
header('Content-Type: application/json; charset=utf-8'); // 👈 Clave: declarar UTF-8
// 3️⃣ Conectar a la BD
    require_once '../../config/cors.php';
    require_once '../../config/database.php';
    
// Capturar errores fatales
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() 
{
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) 
    {
        http_response_code(500);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error interno del servidor',
            'error' => $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try 
{
    // 1️⃣ Recibir datos JSON del body
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);

    if (!$input) 
    {
        throw new Exception('Datos inválidos. Se esperaba JSON.');
    }

    $usuario    = trim($input['user'] ?? '');
    $clave      = $input['pass'] ?? '';
    $empresa_id = 0;//$input['empresa_id'] ?? 0;
    //$dispo_unique_id = $input['device_id'] ?? null;
    //$ip_origen  = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';

    // 2️⃣ Validar campos
    if (empty($usuario) || empty($clave)) 
    {
        throw new Exception('Usuario y contraseña son obligatorios.');
    }

    $conexion = Database::getInstance();
    
    if (!$conexion) 
    {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    // 4️⃣ Buscar el usuario (incluye estado global y foto de perfil)
    $stmt = mysqli_prepare($conexion, "
        SELECT
            u.usuario_id,
            u.usuario_nombre,
            u.usuario_clave,
            u.usuario_nombrecomp,
            u.usuario_correo,
            u.usuario_telefono,
            u.empresa_id,
            u.usuario_estado,
            img.usuario_img_ruta
        FROM tbl_usuario u
        LEFT JOIN tbl_usuario_img img ON img.usuario_id = u.usuario_id
        WHERE u.usuario_nombre = ?
          AND u.empresa_id = ?
        LIMIT 1
    ");

    if (!$stmt) 
    {
        throw new Exception('Error en la consulta SQL: ' . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, 'si', $usuario, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // 5️⃣ Validar credenciales
    if (!$user || $clave!=$user['usuario_clave']) 
    {
        // ❌ LOGIN FALLIDO - Registrar en bitácora
        /*registrarBitacora($conexion, null, $usuario, $dispo_unique_id, $ip_origen, 
            'LOGIN_FALLIDO', 'tbl_usuario', null, 'FALLIDO', 
            'Credenciales incorrectas para usuario: ' . $usuario);*/

        http_response_code(401);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Usuario o contraseña incorrectos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 5️⃣.1 Verificar acceso GLOBAL a la app (grant/revoke)
    if (isset($user['usuario_estado']) && (int)$user['usuario_estado'] === 0)
    {
        http_response_code(403);
        echo json_encode([
            'exito'    => false,
            'revocado' => true,
            'mensaje'  => 'Tu acceso a la aplicación ha sido revocado. Contacta al administrador.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 6️⃣ ✅ LOGIN EXITOSO - Actualizar último acceso
    $updateStmt = mysqli_prepare($conexion, "
        UPDATE tbl_usuario 
        SET usuario_fultacceso = NOW() 
        WHERE usuario_id = ?
    ");
    mysqli_stmt_bind_param($updateStmt, 'i', $user['usuario_id']);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    echo json_encode([
            'exito' => true,
            'mensaje' => 'Bienvenido, ' . $user['usuario_nombrecomp'],
            'usuario' => [
                'id'              => $user['usuario_id'],
                'usuario'         => $user['usuario_nombre'],
                'nombre_completo' => $user['usuario_nombrecomp'],
                'correo'          => $user['usuario_correo'],
                'telefono'        => $user['usuario_telefono'],
                'empresa_id'      => $user['empresa_id'],
                'estado'          => (int)$user['usuario_estado'],
                'img_ruta'        => $user['usuario_img_ruta']
            ]
        ], JSON_UNESCAPED_UNICODE);

    /*if($dispo_unique_id!="")
    {
    // 7️⃣ Registrar en bitácora
        registrarBitacora($conexion, $user['usuario_id'], $usuario, $dispo_unique_id, $ip_origen,
        'LOGIN', 'tbl_usuario', $user['usuario_id'], 'EXITOSO', 
        'Inicio de sesión exitoso');

    // 8️⃣ Devolver datos del usuario (SIN la clave)
        echo json_encode([
            'exito' => true,
            'mensaje' => 'Bienvenido, ' . $user['usuario_nombrecomp'],
            'usuario' => [
                'id'              => $user['usuario_id'],
                'usuario'         => $user['usuario_nombre'],
                'nombre_completo' => $user['usuario_nombrecomp'],
                'correo'          => $user['usuario_correo'],
                'telefono'        => $user['usuario_telefono'],
                'empresa_id'      => $user['empresa_id']
            ]
        ], JSON_UNESCAPED_UNICODE);
    }*/
} 
catch (Exception $e) 
{
    http_response_code(400);
    echo json_encode([
        'exito' => false,
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Función auxiliar para registrar en la bitácora
 */
/*function registrarBitacora($conexion, $usuario_id, $nombre_usuario_temp, $dispo_unique_id, 
                          $ip_origen, $accion, $tabla_afectada, $registro_id, 
                          $estado_operacion, $mensaje_error = null) 
{
    try 
    {
        $stmt = mysqli_prepare($conexion, "
            INSERT INTO tbl_bitacora 
            (usuario_id, dispo_unique_id, ip_origen, bitacora_accion, 
             tabla_afectada, registro_id, estado_operacion, mensaje_error, bitacora_fecha)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) return; // Si falla, no interrumpimos el flujo principal

        mysqli_stmt_bind_param(
            $stmt, 
            'isssssss',
            $usuario_id,
            $dispo_unique_id,
            $ip_origen,
            $accion,
            $tabla_afectada,
            $registro_id,
            $estado_operacion,
            $mensaje_error
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } 
    catch (Exception $e) 
    {
        // Silenciar error de bitácora para no afectar el login
        error_log("Error al registrar bitácora: " . $e->getMessage());
    }
}*/
?>