<?php
// api/accesos/listar_modulos_acceso.php
// Dado un usuario_id, devuelve TODOS los módulos (MODULO y ACCION) indicando
// si el usuario tiene o no acceso activo a cada uno (para pintar los switches).

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

if (!isset($input['usuario_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Faltan parámetros: usuario_id es requerido'
    ]);
    exit();
}

$usuario_id = $input['usuario_id'];

$conexion = Database::getInstance();

if (!$conexion) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ]);
    exit();
}

// LEFT JOIN: si no existe fila en tbl_acceso para ese usuario, el módulo sale con estado 0 (sin acceso)
$sql = "SELECT
            m.modulo_codigo,
            m.modulo_nombre,
            m.modulo_tipo,
            m.modulo_activity,
            m.modulo_estado,
            COALESCE(ac.acceso_estado, 0) AS acceso_estado
        FROM tbl_modulo m
        LEFT JOIN tbl_acceso ac
            ON ac.modulo_codigo = m.modulo_codigo
            AND ac.usuario_id = ?
        WHERE m.modulo_estado = 'ACTIVO'
        ORDER BY m.modulo_codigo + 0 ASC";

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

$stmt->bind_param('i', $usuario_id);

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

$resultado = $stmt->get_result();

$modulos = [];
while ($fila = $resultado->fetch_assoc()) {
    $modulos[] = $fila;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Consulta realizada correctamente',
    'usuario_id' => $usuario_id,
    'total' => count($modulos),
    'data' => $modulos
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$stmt->close();
$conexion->close();
?>