<?php
// api/accesos/registrar_usuario.php
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/database.php';
require_once '../../config/cors.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function()
{
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]))
    {
        http_response_code(500);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error interno del servidor',
            'error' => $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try
{
    // 1️⃣ Recibir datos JSON del body
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);

    if (!$input)
    {
        throw new Exception('Datos inválidos. Se esperaba JSON.');
    }

    $usuario     = trim($input['nombre'] ?? '');
    $clave       = trim($input['clave'] ?? '');
    $nombreComp  = trim($input['nombreComp'] ?? '');
    $correo      = trim($input['correo'] ?? '');
    $telefono    = trim($input['telefono'] ?? '');
    // empresa_id se fuerza siempre a 0, sin importar lo que llegue del formulario
    $empresa_id  = 0;

    // 2️⃣ Validar campos obligatorios
    if (empty($usuario) || empty($clave) || empty($nombreComp) || empty($correo) || empty($telefono))
    {
        throw new Exception('Todos los campos marcados con * son obligatorios.');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL))
    {
        throw new Exception('El correo electrónico no es válido.');
    }

    $conexion = Database::getInstance();

    if (!$conexion)
    {
        throw new Exception('No se pudo conectar a la base de datos.');
    }

    // 3️⃣ Verificar que no exista un usuario duplicado (mismo nombre de usuario
    //    O mismo correo, dentro de la misma empresa). Esto evita registros
    //    duplicados ANTES de intentar el insert.
    $stmtCheck = mysqli_prepare($conexion, "
        SELECT usuario_id, usuario_nombre, usuario_correo
        FROM tbl_usuario
        WHERE empresa_id = ?
          AND (usuario_nombre = ? OR usuario_correo = ?)
        LIMIT 1
    ");

    if (!$stmtCheck)
    {
        throw new Exception('Error en la consulta SQL: ' . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmtCheck, 'iss', $empresa_id, $usuario, $correo);
    mysqli_stmt_execute($stmtCheck);
    $resultCheck = mysqli_stmt_get_result($stmtCheck);
    $existente = mysqli_fetch_assoc($resultCheck);
    mysqli_stmt_close($stmtCheck);

    if ($existente)
    {
        // Determinar cuál campo específico está duplicado para dar un
        // mensaje claro al usuario
        if (strcasecmp($existente['usuario_nombre'], $usuario) === 0)
        {
            $mensajeDuplicado = 'Ya existe un usuario registrado con ese nombre de usuario.';
        }
        else
        {
            $mensajeDuplicado = 'Ya existe un usuario registrado con ese correo electrónico.';
        }

        http_response_code(409);
        echo json_encode([
            'exito' => false,
            'mensaje' => $mensajeDuplicado
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4️⃣ Insertar el nuevo usuario
    $stmt = mysqli_prepare($conexion, "
        INSERT INTO tbl_usuario
            (usuario_nombre, usuario_clave, usuario_nombrecomp, usuario_correo,
             usuario_telefono, empresa_id, usuario_fcreacion, usuario_factualizacion)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    if (!$stmt)
    {
        throw new Exception('Error en la consulta SQL: ' . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, 'sssssi', $usuario, $clave, $nombreComp, $correo, $telefono, $empresa_id);

    if (mysqli_stmt_execute($stmt))
    {
        $nuevoId = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        echo json_encode([
            'exito' => true,
            'mensaje' => 'Usuario registrado correctamente.',
            'usuario' => [
                'id'              => $nuevoId,
                'usuario'         => $usuario,
                'nombre_completo' => $nombreComp,
                'correo'          => $correo,
                'telefono'        => $telefono,
                'empresa_id'      => $empresa_id
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    else
    {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('No se pudo guardar el usuario: ' . $err);
    }
}
catch (Exception $e)
{
    http_response_code(400);
    echo json_encode([
        'exito' => false,
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>