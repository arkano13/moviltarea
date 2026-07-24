<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'exito' => false,
        'mensaje' => 'Método no permitido. Use POST.'
    ]);

    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        throw new Exception('No se recibieron datos válidos.');
    }

    $usuario = trim($input['usuario'] ?? '');
    $nombreCompleto = trim($input['nombre_completo'] ?? '');
    $correo = trim($input['correo'] ?? '');
    $telefono = trim($input['telefono'] ?? '');
    $clave = $input['clave'] ?? '';

    if (
        $usuario === '' ||
        $nombreCompleto === '' ||
        $correo === '' ||
        $telefono === '' ||
        $clave === ''
    ) {
        throw new Exception('Todos los campos son obligatorios.');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo electrónico no es válido.');
    }

    if (!ctype_digit($telefono)) {
        throw new Exception('El teléfono solo debe contener números.');
    }

    if (strlen($clave) < 6) {
        throw new Exception('La clave debe tener al menos 6 caracteres.');
    }

    $conexion = Database::getInstance();

    if (!$conexion) {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    $sqlVerificar = "
        SELECT usuario_id
        FROM tbl_usuario
        WHERE usuario_nombre = ?
           OR usuario_correo = ?
        LIMIT 1
    ";

    $stmtVerificar = $conexion->prepare($sqlVerificar);

    if (!$stmtVerificar) {
        throw new Exception(
            'Error al verificar el usuario: ' . $conexion->error
        );
    }

    $stmtVerificar->bind_param(
        'ss',
        $usuario,
        $correo
    );

    $stmtVerificar->execute();

    $resultadoVerificar = $stmtVerificar->get_result();

    if ($resultadoVerificar->num_rows > 0) {
        $stmtVerificar->close();

        echo json_encode([
            'exito' => false,
            'mensaje' => 'El nombre de usuario o correo ya está registrado.'
        ], JSON_UNESCAPED_UNICODE);

        exit();
    }

    $stmtVerificar->close();

    $claveGuardar = $clave;

    $sqlInsertar = "
        INSERT INTO tbl_usuario (
            usuario_nombre,
            usuario_clave,
            usuario_nombrecomp,
            usuario_correo,
            usuario_telefono,
            usuario_fultacceso,
            usuario_fcreacion,
            usuario_factualizacion,
            empresa_id
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW(),
            NOW(),
            NOW(),
            0
        )
    ";

    $stmtInsertar = $conexion->prepare($sqlInsertar);

    if (!$stmtInsertar) {
        throw new Exception(
            'Error al preparar el registro: ' . $conexion->error
        );
    }

    $stmtInsertar->bind_param(
        'sssss',
        $usuario,
        $claveGuardar,
        $nombreCompleto,
        $correo,
        $telefono
    );

    if (!$stmtInsertar->execute()) {
        throw new Exception(
            'No se pudo registrar el usuario: ' . $stmtInsertar->error
        );
    }

    $nuevoUsuarioId = $conexion->insert_id;

    $stmtInsertar->close();
    $conexion->close();

    echo json_encode([
        'exito' => true,
        'mensaje' => 'Usuario registrado correctamente.',
        'usuario' => [
            'usuario_id' => $nuevoUsuarioId,
            'usuario_nombre' => $usuario,
            'usuario_nombrecomp' => $nombreCompleto,
            'usuario_correo' => $correo,
            'usuario_telefono' => $telefono
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);

    echo json_encode([
        'exito' => false,
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}