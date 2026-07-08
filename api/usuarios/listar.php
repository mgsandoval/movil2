<?php
// api/usuarios/listar.php
// Lista los usuarios de una empresa para poblar el combobox de gestión de accesos.
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

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    echo json_encode([
        'status'  => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit();
}

// Recibir JSON (empresa_id es opcional, por defecto 1)
$input = json_decode(file_get_contents('php://input'), true);
$empresa_id = isset($input['empresa_id']) ? (int) $input['empresa_id'] : 1;

$sql = "
    SELECT usuario_id, usuario_nombre, usuario_nombrecomp, usuario_correo
    FROM tbl_usuario
    WHERE empresa_id = ?
    ORDER BY usuario_nombrecomp
";

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

$stmt->bind_param('i', $empresa_id);

if (!$stmt->execute())
{
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al ejecutar la consulta',
        'error'   => $stmt->error
    ]);
    $stmt->close();
    $conexion->close();
    exit();
}

$resultado = $stmt->get_result();

$usuarios = [];
while ($fila = $resultado->fetch_assoc())
{
    $usuarios[] = $fila;
}

echo json_encode([
    'status'     => 'success',
    'message'    => 'Consulta realizada correctamente',
    'total'      => count($usuarios),
    'empresa_id' => $empresa_id,
    'data'       => $usuarios
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$stmt->close();
$conexion->close();
?>
