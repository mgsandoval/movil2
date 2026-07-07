<?php
//api/logs/getAccesos.php
header('Content-Type: application/json; charset=utf-8'); // 👈 Clave: declarar UTF-8
// 3️⃣ Conectar a la BD
require_once '../../config/database.php';
require_once '../../config/cors.php';
    
// Capturar errores fatales
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
        'status' => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit();
}

// Recibir JSON
$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'] ?? '';

// ============================================
// RECIBIR DATOS JSON
// ============================================
$input = json_decode(file_get_contents('php://input'), true);

// Validar que se recibieron los datos
if (!isset($input['usuario_id'])) 
{
    echo json_encode([
        'status' => 'error',
        'message' => 'Faltan parámetros: usuario_id es requerido'
    ]);
    exit();
}


//Conexion a BD==========================================================

// Consulta SQL
$sql = "select
m.modulo_codigo,
m.modulo_nombre,
m.modulo_nombre as modulo_descripcion,
m.modulo_activity
from
tbl_acceso ac 
inner join tbl_modulo m on ac.modulo_codigo=m.modulo_codigo
where ac.usuario_id=?
and acceso_estado=1 and m.modulo_tipo='MODULO'";

$conexion = Database::getInstance();
    
if (!$conexion) 
{
    throw new Exception('No se pudo conectar a la base de datos.');
}
// Preparar la consulta
$stmt = $conexion->prepare($sql);

if (!$stmt) 
{
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al preparar la consulta',
        'error' => $conexion->error
    ]);
    $conexion->close();
    exit();
}

// Vincular parámetros (ss = dos strings)
$stmt->bind_param('i', $usuario_id);

// Ejecutar consulta
if (!$stmt->execute()) 
{
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al ejecutar la consulta',
        'error' => $stmt->error
    ]);
    $stmt->close();
    $conexion->close();
    exit();
}

// Obtener resultados
$resultado = $stmt->get_result();

// Verificar si hay resultados
if ($resultado->num_rows === 0) 
{
    echo json_encode([
        'status' => 'warning',
        'message' => 'No se encontraron dispositivos en el rango de fechas',
        'total' => 0,
        'data' => []
    ]);
    $stmt->close();
    $conexion->close();
    exit();
}

// Convertir resultados a array asociativo
$dispositivos = [];
while ($fila = $resultado->fetch_assoc()) 
{
    $dispositivos[] = $fila;
}

// ============================================
// RESPUESTA EXITOSA
// ============================================
echo json_encode([
    'status' => 'success',
    'message' => 'Consulta realizada correctamente',
    'total' => count($dispositivos),
    'usuario_id' => $usuario_id,
    'data' => $dispositivos
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ============================================
// CERRAR CONEXIONES
// ============================================
$stmt->close();
$conexion->close();
?>