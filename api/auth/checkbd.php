<?php
// api/auth/checkConnection.php
header('Content-Type: application/json; charset=utf-8'); // 👈 Clave: declarar UTF-8
require_once '../../config/database.php'; 
require_once '../../config/cors.php';

try 
{
    $conexion = Database::getInstance();
    
    if (!$conexion) 
    {
        throw new Exception("Conexión nula");
    }

    $version_mysql = mysqli_get_server_info($conexion);
    
    // 👇 JSON_UNESCAPED_UNICODE evita que é, ñ, á se conviertan en \u00e9
    echo json_encode([
        'conectado'        => true,
        'mensaje'          => 'Conexión establecida correctamente', // Ahora se verá bien
        'base_de_datos'    => Database::getDatabaseName(),
        'version_servidor' => $version_mysql,
        'timestamp'        => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} 
catch (Exception $e) 
{
    http_response_code(503);
    echo json_encode([
        'conectado' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>