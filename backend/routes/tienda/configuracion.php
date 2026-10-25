<?php
// GET /api/tienda/config: nombre de la tienda y tasa de IVA (pública)
// Los precios ya incluyen el IVA; la tienda solo lo desglosa

require_once __DIR__ . '/../../models/configuracion.php';

$configuracion = Configuracion::readConfiguracion();

responder([
    'tienda' => [
        'nombre' => $configuracion ? $configuracion['Nombre_Tienda'] : 'Tienda',
        'tasa_iva' => Configuracion::tasaIVA(),
    ],
]);
