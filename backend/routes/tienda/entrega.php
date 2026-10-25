<?php
// Datos de entrega de la compra en curso (cliente con sesión)
// GET:  los datos para el formulario: los que ya capturó en esta sesión, si no los de su último pedido (T-S4),
//       y si no su nombre y teléfono. "confirmada" dice si ya pasó por este paso (guarda del resumen, P7)
// POST: valida los datos (mensaje por campo) y los guarda en la sesión para el resumen y la confirmación

require_once __DIR__ . '/../../models/entrega.php';

$cliente = requerirCliente();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['entrega'] = Entrega::validar(leerJson());
    responder(['entrega' => $_SESSION['entrega']]);
    return;
}

$vacia = array_fill_keys(['recibe', 'telefono', 'calle', 'numero_exterior', 'numero_interior', 'colonia',
    'codigo_postal', 'ciudad', 'entidad', 'referencias'], '');
$entrega = $_SESSION['entrega']
    ?? Entrega::ultima($cliente['id'])
    ?? ['recibe' => "{$cliente['nombre']} {$cliente['apellidos']}", 'telefono' => $cliente['telefono']] + $vacia;

responder(['entrega' => $entrega, 'confirmada' => isset($_SESSION['entrega'])]);
