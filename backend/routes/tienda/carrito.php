<?php
// POST /api/tienda/carrito/revisar: revisa el carrito del navegador con los datos vigentes (pública)
// Cuerpo: {productos: [{id, cantidad, precio}]}. El precio es el que vio el cliente y solo sirve para avisar si cambió

require_once __DIR__ . '/../../models/carrito.php';

responder(Carrito::revisar(Carrito::leer(leerJson())));
