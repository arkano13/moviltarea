<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido. Use POST.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['fecha_inicial']) || !isset($input['fecha_final'])) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros: fecha_inicial y fecha_final']);
    exit();
}

$fechaInicial = trim($input['fecha_inicial']);
$fechaFinal   = trim($input['fecha_final']);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicial) || 
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFinal)) {
    echo json_encode(['status' => 'error', 'message' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
    exit();
}

$conexion = Database::getInstance();

if (!$conexion) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a BD']);
    exit();
}

$sql = "SELECT bitacora_id, usuario_id, dispo_unique_id, ip_origen, 
               bitacora_accion, tabla_afectada, registro_id,
               datos_anteriores, datos_nuevos, bitacora_estado, bitacora_fecha
        FROM tbl_bitacora
        WHERE DATE(bitacora_fecha) BETWEEN ? AND ?
        ORDER BY bitacora_fecha DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param('ss', $fechaInicial, $fechaFinal);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

if (count($data) > 0) {
    echo json_encode([
        'status' => 'success',
        'total'  => count($data),
        'data'   => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'status'  => 'warning',
        'message' => 'No se encontraron registros en el rango de fechas',
        'total'   => 0,
        'data'    => []
    ]);
}

$stmt->close();
$conexion->close();
?>