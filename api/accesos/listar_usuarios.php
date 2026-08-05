<?php
// api/accesos/listar_usuarios.php
// Devuelve la lista de usuarios para llenar el comboBox de la Pantalla de Control de Accesos

header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Aceptamos GET (no necesita body)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use GET.'
    ]);
    exit();
}

$conexion = Database::getInstance();

if (!$conexion) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos.'
    ]);
    exit();
}

$sql = "SELECT
            usuario_id,
            usuario_nombre,
            usuario_nombrecomp,
            usuario_correo,
            usuario_telefono
        FROM tbl_usuario
        ORDER BY usuario_nombre ASC";

$resultado = $conexion->query($sql);

if (!$resultado) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al ejecutar la consulta',
        'error' => $conexion->error
    ]);
    $conexion->close();
    exit();
}

$usuarios = [];
while ($fila = $resultado->fetch_assoc()) {
    $usuarios[] = $fila;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Consulta realizada correctamente',
    'total' => count($usuarios),
    'data' => $usuarios
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$conexion->close();
?>