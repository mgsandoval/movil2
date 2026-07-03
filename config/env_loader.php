<?php
// config/env_loader.php
if (!function_exists('loadEnv')) 
{
    function loadEnv($filePath) 
    {
        if (!file_exists($filePath)) 
        {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) 
        {
            $line = trim($line);
            
            // Ignorar comentarios
            if (substr($line, 0, 1) === '#') 
            {
                continue;
            }

            // Ignorar líneas sin '='
            if (strpos($line, '=') === false) 
            {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Quitar comillas si existen
            if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'")) 
            {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}
?>