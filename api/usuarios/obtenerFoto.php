<?php
// api/usuarios/obtenerFoto.php
// Devuelve la ruta de la foto de perfil (usuario_img_ruta) de un usuario.
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

if (!isset($input['usuario_id']))
{
    echo json_encode([
        'status'  => 'error',
        'message' => 'Faltan parámetros: usuario_id es requerido'
    ]);
    exit();
}

$usuario_id = (int) $input['usuario_id'];

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

$sql  = "SELECT usuario_img_ruta FROM tbl_usuario_img WHERE usuario_id = ? LIMIT 1";
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

$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$fila = $resultado->fetch_assoc();

echo json_encode([
    'status'     => 'success',
    'usuario_id' => $usuario_id,
    'img_ruta'   => $fila ? $fila['usuario_img_ruta'] : null
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conexion->close();
?>
