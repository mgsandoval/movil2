<?php
// api/auth/checkdb-simple.php
// Simplified test WITHOUT env_loader - for debugging
require_once '../../config/cors.php';
header('Content-Type: application/json; charset=utf-8');

$conectado = false;
$mensaje = '';
$version_mysql = '';
$base_datos = '';

try 
{
    // HARDCODE your InfinityFree credentials here temporarily for testing
    $db_host = 'sql104.infinityfree.com';
    $db_port = 3306;
    $db_name = 'if0_42224118_movil2';        // Replace with your actual DB name
    $db_user = 'if0_42224118';       // Replace with your actual username
    $db_pass = 'oPhArKqEQ28E0zS';       // Replace with your actual password
    
    // Test connection
    $conexion = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    
    if (!$conexion) 
    {
        throw new Exception('MySQL Error: ' . mysqli_connect_error());
    }
    
    // Set charset
    mysqli_set_charset($conexion, 'utf8mb4');
    
    // Get version
    $version_mysql = mysqli_get_server_info($conexion);
    $base_datos = $db_name;
    $conectado = true;
    $mensaje = 'Conexión exitosa';
    
    mysqli_close($conexion);
}
catch (Exception $e) 
{
    $conectado = false;
    $mensaje = $e->getMessage();
}

http_response_code($conectado ? 200 : 503);

echo json_encode([
    'conectado'        => $conectado,
    'mensaje'          => $mensaje,
    'base_de_datos'    => $base_datos,
    'version_servidor' => $version_mysql,
    'timestamp'        => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
?>
