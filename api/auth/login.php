<?php

ob_start();

require_once '../../config/cors.php';
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() 
{
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) 
    {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error interno del servidor',
            'error' => $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try 
{
    // 1️⃣ Recibir datos - soporta JSON y FormData
    $inputRaw = file_get_contents('php://input');
    $input = null;

    // Intentar JSON primero
    if (!empty($inputRaw)) {
        $input = json_decode($inputRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $input = null;
        }
    }

    // Si no hay JSON, intentar $_POST (FormData)
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    error_log("LOGIN RAW: [" . $inputRaw . "]");
    error_log("LOGIN POST: [" . json_encode($_POST) . "]");
    error_log("LOGIN INPUT FINAL: [" . json_encode($input) . "]");

    if (empty($input)) {
        throw new Exception('No se recibieron datos del cliente.');
    }

    $usuario    = trim($input['usuario_nombre'] ?? '');
    $clave      = $input['usuario_clave'] ?? '';
    $empresa_id = 0;

    // 2️⃣ Validar campos
    if (empty($usuario) || empty($clave)) 
    {
        throw new Exception('Usuario y contraseña son obligatorios.');
    }

    // 3️⃣ Conectar a la BD
    $conexion = Database::getInstance();
    
    if (!$conexion) 
    {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    // 4️⃣ Buscar el usuario
    $stmt = mysqli_prepare($conexion, "
        SELECT 
            usuario_id, 
            usuario_nombre, 
            usuario_clave, 
            usuario_nombrecomp, 
            usuario_correo,
            usuario_telefono,
            empresa_id
        FROM tbl_usuario 
        WHERE usuario_nombre = ? 
          AND empresa_id = ?
        LIMIT 1
    ");

    if (!$stmt) 
    {
        throw new Exception('Error en la consulta SQL: ' . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, 'si', $usuario, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // 5️⃣ Validar credenciales
    if (!$user || $clave != $user['usuario_clave']) 
    {
        http_response_code(401);
        ob_clean();
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Usuario o contraseña incorrectos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 6️⃣ ✅ LOGIN EXITOSO - Actualizar último acceso
    $updateStmt = mysqli_prepare($conexion, "
        UPDATE tbl_usuario 
        SET usuario_fultacceso = NOW() 
        WHERE usuario_id = ?
    ");
    mysqli_stmt_bind_param($updateStmt, 'i', $user['usuario_id']);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    ob_clean();
    echo json_encode([
        'exito' => true,
        'mensaje' => 'Bienvenido, ' . $user['usuario_nombrecomp'],
        'usuario' => [
            'id'              => $user['usuario_id'],
            'usuario'         => $user['usuario_nombre'],
            'nombre_completo' => $user['usuario_nombrecomp'],
            'correo'          => $user['usuario_correo'],
            'telefono'        => $user['usuario_telefono'],
            'empresa_id'      => $user['empresa_id']
        ]
    ], JSON_UNESCAPED_UNICODE);

} 
catch (Exception $e) 
{
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'exito' => false,
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/*function registrarBitacora($conexion, $usuario_id, $nombre_usuario_temp, $dispo_unique_id, 
                          $ip_origen, $accion, $tabla_afectada, $registro_id, 
                          $estado_operacion, $mensaje_error = null) 
{
    try 
    {
        $stmt = mysqli_prepare($conexion, "
            INSERT INTO tbl_bitacora 
            (usuario_id, dispo_unique_id, ip_origen, bitacora_accion, 
             tabla_afectada, registro_id, estado_operacion, mensaje_error, bitacora_fecha)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) return;

        mysqli_stmt_bind_param(
            $stmt, 
            'isssssss',
            $usuario_id,
            $dispo_unique_id,
            $ip_origen,
            $accion,
            $tabla_afectada,
            $registro_id,
            $estado_operacion,
            $mensaje_error
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } 
    catch (Exception $e) 
    {
        error_log("Error al registrar bitácora: " . $e->getMessage());
    }
}*/
?>