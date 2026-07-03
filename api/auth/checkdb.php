<?php
// api/auth/checkdb.php
// CORS MUST be first, before any output or errors
require_once '../../config/cors.php';

// Set JSON header IMMEDIATELY
header('Content-Type: application/json; charset=utf-8');

// Initialize response variables
$conectado = false;
$mensaje = '';
$version_mysql = '';
$base_datos = '';
$timestamp = date('Y-m-d H:i:s');

try 
{
    // Load env credentials
    require_once '../../config/env_loader.php';
    
    // Try loading from .env file
    @loadEnv(__DIR__ . '/../../config/.env');
    
    // Get database credentials from environment variables
    $db_host = getenv('DB_HOST') ? getenv('DB_HOST') : 'localhost';
    $db_port = getenv('DB_PORT') ? getenv('DB_PORT') : '3306';
    $db_name = getenv('DB_NAME') ? getenv('DB_NAME') : 'movil2';
    $db_user = getenv('DB_USER') ? getenv('DB_USER') : 'root';
    $db_pass = getenv('DB_PASS') ? getenv('DB_PASS') : '';
    
    // Connect to database directly
    $conexion = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    
    if (!$conexion) 
    {
        throw new Exception('MySQL Connection Error: ' . mysqli_connect_error());
    }
    
    // Set charset
    mysqli_set_charset($conexion, 'utf8mb4');
    
    // Get version
    $version_mysql = mysqli_get_server_info($conexion);
    $base_datos = $db_name;
    $conectado = true;
    $mensaje = 'Conexión establecida correctamente';
    
    // Close connection
    mysqli_close($conexion);
} 
catch (Exception $e) 
{
    $conectado = false;
    $mensaje = 'Error de conexión: ' . $e->getMessage();
}

// ALWAYS send JSON response with proper structure
http_response_code($conectado ? 200 : 503);

echo json_encode([
    'conectado'        => $conectado,
    'mensaje'          => $mensaje,
    'base_de_datos'    => $base_datos,
    'version_servidor' => $version_mysql,
    'timestamp'        => $timestamp
], JSON_UNESCAPED_UNICODE);
?>