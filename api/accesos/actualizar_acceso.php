<?php
// api/accesos/actualizar_acceso.php
// Inserta o actualiza el estado de acceso de un usuario a un módulo (lo que dispara el switch)
//
// ⚠️ IMPORTANTE: esta consulta usa INSERT ... ON DUPLICATE KEY UPDATE, por lo que
// tbl_acceso necesita una llave única sobre (usuario_id, modulo_codigo).
// Si no la tienes, ejecuta antes en tu base de datos:
//
//   ALTER TABLE tbl_acceso
//   ADD UNIQUE KEY uq_usuario_modulo (usuario_id, modulo_codigo);

header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['usuario_id']) || !isset($input['modulo_codigo']) || !isset($input['acceso_estado'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Faltan parámetros: usuario_id, modulo_codigo y acceso_estado son requeridos'
    ]);
    exit();
}

$usuario_id     = $input['usuario_id'];
$modulo_codigo  = $input['modulo_codigo'];
$acceso_estado  = (int)$input['acceso_estado']; // 1 = activo, 0 = inactivo

$conexion = Database::getInstance();

if (!$conexion) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ]);
    exit();
}

$sql = "INSERT INTO tbl_acceso (usuario_id, modulo_codigo, acceso_estado)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE acceso_estado = VALUES(acceso_estado)";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al preparar la consulta',
        'error' => $conexion->error
    ]);
    $conexion->close();
    exit();
}

$stmt->bind_param('isi', $usuario_id, $modulo_codigo, $acceso_estado);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al ejecutar la consulta',
        'error' => $stmt->error
    ]);
    $stmt->close();
    $conexion->close();
    exit();
}

echo json_encode([
    'status' => 'success',
    'message' => 'Acceso actualizado correctamente',
    'usuario_id' => $usuario_id,
    'modulo_codigo' => $modulo_codigo,
    'acceso_estado' => $acceso_estado
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$stmt->close();
$conexion->close();
?>