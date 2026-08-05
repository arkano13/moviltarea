<?php
//header('Content-Type: application/json; charset=utf-8'); // 👈 Clave: declarar UTF-8
// 3️⃣ Conectar a la BD
require_once __DIR__ . '/../../config/database.php';
//require_once '../../config/cors.php';

$conexion= Database::getInstance();

// Recibir usuario_id por GET
$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;


$stmt_user = mysqli_prepare($conexion, "
    SELECT usuario_nombre, usuario_nombrecomp, usuario_correo 
    FROM tbl_usuario 
    WHERE usuario_id = ?
");
mysqli_stmt_bind_param($stmt_user, "i", $usuario_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$usuario = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

$stmt_qr = mysqli_prepare($conexion, "
    SELECT contenido, ruta_imagen, pin, creado_en 
    FROM tbl_usuario_qr 
    WHERE usuario_id = ?
");
mysqli_stmt_bind_param($stmt_qr, "i", $usuario_id);
mysqli_stmt_execute($stmt_qr);
$result_qr = mysqli_stmt_get_result($stmt_qr);
$qr_data = mysqli_fetch_assoc($result_qr);
mysqli_stmt_close($stmt_qr);


$qr_url = 'http://kisna.bonaquian.com/movil2/' . $qr_data['ruta_imagen'];
// Formatear fecha
$fecha_generacion = date('d/m/Y H:i', strtotime($qr_data['creado_en']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu Código de Acceso QR</title>
    <style>
        /* (Pega aquí los mismos estilos CSS que te di en el mensaje anterior) */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: #667eea; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .pin-section { background: #f5576c; color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0; }
        .pin-code { font-size: 40px; font-weight: bold; letter-spacing: 5px; background: rgba(255,255,255,0.2); padding: 10px; border-radius: 5px; display: inline-block; }
        .qr-section { text-align: center; margin: 20px 0; }
        .qr-section img { max-width: 250px; border: 3px solid #667eea; border-radius: 10px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #777; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Tu Código de Acceso</h1>
            <p>Sistema de Gestión Bonaquian</p>
        </div>
        <div class="content">
            <h2>¡Hola, <?php echo htmlspecialchars($usuario['usuario_nombrecomp']); ?>!</h2>
            <p>Tu código QR y PIN de acceso han sido generados exitosamente.</p>
            
            <div class="pin-section">
                <h3>TU PIN DE ACCESO</h3>
                <div class="pin-code"><?php echo htmlspecialchars($qr_data['pin']); ?></div>
            </div>

            <div class="qr-section">
                <h3>📱 Escanea tu Código QR</h3>
                <img src="<?php echo $qr_url; ?>" alt="Código QR">
            </div>

            <p><strong>Usuario:</strong> <?php echo htmlspecialchars($usuario['usuario_nombre']); ?></p>
            <p><strong>Correo:</strong> <?php echo htmlspecialchars($usuario['usuario_correo']); ?></p>
            <p><strong>Generado:</strong> <?php echo $fecha_generacion; ?></p>
        </div>
        <div class="footer">
            <p>Sistema Bonaquian - Correo automático</p>
        </div>
    </div>
</body>
</html>