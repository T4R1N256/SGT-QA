<?php
// Rutas para usuario 

if ($path === '/api/getUsers') {//Ruta que obtiene la información de todos los usuarios

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }

    require_once __DIR__ . '/../models/usuario.php';

    $res = Usuario::readAllUsuario();

    http_response_code(200);
    echo json_encode(['ok' => true, 'usuarios' => $res]);

    //--Termina case getUsers

} elseif ($path === '/api/getUserInfo') { //Ruta para obtener información de un usuario

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }


    $ID_Usuario = campoEntero($_GET, 'id', false, 1) ?? (int) $_SESSION['ID_Usuario'];

    // Un empleado solo puede consultar su propia información (datos personales, RL01)
    if ($ID_Usuario !== (int) $_SESSION['ID_Usuario'] && !esAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']);
        return;
    }

    require_once __DIR__ . '/../models/usuario.php';

    $res = Usuario::readInfoUsuario($ID_Usuario);

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'usuarioinfo' => $res]);

    //--Termina case getUserInfo

} elseif ($path === '/api/createUser') { //Ruta para crear usuario 

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode((['ok' => false, 'error' => 'Método no permitido']));
        return;
    }

    $data = leerJson();
    $Password = campoPassword($data);
    $Nombre = campoTexto($data, 'Nombre', true, 100);
    $Puesto = campoOpcion($data, 'Puesto', ['admin', 'empleado']);
    $Telefono = campoTexto($data, 'Telefono', false, 20) ?? '';
    $Correo = campoTexto($data, 'Correo', false) ?? '';
    $Calle = campoTexto($data, 'Calle', false, 100) ?? '';
    $Colonia = campoTexto($data, 'Colonia', false, 100) ?? '';
    $Codigo_Postal = campoTexto($data, 'Codigo_Postal', false, 10) ?? '';

    require_once __DIR__ . '/../models/usuario.php';
    require_once __DIR__ . '/../models/empleado.php';
    require_once __DIR__ . '/../models/contacto_empleado.php';
    require_once __DIR__ . '/../config/database.php';


    $connection = new Conexion();


    try {
        $connection->beginTransaction();

        $resUsuario = Usuario::createUsuario($connection, $Password, 'autorizado');
        $resContacto = Contacto_Empleado::crearContacto(
            $connection,
            $Telefono,
            $Correo,
            $Calle,
            $Colonia,
            $Codigo_Postal
        );
        $resEmpleado = Empleado::crearEmpleado($connection, $Nombre, $Puesto, 'activo', $resContacto, $resUsuario);

        $connection->commit();
        $connection = NULL;

        http_response_code(201);
        echo json_encode(['ok' => true, 'mensaje' => 'Usuario e informacion creado correctamente']);

    } catch (Exception $e) {

        $connection->rollback();
        $connection = NULL;
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);

    }

    //--Termina case createUser

} elseif ($path === '/api/modifyInfoUser') { //Ruta para modificar informacion del usuario

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode((['ok' => false, 'error' => 'Método no permitido']));
        return;
    }

    $data = leerJson();
    $ID_Usuario = campoEntero($data, 'ID_Usuario', true, 1);
    $Nombre = campoTexto($data, 'Nombre', false, 100) ?: null;
    $Puesto = campoOpcion($data, 'Puesto', ['admin', 'empleado'], false);
    $Telefono = campoTexto($data, 'Telefono', false, 20);
    $Correo = campoTexto($data, 'Correo', false);
    $Calle = campoTexto($data, 'Calle', false, 100);
    $Colonia = campoTexto($data, 'Colonia', false, 100);
    $Codigo_Postal = campoTexto($data, 'Codigo_Postal', false, 10);

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../models/empleado.php';
    require_once __DIR__ . '/../models/usuario.php';
    require_once __DIR__ . '/../models/contacto_empleado.php';


    $IDs = Empleado::getIDsByUsuario($ID_Usuario);
    $infoActual = Usuario::readInfoUsuario($ID_Usuario);

    $Nombre = $Nombre ?? $infoActual['Nombre'];
    $Puesto = $Puesto ?? $infoActual['Puesto'];
    $Telefono = $Telefono ?? $infoActual['Telefono'];
    $Correo = $Correo ?? $infoActual['Correo'];
    $Calle = $Calle ?? $infoActual['Calle'];
    $Colonia = $Colonia ?? $infoActual['Colonia'];
    $Codigo_Postal = $Codigo_Postal ?? $infoActual['Codigo_Postal'];

    if (!$IDs) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado']);
        return;
    }

    $ID_Empleado = $IDs['ID_Empleado'];
    $ID_Contacto_Empleado = $IDs['ID_Contacto_Empleado'];

    try {
        $connection = new Conexion();

        $connection->beginTransaction();

        Empleado::updateEmpleado($connection, $ID_Empleado, $Nombre, $Puesto, "activo");
        Contacto_Empleado::updateContacto(
            $connection,
            $ID_Contacto_Empleado,
            $Telefono,
            $Correo,
            $Calle,
            $Colonia,
            $Codigo_Postal
        );
        $connection->commit();
        $connection = NULL;


        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Informacion actualizada correctamente']);


    } catch (Exception $e) {
        $connection->rollBack();
        $connection = NULL;
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
    }

    //--Termina case modifyInfoUser

} elseif ($path == '/api/modifyPasswordUser') { //Ruta para modificar password del usuario

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode((['ok' => false, 'error' => 'Método no permitido']));
        return;
    }


    $data = leerJson();
    $ID_Usuario = campoEntero($data, 'ID_Usuario', true, 1);
    $Password = campoPassword($data);

    require_once __DIR__ . '/../models/usuario.php';

    $res = Usuario::updatePassword($ID_Usuario, $Password);

    if ($res === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado']);
    } elseif ($res === 1) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Password modificada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar']);
    }


    //--Termina case modifyPasswordUser

} elseif ($path == '/api/modifyEstadoUser') { //Ruta para modificar estado del usuario

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode((['ok' => false, 'error' => 'Método no permitido']));
        return;
    }


    $data = leerJson();
    $ID_Usuario = campoEntero($data, 'ID_Usuario', true, 1);
    $Estado = campoOpcion($data, 'Estado', ['autorizado', 'no autorizado']);

    require_once __DIR__ . '/../models/usuario.php';


    $res = Usuario::updateEstado($ID_Usuario, $Estado);

    if ($res === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado']);
    } elseif ($res === 1) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Estado modificado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar']);
    }

    //-- Termina case modifyEstadoUser

} elseif ($path === '/api/deleteUser') { // Ruta para deshabilitar usuario

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode((['ok' => false, 'error' => 'Método no permitido']));
        return;
    }

    $data = leerJson();
    $ID_Usuario = campoEntero($data, 'ID_Usuario', true, 1);

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../models/empleado.php';
    require_once __DIR__ . '/../models/usuario.php';

    $IDs = Empleado::getIDsByUsuario($ID_Usuario);

    if (!$IDs) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado']);
        return;
    }

    $ID_Empleado = (int) $IDs['ID_Empleado'];

    $connection = new Conexion();

    try {
        $connection->beginTransaction();

        Empleado::desactivarEmpleado($connection, $ID_Empleado);
        Usuario::updateEstado($ID_Usuario, 'no autorizado', $connection);

        $connection->commit();
        $connection = NULL;

        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Empleado desactivado correctamente']);

    } catch (Exception $e) {
        $connection->rollBack();
        $connection = NULL;
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
    }

}//--Termina case deleteUser
