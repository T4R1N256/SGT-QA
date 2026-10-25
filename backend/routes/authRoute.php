<?php


if ($path === '/api/login') { //Ruta para inicio de sesion 

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }
    require_once __DIR__ . '/../models/login.php';

    $data = leerJson();
    $ID_Usuario = campoEntero($data, 'ID_Usuario', true, 1);
    $Password = campoPassword($data);


    $res = Login::validar($ID_Usuario, $Password);

    if ($res === -1) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado']);
    } elseif ($res === 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'mensaje' => 'Credenciales incorrectas']);
    } elseif ($res === -2) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'mensaje' => 'Usuario no autorizado']);
    } else {
        session_regenerate_id(true);

        $_SESSION['ID_Usuario'] = $ID_Usuario;
        $_SESSION['Puesto'] = $res['Puesto'];

        require_once __DIR__ . '/../models/caja.php';
        $caja = Caja::cajaAbierta();

        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'mensaje' => 'Login exitoso',
            'puesto' => $res['Puesto'],
            'id' => $ID_Usuario,
            'caja' => $caja ?: null,
        ]);
    }// --Cierra if de respuesta   

    return;// --Termina case login

} elseif ($path === '/api/logout') {//Ruta para cerrar sesion 

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    session_destroy();

    echo json_encode(['ok' => true, 'mensaje' => 'Sesión cerrada']);

    return;// -- Termina case logout
}
