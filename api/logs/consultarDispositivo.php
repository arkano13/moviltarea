<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:8081");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL);
ini_set("display_errors", 0);

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => "error",
        "message" => "Método no permitido. Use POST."
    ]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input["usuario_id"])) {
    echo json_encode([
        "status" => "error",
        "message" => "Falta el parámetro usuario_id"
    ]);
    exit();
}

$usuario_id = (int)$input["usuario_id"];

$conexion = Database::getInstance();

if (!$conexion) {
    echo json_encode([
        "status" => "error",
        "message" => "No se pudo conectar a la base de datos"
    ]);
    exit();
}

$sql = "SELECT
            m.modulo_codigo,
            m.modulo_nombre,
            m.modulo_nombre AS modulo_descripcion,
            m.modulo_activity,
            m.modulo_estado
        FROM tbl_acceso ac
        INNER JOIN tbl_modulo m
            ON ac.modulo_codigo = m.modulo_codigo
        WHERE ac.usuario_id = ?
          AND ac.acceso_estado = 1
          AND m.modulo_tipo = 'MODULO'
        ORDER BY m.modulo_nombre";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => $conexion->error
    ]);
    exit();
}

$stmt->bind_param("i", $usuario_id);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => $stmt->error
    ]);
    exit();
}

$resultado = $stmt->get_result();

$data = [];

while ($fila = $resultado->fetch_assoc()) {
    $data[] = $fila;
}

echo json_encode([
    "status" => count($data) ? "success" : "warning",
    "message" => count($data) ? "Consulta realizada correctamente" : "No se encontraron dispositivos",
    "total" => count($data),
    "data" => $data
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conexion->close();