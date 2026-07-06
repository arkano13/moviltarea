<?php

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../../config/database.php';

try {

    $conexion = Database::getInstance();

    if ($conexion) {

        echo json_encode([
            "conectado" => true,
            "mensaje" => "Conexión establecida correctamente"
        ], JSON_UNESCAPED_UNICODE);

    } else {

        echo json_encode([
            "conectado" => false,
            "mensaje" => "Sin conexión a la base de datos"
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {

    http_response_code(503);

    echo json_encode([
        "conectado" => false,
        "mensaje" => "Error de conexión"
    ], JSON_UNESCAPED_UNICODE);
}