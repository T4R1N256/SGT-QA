<?php
// POST /api/tienda/ingresar: inicia la sesión del cliente con su correo y contraseña
// Un correo sin cuenta y una contraseña incorrecta responden igual, para no revelar qué correos están registrados

require_once __DIR__ . '/../../models/cliente.php';

$data = leerJson();
$correo = is_string($data['correo'] ?? null) ? trim($data['correo']) : '';
$password = is_string($data['password'] ?? null) ? $data['password'] : '';

$errores = array_filter([
    'correo' => $correo === '' ? 'Escribe tu correo' : null,
    'password' => $password === '' ? 'Escribe tu contraseña' : null,
]);
if ($errores) {
    throw new ErrorCampos($errores);
}

$cliente = Cliente::ingresar($correo, $password);
if ($cliente === null) {
    throw new ErrorApi('Correo o contraseña incorrectos', 401);
}

iniciarSesionCliente($cliente['id']);
responder(['cliente' => $cliente]);
