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
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);

    if (empty($input) || empty($input['usuario_id'])) {
        throw new Exception('Falta el usuario_id.');
    }

    $usuario_id = (int)$input['usuario_id'];

    $conexion = Database::getInstance();
    if (!$conexion) {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    $stmt = mysqli_prepare($conexion, "
        SELECT
            m.modulo_codigo,
            m.modulo_nombre,
            m.modulo_tipo,
            m.modulo_activity,
            m.modulo_estado,
            IFNULL(a.acceso_estado, 0) AS acceso_estado
        FROM tbl_modulo m
        LEFT JOIN tbl_acceso a
            ON a.modulo_codigo = m.modulo_codigo
            AND a.usuario_id = ?
        ORDER BY m.modulo_codigo ASC
    ");

    if (!$stmt) {
        throw new Exception('Error en la consulta: ' . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, 'i', $usuario_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    $modulos = [];
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $fila['acceso_estado'] = (int)$fila['acceso_estado'];
        $modulos[] = $fila;
    }
    mysqli_stmt_close($stmt);

    ob_clean();
    echo json_encode([
        'exito' => true,
        'modulos' => $modulos
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>