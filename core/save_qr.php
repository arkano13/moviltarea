<?php
// save_qr.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/cors.php';

$conexion = Database::getInstance();

// 1. Recibir datos (compatibilidad PHP 5.4 sin operador ??)
$usuario_id  = isset($_POST['usuario_id']) ? $_POST['usuario_id'] : null;
$nombre      = isset($_POST['usuario_nombre']) ? $_POST['usuario_nombre'] : '';
$correo      = isset($_POST['correo']) ? $_POST['correo'] : '';
$clave       = isset($_POST['clave_temporal']) ? $_POST['clave_temporal'] : '';
$expira      = isset($_POST['expira']) ? $_POST['expira'] : '';
$contenido   = isset($_POST['contenido_qr']) ? $_POST['contenido_qr'] : '';
$pin = str_pad(random_int(0, 999999), 4, '0', STR_PAD_LEFT);

if ($usuario_id === null || $usuario_id === '' || trim($nombre) === '' || trim($correo) === '')
{
    echo json_encode(array("ok" => false, "msg" => "Faltan datos del usuario"));
    exit;
}

//Validar que se recibió el archivo QR
if (!isset($_FILES['qr_imagen']) || $_FILES['qr_imagen']['error'] !== UPLOAD_ERR_OK) 
{
    echo json_encode(array("ok" => false, "msg" => "No se recibió la imagen QR"));
    exit;
}

$file = $_FILES['qr_imagen'];

// Validar que sea PNG usando finfo (compatible PHP 5.3+)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mime !== 'image/png') 
{
    echo json_encode(array("ok" => false, "msg" => "El archivo debe ser PNG. Tipo detectado: " . $mime));
    exit;
}

// 3. Iniciar transacción (PHP 5.4 usa autocommit)
mysqli_autocommit($conexion, false);
$error_ocurrido = false;

