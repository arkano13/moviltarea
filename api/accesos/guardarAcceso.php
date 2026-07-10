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

  if (!isset($input['usuario_id']) || !isset($input['modulo_codigo']) || $input['modulo_codigo'] === '') {
    throw new Exception('Faltan datos: usuario_id y modulo_codigo son obligatorios.');
}   

    $usuario_id = (int)$input['usuario_id'];
    $modulo_codigo = trim($input['modulo_codigo']);
    $acceso_estado = isset($input['acceso_estado']) ? (int)$input['acceso_estado'] : 0;

    $conexion = Database::getInstance();
    if (!$conexion) {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    $stmtCheck = mysqli_prepare($conexion, "
        SELECT usuario_id FROM tbl_acceso
        WHERE usuario_id = ? AND modulo_codigo = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmtCheck, 'is', $usuario_id, $modulo_codigo);
    mysqli_stmt_execute($stmtCheck);
    $resultado = mysqli_stmt_get_result($stmtCheck);
    $existe = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmtCheck);

    if ($existe) {
        $stmt = mysqli_prepare($conexion, "
            UPDATE tbl_acceso
            SET acceso_estado = ?
            WHERE usuario_id = ? AND modulo_codigo = ?
        ");
        mysqli_stmt_bind_param($stmt, 'iis', $acceso_estado, $usuario_id, $modulo_codigo);
    } else {
        $stmt = mysqli_prepare($conexion, "
            INSERT INTO tbl_acceso (usuario_id, modulo_codigo, acceso_estado)
            VALUES (?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'isi', $usuario_id, $modulo_codigo, $acceso_estado);
    }

    if (!$stmt) {
        throw new Exception('Error en la consulta: ' . mysqli_error($conexion));
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    ob_clean();
    echo json_encode([
        'exito' => true,
        'mensaje' => 'Acceso actualizado correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>