<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/cors.php';

$conexion = Database::getInstance();

$sql = "SELECT usuario_id, usuario_nombre, usuario_nombrecomp, usuario_correo 
        FROM tbl_usuario 
        ORDER BY usuario_nombre ASC";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_execute($stmt);

// Obtener el resultado (requiere el driver mysqlnd, estándar en PHP 5.4+)
$result = mysqli_stmt_get_result($stmt);

$usuarios = array();
while ($row = mysqli_fetch_assoc($result)) {
    $usuarios[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode(array(
    "ok" => true,
    "usuarios" => $usuarios
));
?>