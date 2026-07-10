<?php
ob_start();
require_once '../../config/cors.php';
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['exito' => false, 'mensaje' => 'Error interno del servidor', 'error' => $error['message']], JSON_UNESCAPED_UNICODE);
    }
});

try {
    $conexion = Database::getInstance();
    if (!$conexion) {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    $resultado = mysqli_query($conexion, "
        SELECT usuario_id, usuario_nombre, usuario_nombrecomp
        FROM tbl_usuario
        ORDER BY usuario_nombrecomp ASC
    ");

    if (!$resultado) {
        throw new Exception('Error en la consulta: ' . mysqli_error($conexion));
    }

    $usuarios = [];
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $usuarios[] = $fila;
    }

    ob_clean();
    echo json_encode([
        'exito' => true,
        'usuarios' => $usuarios
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>