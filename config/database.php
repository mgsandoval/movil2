<?php
// config/database.php
require_once __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/.env');

class Database 
{
    private static $instance = null;
    private $conn;
    private $host;
    private $port;
    private $name;
    private $user;
    private $pass;

    private function __construct() 
    {
        $this->host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost';
        $this->port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : '3306';
        $this->name = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : 'movil2';
        $this->user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root';
        $this->pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : 'your_password_here';

        // Conexión MySQLi orientada a objetos
        $this->conn = mysqli_connect($this->host, $this->user, $this->pass, $this->name, $this->port);

        
        if (!$this->conn) 
        {
            throw new Exception("Error de conexión: " . mysqli_connect_error(), 503);
        }

        // Forzar UTF-8
        mysqli_set_charset($this->conn, 'utf8mb4');
    }
    public static function getDatabaseName() 
    {
        if (self::$instance === null) 
        {
            self::$instance = new Database();
        }
        return self::$instance->name;
    }
    public static function getInstance() 
    {
        if (self::$instance === null) 
        {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
    public static function close() 
    {
        if (self::$instance && self::$instance->conn) 
        {
            mysqli_close(self::$instance->conn);
            self::$instance = null;
        }
    }
}
?>