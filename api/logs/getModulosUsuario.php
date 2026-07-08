<?php
// api/logs/getModulosUsuario.php
// Lista TODOS los módulos (modulo_tipo='MODULO') junto con el estado de acceso
// (activado/desactivado) para un usuario dado. Contraparte "lista completa" de getAccesos.php.
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

if (!isset($input['usuario_id']))
{
    echo json_encode([
        'status'  => 'error',
        'message' => 'Faltan parámetros: usuario_id es requerido'
    ]);
    exit();
}

$usuario_id = (int) $input['usuario_id'];

// LEFT JOIN: los módulos sin fila en tbl_acceso para este usuario aparecen con estado 0
$sql = "
    SELECT
        m.modulo_codigo,
        m.modulo_nombre,
        m.modulo_activity,
        COALESCE(ac.acceso_estado, 0) AS acceso_estado
    FROM tbl_modulo m
    LEFT JOIN tbl_acceso ac
        ON ac.modulo_codigo = m.modulo_codigo
       AND ac.usuario_id = ?
    WHERE m.modulo_tipo = 'MODULO'
    ORDER BY m.modulo_nombre
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

$stmt->bind_param('i', $usuario_id);

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

$modulos = [];
while ($fila = $resultado->fetch_assoc())
{
    // Normalizar acceso_estado a entero (COALESCE puede devolverlo como string)
    $fila['acceso_estado'] = (int) $fila['acceso_estado'];
    $modulos[] = $fila;
}

echo json_encode([
    'status'     => 'success',
    'message'    => 'Consulta realizada correctamente',
    'total'      => count($modulos),
    'usuario_id' => $usuario_id,
    'data'       => $modulos
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$stmt->close();
$conexion->close();
?>
