<?php
// api/auth/register.php
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/cors.php';
require_once '../../config/database.php';

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

    $usuario     = trim($input['user'] ?? '');
    $nombreComp  = trim($input['name'] ?? '');
    $clave       = $input['pass'] ?? '';
    $correo      = trim($input['email'] ?? '');
    $telefono    = trim($input['phone'] ?? '');
    $empresa_id  = 1;

    // 2️⃣ Validar campos obligatorios
    if (empty($usuario) || empty($nombreComp) || empty($clave) || empty($correo) || empty($telefono))
    {
        throw new Exception('Todos los campos son obligatorios.');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL))
    {
        throw new Exception('El correo electrónico no es válido.');
    }

    if (strlen($usuario) > 50 || strlen($clave) > 50 || strlen($nombreComp) > 100 || strlen($correo) > 100 || strlen($telefono) > 20)
    {
        throw new Exception('Uno o más campos exceden la longitud permitida.');
    }

    $conexion = Database::getInstance();

    if (!$conexion)
    {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    // 3️⃣ Verificar que el usuario o correo no existan ya
    $stmt = mysqli_prepare($conexion, "
        SELECT usuario_id
        FROM tbl_usuario
        WHERE (usuario_nombre = ? OR usuario_correo = ?)
          AND empresa_id = ?
        LIMIT 1
    ");

    if (!$stmt)
    {
        throw new Exception('Error en la consulta SQL: ' . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, 'ssi', $usuario, $correo, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0)
    {
        mysqli_stmt_close($stmt);
        http_response_code(409);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Ya existe un usuario con ese nombre de usuario o correo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    mysqli_stmt_close($stmt);

    // 4️⃣ Insertar el nuevo usuario
    $insertStmt = mysqli_prepare($conexion, "
        INSERT INTO tbl_usuario
            (usuario_nombre, usuario_clave, usuario_nombrecomp, usuario_correo, usuario_telefono, empresa_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if (!$insertStmt)
    {
        throw new Exception('Error en la consulta SQL: ' . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($insertStmt, 'sssssi', $usuario, $clave, $nombreComp, $correo, $telefono, $empresa_id);

    if (!mysqli_stmt_execute($insertStmt))
    {
        throw new Exception('Error al registrar el usuario: ' . mysqli_stmt_error($insertStmt));
    }

    $nuevoId = mysqli_insert_id($conexion);
    mysqli_stmt_close($insertStmt);

    // 5️⃣ ✅ Registro exitoso
    echo json_encode([
        'exito' => true,
        'mensaje' => 'Usuario registrado correctamente.',
        'usuario' => [
            'id'              => $nuevoId,
            'usuario'         => $usuario,
            'nombre_completo' => $nombreComp,
            'correo'          => $correo,
            'telefono'        => $telefono,
            'empresa_id'      => $empresa_id
        ]
    ], JSON_UNESCAPED_UNICODE);
}
catch (Exception $e)
{
    http_response_code(400);
    echo json_encode([
        'exito' => false,
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
