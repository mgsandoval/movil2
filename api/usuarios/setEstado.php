<?php
// api/usuarios/setEstado.php
// Concede (1) o revoca (0) el acceso GLOBAL de un usuario a la app.
// Actualiza tbl_usuario.usuario_estado.
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    echo json_encode([
        'status'  => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['usuario_id']) || !isset($input['estado']))
{
    echo json_encode([
        'status'  => 'error',
        'message' => 'Faltan parámetros: usuario_id y estado son requeridos'
    ]);
    exit();
}

$usuario_id = (int) $input['usuario_id'];
$estado     = ((int) $input['estado']) === 1 ? 1 : 0;

$conexion = Database::getInstance();

if (!$conexion)
{
    http_response_code(503);
    echo json_encode([
        'status'  => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ]);
    exit();
}

$sql  = "UPDATE tbl_usuario SET usuario_estado = ? WHERE usuario_id = ?";
$stmt = $conexion->prepare($sql);

if (!$stmt)
{
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al preparar la consulta',
        'error'   => $conexion->error
    ]);
    $conexion->close();
    exit();
}

$stmt->bind_param('ii', $estado, $usuario_id);

if (!$stmt->execute())
{
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al actualizar el estado',
        'error'   => $stmt->error
    ]);
    $stmt->close();
    $conexion->close();
    exit();
}

echo json_encode([
    'status'     => 'success',
    'message'    => $estado === 1 ? 'Acceso concedido' : 'Acceso revocado',
    'usuario_id' => $usuario_id,
    'estado'     => $estado
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conexion->close();
?>
