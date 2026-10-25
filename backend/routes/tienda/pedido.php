<?php
// GET /api/tienda/pedido?folio=...: un pedido del cliente con sesión (productos, totales y entrega)
// El pedido de otro cliente responde 404, igual que uno que no existe

require_once __DIR__ . '/../../models/pedido.php';

$cliente = requerirCliente();
$folio = is_string($_GET['folio'] ?? null) ? trim($_GET['folio']) : '';

$pedido = $folio === '' ? null : Pedido::leer($cliente['id'], $folio);
if ($pedido === null) {
    throw new ErrorApi('Pedido no encontrado', 404);
}

responder(['pedido' => $pedido]);
