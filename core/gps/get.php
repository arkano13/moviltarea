<?php
//core/gps/get.php
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/cors.php';

 $conn = Database::getInstance();

$result = $conn->query("SELECT * FROM tbl_puntos_gps ORDER BY fecha_creacion DESC");
$puntos = [];

while($row = $result->fetch_assoc()) 
{
    $puntos[] = $row;
}

echo json_encode($puntos);
?>