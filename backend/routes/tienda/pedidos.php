<?php
// Pedidos del cliente con sesión
// GET:  sus pedidos, del más reciente al más antiguo (Mis pedidos, T-S6)
// POST: confirma la compra. Cuerpo: {productos: [{id, cantidad, precio}], clave}
//       Usa los datos de entrega guardados en la sesión; responde 201 con el folio,
//       o 200 con el mismo folio si esa clave ya se había usado (doble clic, P12)

require_once __DIR__ . '/../../models/pedido.php';

$cliente = requerirCliente();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    responder(['pedidos' => Pedido::listar($cliente['id'])]);
    return;
}

$data = leerJson();
$clave = $data['clave'] ?? null;
if (!is_string($clave) || !preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave)) {
    throw new ErrorValidacion('Falta la clave del envío');
}

// P12: si esta clave ya creó un pedido, se regresa ese (va antes de revisar la entrega, que se borra al confirmar)
$existente = Pedido::folioPorClave($cliente['id'], $clave);
if ($existente !== null) {
    responder(['folio' => $existente, 'creado' => false]);
    return;
}

// P7: no se puede confirmar sin haber pasado por los datos de entrega
if (!isset($_SESSION['entrega'])) {
    throw new ErrorDatos('Faltan los datos de entrega', 409, ['paso' => 'entrega']);
}

[$folio, $creado] = Pedido::crear($cliente['id'], Carrito::leer($data), $_SESSION['entrega'], $clave);
unset($_SESSION['entrega']);

responder(['folio' => $folio, 'creado' => $creado], $creado ? 201 : 200);
