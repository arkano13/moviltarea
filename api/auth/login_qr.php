<?php
// core/validar_login_qr.php
require_once '../../config/database.php';
require_once '../../config/cors.php';

$conexion = Database::getInstance();

// 1. Recibir datos
$qr_data = isset($_POST['qr_data']) ? trim($_POST['qr_data']) : '';
$pin_ingresado = isset($_POST['pin']) ? trim($_POST['pin']) : '';

if (empty($qr_data) || empty($pin_ingresado)) 
{
    echo json_encode(["ok" => false, "msg" => "Faltan datos (QR o PIN)"]);
    exit;
}

// 2. Decodificar el contenido del QR (esperamos un JSON como el que generamos)
$data = json_decode($qr_data, true);

if (!$data || !isset($data['user_id'])) 
{
    echo json_encode(["ok" => false, "msg" => "El código QR no es válido o está corrupto"]);
    exit;
}

$usuario_id = (int)$data['user_id'];

try {
    // 3. Verificar en la BD que el usuario tiene ese QR y PIN registrados
    $stmt = mysqli_prepare($conexion, "SELECT pin FROM tbl_usuario_qr WHERE usuario_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $qr_registro = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$qr_registro) 
    {
        echo json_encode(["ok" => false, "msg" => "Este código QR no está registrado en el sistema"]);
        exit;
    }

    // 4. Validar el PIN
    if ($qr_registro['pin'] !== $pin_ingresado) 
    {
        echo json_encode(["ok" => false, "msg" => "El PIN ingresado es incorrecto"]);
        exit;
    }

    // 5. Si todo es correcto, obtener los datos del usuario para la sesión

    $sql="SELECT 
            us.usuario_id, 
            us.usuario_nombre, 
            us.usuario_clave, 
            us.usuario_nombrecomp, 
            us.usuario_correo,
            us.usuario_telefono,
            us.empresa_id,
            ifnull(usi.usuario_img_ruta,'') as ruta
        FROM tbl_usuario us 
        left outer join tbl_usuario_img usi on us.usuario_id = usi.usuario_id
        WHERE us.usuario_id = ?      
          LIMIT 1";

    //$stmt_user = mysqli_prepare($conexion, "SELECT usuario_id, usuario_nombre, usuario_nombrecomp, usuario_correo FROM tbl_usuario WHERE usuario_id = ?");
    $stmt_user = mysqli_prepare($conexion,$sql);
    mysqli_stmt_bind_param($stmt_user, "i", $usuario_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    $usuario = mysqli_fetch_assoc($result_user);
    mysqli_stmt_close($stmt_user);

    if (!$usuario) 
    {
        echo json_encode(["ok" => false, "msg" => "Usuario no encontrado en la base de datos"]);
        exit;
    }

    // 6. Éxito
    echo json_encode([
        "ok"      => true,
        "msg"     => "Acceso concedido",
        'usuario' => [
                'id'              => $usuario['usuario_id'],
                'usuario'         => $usuario['usuario_nombre'],
                'nombre_completo' => $usuario['usuario_nombrecomp'],
                'correo'          => $usuario['usuario_correo'],
                'telefono'        => $usuario['usuario_telefono'],
                'empresa_id'      => $usuario['empresa_id'],
                'ruta'      => $url.$usuario['ruta']
        ]
    ]);

} catch (Exception $e) 
{
    echo json_encode(["ok" => false, "msg" => "Error del servidor: " . $e->getMessage()]);
}
?>