try 
{
    $carpeta = __DIR__ . '/../api/uploads/tbl_usuario_qr/';

    if (!is_dir($carpeta)) 
    {
        mkdir($carpeta, 0755, true);
    }

    $nombreArchivo = "qr_" . $usuario_id . "_" . time() . ".png";
    $rutaAbsoluta  = $carpeta . $nombreArchivo;
    $rutaRelativa  = "api/uploads/tbl_usuario_qr/" . $nombreArchivo;

    if (!move_uploaded_file($file['tmp_name'], $rutaAbsoluta)) 
    {
        throw new Exception("No se pudo guardar el archivo en el servidor");
    }

    // 6. INSERT o UPDATE en tbl_usuario_qr
    // Sentencia SQL
    $sql_insert = "INSERT INTO tbl_usuario_qr 
                (usuario_id, contenido, ruta_imagen, tipo, usado, creado_en, pin)
                VALUES (?, ?, ?, 'registro', 0, NOW(), ?)
                ON DUPLICATE KEY UPDATE
                contenido   = VALUES(contenido),
                ruta_imagen = VALUES(ruta_imagen),
                tipo        = VALUES(tipo),
                usado       = 0,
                creado_en   = NOW(),
                pin         = VALUES(pin)";

    $stmt_insert = mysqli_prepare($conexion, $sql_insert);

    // "i" = integer (usuario_id)
    // "s" = string (contenido, ruta_imagen, pin)
    mysqli_stmt_bind_param($stmt_insert, "isss", $usuario_id, $contenido, $rutaRelativa, $pin);

    if (!mysqli_stmt_execute($stmt_insert)) {
        throw new Exception("Error al guardar en BD: " . mysqli_error($conexion));
    }

    // Verificar si fue INSERT o UPDATE
    $filasAfectadas = mysqli_stmt_affected_rows($stmt_insert);
    if ($filasAfectadas === 1) {
        // Fue un INSERT nuevo
        $accion = 'insertado';
    } elseif ($filasAfectadas === 2) {
        // Fue un UPDATE (registro existente actualizado)
        $accion = 'actualizado';
    }

    mysqli_stmt_close($stmt_insert);

    // 7. Confirmar transacción
    mysqli_commit($conexion);
    mysqli_autocommit($conexion, true); // Restaurar comportamiento normal

//========================================================================================================================================================================
    $_GET['usuario_id'] = isset($usuario_id) ? $usuario_id : 0; 

    //PLANTILLA para envios en carpeta de reportes API==============================
    $template_path = __DIR__ . '/../api/reportes/email_userqr.php';

    if (!file_exists($template_path)) 
    {
        echo json_encode([
        "ok" => false, 
        "msg" => "Plantilla no encontrada en: " . $template_path
        ]);
        exit;
    }

    try 
    {
    
    //Capturar la plantilla
    ob_start();
    include $template_path;
    $htmlBody = ob_get_clean();
    
    // Verificar si la plantilla quedó vacía (significa que falló internamente)
    if (empty($htmlBody) || strlen($htmlBody) < 50) 
    {
        throw new Exception("La plantilla se generó vacía o con errores. Revisa email_userqr.php");
    }

    //Configurar PHPMailer
    require_once __DIR__ . '/../api/phpmailer/class.phpmailer.php';
    require_once __DIR__ . '/../api/phpmailer/class.smtp.php';

    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->SMTPDebug = 0; // Cambia a 2 SOLO si necesitas depurar el envío
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ymarmol37@gmail.com';
    $mail->Password   = 'tlts eobb mpho uqfu'; // Tu contraseña de aplicación
    $mail->SMTPSecure = 'tls'; 
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';
    $mail->Timeout    = 15; // Timeout de 15 segundos para evitar que se cuelgue

    $mail->setFrom('ymarmol37@gmail.com', 'APPMOVIL');
    $mail->addAddress($correo, $nombre);

    $mail->isHTML(true);
    $mail->Subject = 'Tu Código QR y PIN de Acceso';
    $mail->msgHTML($htmlBody); 
    
    //$mail->AltBody = "Hola " . $nombre . ".\n\nTu PIN: " . $pin . "\n\nEscanea el QR adjunto.";

    //Si desea agregar adjuntos=====================================================================
    // Descomenta esto cuando estés listo:
    // if (file_exists($rutaAbsoluta)) {
    //     $mail->addAttachment($rutaAbsoluta, "codigo_acceso.png");
    // }

    if ($mail->send()) 
    {
        // 6. RESPUESTA JSON EXITOSA (La app podrá leer esto)
        /*echo json_encode([
            "ok"            => true,
            "msg"           => "Correo enviado exitosamente",
            "usuario_id"    => (int)$usuario_id,
            "ruta_qr"       => $rutaRelativa ?? ''
        ]);*/
        $emailOk="Correo enviado exitosamente";
    } 
    else 
    {
        throw new Exception("Error de PHPMailer: " . $mail->ErrorInfo);
    }

} 
catch (Exception $e) 
{
    // 7. RESPUESTA JSON DE ERROR (La app podrá leer esto y mostrar el Alert)
    echo json_encode([
        "ok"  => false,
        "msg" => $e->getMessage()
    ]);
}

//=========================================================================================================================================================================
    // 9. Respuesta exitosa
    echo json_encode(array(
        "ok"             => true,
        "usuario_id"     => (int)$usuario_id,
        "ruta_qr"        => $rutaRelativa,
        "email_enviado"  => $emailOk,
        "email_error"    => $emailOk ? null : $emailErr,
        "clave_temporal" => $clave
    ));
} 
catch (Exception $e) 
{
    // Revertir transacción en caso de error
    mysqli_rollback($conexion);
    mysqli_autocommit($conexion, true);
    
    // Intentar eliminar el archivo si se creó pero falló la BD
    if (isset($rutaAbsoluta) && file_exists($rutaAbsoluta)) {
        @unlink($rutaAbsoluta);
    }

    echo json_encode(array("ok" => false, "msg" => $e->getMessage()));
}
?>