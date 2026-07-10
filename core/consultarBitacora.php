<?php
//Agregar en api/logs/consultarBitacora.php
header('Content-Type: application/json; charset=utf-8'); // 👈 Clave: declarar UTF-8
// 3️⃣ Conectar a la BD
require_once '../../config/database.php';
require_once '../../config/cors.php';

// Capturar errores fatales
error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') 
{
    http_response_code(200);
    exit();
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') 
{
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit();
}

// ============================================
// RECIBIR DATOS JSON
// ============================================
$input = json_decode(file_get_contents('php://input'), true);

// Validar que se recibieron los datos
if (!isset($input['fecha_inicial']) || !isset($input['fecha_final'])) 
{
    echo json_encode([
        'status' => 'error',
        'message' => 'Faltan parámetros: fecha_inicial y fecha_final son requeridos'
    ]);
    exit();
}

$fechaInicial = trim($input['fecha_inicial']);
$fechaFinal   = trim($input['fecha_final']);

// Validar formato de fechas (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicial) || 
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFinal)) 
{
    echo json_encode([
        'status' => 'error',
        'message' => 'Formato de fecha inválido. Use YYYY-MM-DD'
    ]);
    exit();
}

//Conexion a BD==========================================================

$sql = "SELECT bitacora_id, usuario_id, dispo_unique_id, ip_origen,
               bitacora_accion, tabla_afectada, registro_id,
               bitacora_estado, bitacora_fecha
        FROM tbl_bitacora
        WHERE DATE(bitacora_fecha) BETWEEN ? AND ?
        ORDER BY bitacora_fecha DESC";

$conexion = Database::getInstance();

if (!$conexion) 
{
    throw new Exception('No se pudo conectar a la base de datos.');
}

// Preparar la consulta
$stmt = $conexion->prepare($sql);

if (!$stmt) 
{
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al preparar la consulta',
        'error' => $conexion->error
    ]);
    $conexion->close();
    exit();
}

// Vincular parámetros (ss = dos strings)
$stmt->bind_param('ss', $fechaInicial, $fechaFinal);

// Ejecutar consulta
if (!$stmt->execute()) 
{
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

// Obtener resultados
$resultado = $stmt->get_result();

// Verificar si hay resultados
if ($resultado->num_rows === 0) 
{
    echo json_encode([
        'status' => 'warning',
        'message' => 'No se encontraron registros de bitácora en el rango de fechas',
        'total' => 0,
        'data' => []
    ]);
    $stmt->close();
    $conexion->close();
    exit();
}

// Convertir resultados a array asociativo
$bitacora = [];
while ($fila = $resultado->fetch_assoc()) 
{
    $bitacora[] = $fila;
}

// ============================================
// RESPUESTA EXITOSA
// ============================================
echo json_encode([
    'status' => 'success',
    'message' => 'Consulta realizada correctamente',
    'total' => count($bitacora),
    'fecha_inicial' => $fechaInicial,
    'fecha_final' => $fechaFinal,
    'data' => $bitacora
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ============================================
// CERRAR CONEXIONES
// ============================================
$stmt->close();
$conexion->close();
?>