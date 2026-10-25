<?php
// POST /api/tienda/salir: cierra la sesión del cliente (aunque no hubiera una)

$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $cookie['path'],
    'httponly' => $cookie['httponly'],
    'samesite' => $cookie['samesite'],
]);
session_destroy();

responder();
