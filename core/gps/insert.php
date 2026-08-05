<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

$latitud = $_POST['latitud'];
$longitud = $_POST['longitud'];
$nombre = $_POST['nombre'];

$conn = Database::getInstance();

$stmt = $conn->prepare("INSERT INTO tbl_puntos_gps (latitud, longitud, nombre) VALUES (?, ?, ?)");
$stmt->bind_param("dds", $latitud, $longitud, $nombre);

if ($stmt->execute()) 
{
    echo json_encode(["success" => true, "message" => "Punto guardado"]);
} 
else 
{
    echo json_encode(["success" => false, "message" => "Error al guardar"]);
}

$stmt->close();
$conn->close();
?>