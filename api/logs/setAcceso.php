<?php
// api/logs/setAcceso.php
// Activa o desactiva el acceso de un usuario a un módulo (upsert sobre tbl_acceso).
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

// Recibir JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['usuario_id']) || !isset($input['modulo_codigo']) || !isset($input['estado']))
{
    echo json_encode([
        'status'  => 'error',
        'message' => 'Faltan parámetros: usuario_id, modulo_codigo y estado son requeridos'
    ]);
    exit();
}

$usuario_id    = (int) $input['usuario_id'];
$modulo_codigo = (int) $input['modulo_codigo'];
// Normalizar a 0 / 1
$estado        = ((int) $input['estado']) === 1 ? 1 : 0;

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

// 1) Verificar si ya existe una fila de acceso para (usuario, módulo)
$sqlCheck = "SELECT 1 FROM tbl_acceso WHERE usuario_id = ? AND modulo_codigo = ? LIMIT 1";
$stmtCheck = $conexion->prepare($sqlCheck);

if (!$stmtCheck)
{
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al preparar la verificación',
        'error'   => $conexion->error
    ]);
    $conexion->close();
    exit();
}

$stmtCheck->bind_param('ii', $usuario_id, $modulo_codigo);
$stmtCheck->execute();
$existe = $stmtCheck->get_result()->num_rows > 0;
$stmtCheck->close();

// 2) UPDATE si existe, INSERT si no
if ($existe)
{
    $operacion = 'UPDATE';
    $sql = "UPDATE tbl_acceso SET acceso_estado = ? WHERE usuario_id = ? AND modulo_codigo = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt) { $stmt->bind_param('iii', $estado, $usuario_id, $modulo_codigo); }
}
else
{
    $operacion = 'INSERT';
    $sql = "INSERT INTO tbl_acceso (usuario_id, modulo_codigo, acceso_estado) VALUES (?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    if ($stmt) { $stmt->bind_param('iii', $usuario_id, $modulo_codigo, $estado); }
}

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

if (!$stmt->execute())
{
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al guardar el acceso',
        'error'   => $stmt->error
    ]);
    $stmt->close();
    $conexion->close();
    exit();
}

echo json_encode([
    'status'        => 'success',
    'message'       => 'Acceso actualizado correctamente',
    'usuario_id'    => $usuario_id,
    'modulo_codigo' => $modulo_codigo,
    'estado'        => $estado,
    'operacion'     => $operacion
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conexion->close();
?>
