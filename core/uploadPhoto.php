<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/cors.php';

$conexion = Database::getInstance();
//$url = Database::getURL();

// ============================================
// VALIDAR MÉTODO
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array(
        'success' => false,
        'message' => 'Metodo no permitido'
    ));
    exit;
}

// ============================================
// RECIBIR PARÁMETROS
// ============================================
$tableName = isset($_POST['tableName']) ? trim($_POST['tableName']) : null;
$fieldID   = isset($_POST['fieldID'])   ? trim($_POST['fieldID'])   : null;
$fieldRuta = isset($_POST['fieldRuta']) ? trim($_POST['fieldRuta']) : null;
$recordId  = isset($_POST['recordId'])  ? trim($_POST['recordId'])  : null;

if (!$tableName || !$fieldID || !$fieldRuta ) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Faltan parametros requeridos: tableName, fieldID, fieldRuta, recordId'
    ));
    exit;
}

// ============================================
// WHITELIST DE TABLAS
// ============================================
$validTables = array(
    'tbl_usuario_img' => array(
        'idField'   => 'usuario_id',
        'rutaField' => 'usuario_img_ruta'
    ),
    'tbl_cliente_img' => array(
        'idField'   => 'cliente_id',
        'rutaField' => 'cliente_img_ruta'
    ),
    'tbl_producto_img' => array(
        'idField'   => 'producto_id',
        'rutaField' => 'producto_img_ruta'
    )
);

if (!array_key_exists($tableName, $validTables)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Tabla no valida'
    ));
    exit;
}

$expectedIdField   = $validTables[$tableName]['idField'];
$expectedRutaField = $validTables[$tableName]['rutaField'];

if ($fieldID !== $expectedIdField || $fieldRuta !== $expectedRutaField) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Campos no validos para la tabla ' . $tableName
    ));
    exit;
}

// ============================================
// VALIDAR IMAGEN
// ============================================
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = array(
        UPLOAD_ERR_INI_SIZE   => 'La imagen excede el tamano maximo del servidor',
        UPLOAD_ERR_FORM_SIZE  => 'La imagen excede el tamano maximo del formulario',
        UPLOAD_ERR_PARTIAL    => 'La imagen se subio parcialmente',
        UPLOAD_ERR_NO_FILE    => 'No se envio ninguna imagen',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco',
        UPLOAD_ERR_EXTENSION  => 'Extension de PHP detuvo la subida'
    );

    $errorCode = isset($_FILES['image']['error']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE;
    $errorMsg  = isset($uploadErrors[$errorCode]) ? $uploadErrors[$errorCode] : 'Error desconocido al subir imagen';

    echo json_encode(array(
        'success' => false,
        'message' => 'Error: ' . $errorMsg
    ));
    exit;
}

$image = $_FILES['image'];

// Validar tipo MIME real
$allowedTypes = array('image/jpeg', 'image/png', 'image/jpg', 'image/gif');
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$realType = finfo_file($finfo, $image['tmp_name']);
finfo_close($finfo);

if (!in_array($realType, $allowedTypes)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Tipo de archivo no permitido. Solo: JPG, PNG, GIF'
    ));
    exit;
}

// Validar tamaño (máximo 5MB)
if ($image['size'] > 5 * 1024 * 1024) {
    echo json_encode(array(
        'success' => false,
        'message' => 'La imagen es muy grande. Maximo 5MB'
    ));
    exit;
}

// ============================================
// CREAR DIRECTORIO Y GUARDAR ARCHIVO
// ============================================
$filePath = '/api/uploads/' . $tableName . '/';
$rutaArchivo=$filePath;
$uploadDir = __DIR__ . '/../api/uploads/' . $tableName . '/';

if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(array(
            'success' => false,
            'message' => 'No se pudo crear el directorio: ' . $uploadDir
        ));
        exit;
    }
}

$extension   = pathinfo($image['name'], PATHINFO_EXTENSION);
$newFileName = $recordId . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
$filePath    = $uploadDir . $newFileName;
$rutaArchivo = $rutaArchivo . $newFileName;

if (!move_uploaded_file($image['tmp_name'], $filePath)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Error al guardar la imagen en el servidor. Ruta: ' . $filePath
    ));
    exit;
}

// ============================================
// OBTENER RUTA ANTERIOR (para eliminarla)
// ============================================
$oldFilePath = null;
try {
    $sqlOld  = "SELECT " . $expectedRutaField . " FROM " . $tableName . " WHERE " . $expectedIdField . " = ? LIMIT 1";
    $stmtOld = mysqli_prepare($conexion, $sqlOld);
    
    if ($stmtOld) {
        mysqli_stmt_bind_param($stmtOld, "s", $recordId);
        mysqli_stmt_execute($stmtOld);
        $result = mysqli_stmt_get_result($stmtOld);
        $oldRow = mysqli_fetch_assoc($result);
        
        if ($oldRow && !empty($oldRow[$expectedRutaField])) {
            $oldFilePath = $oldRow[$expectedRutaField];
        }
        
        mysqli_stmt_close($stmtOld);
    }
} catch (Exception $e) {
    // Continuar aunque falle esta consulta
}

// ============================================
// INSERT ... ON DUPLICATE KEY UPDATE
// ============================================
try {
    $sql = "INSERT INTO " . $tableName . " (" . $expectedIdField . ", " . $expectedRutaField . ") " .
           "VALUES (?, ?) " .
           "ON DUPLICATE KEY UPDATE " . $expectedRutaField . " = VALUES(" . $expectedRutaField . ")";

    $stmt = mysqli_prepare($conexion, $sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conexion));
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $recordId, $rutaArchivo);
    mysqli_stmt_execute($stmt);
    
    $affectedRows = mysqli_stmt_affected_rows($stmt);
    
    if ($affectedRows >= 0) {
        // Eliminar foto anterior si existe
        if ($oldFilePath && file_exists($oldFilePath) && $oldFilePath !== $filePath) {
            @unlink($oldFilePath);
        }

        // Construir URL pública
        $documentRoot = $_SERVER['DOCUMENT_ROOT'];
        $relativePath = str_replace($documentRoot, '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $hostUrl  = $_SERVER['HTTP_HOST'];
        $imageUrl = $protocol . '://' . $hostUrl . $relativePath;

        $action = ($affectedRows === 1) ? 'insertada' : 'actualizada';

        echo json_encode(array(
            'success'   => true,
            'message'   => 'Imagen ' . $action . ' correctamente',
            'imageUrl'  => $imageUrl,
            'imagePath' => $filePath,
            'action'    => $action
        ));
    }
    
    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    // Si falla la BD, eliminar el archivo subido
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    echo json_encode(array(
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ));
}
?>