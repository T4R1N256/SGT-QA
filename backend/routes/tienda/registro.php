<?php
// POST /api/tienda/registro: crea la cuenta del cliente e inicia su sesión
// Cuerpo: {nombre, apellidos, correo, telefono, password}. Los errores vienen por campo (P8–P10)

require_once __DIR__ . '/../../models/cliente.php';

$ID_Cliente = Cliente::registrar(Cliente::validar(leerJson()));
iniciarSesionCliente($ID_Cliente);

responder(['cliente' => Cliente::leer($ID_Cliente)], 201);
