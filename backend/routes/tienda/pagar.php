<?php
// POST /api/tienda/pedido/pagar: intento de pago de un pedido (D6, R14)
// El pago en línea no está disponible: no se contacta a ningún banco ni pasarela, no se reciben datos de tarjeta
// y el pedido sigue pendiente de pago. Cuerpo: {folio, metodo}

require_once __DIR__ . '/../../models/pedido.php';

const METODOS_PAGO = ['tarjeta', 'transferencia', 'efectivo'];

$cliente = requerirCliente();
$data = leerJson();
$folio = is_string($data['folio'] ?? null) ? trim($data['folio']) : '';

$pedido = $folio === '' ? null : Pedido::leer($cliente['id'], $folio);
if ($pedido === null) {
    throw new ErrorApi('Pedido no encontrado', 404);
}
if (!in_array($data['metodo'] ?? null, METODOS_PAGO, true)) {
    throw new ErrorCampos(['metodo' => 'Elige un método de pago']);
}

responder([
    'pagado' => false,
    'mensaje' => "El pago en línea todavía no está disponible. No se hizo ningún cargo y tu pedido {$pedido['folio']} sigue pendiente de pago.",
]);
