<?php
// GET /api/tienda/producto?id=N: detalle de un producto (pública)
// Un producto dado de baja o de una categoría desactivada responde 404, igual que uno que no existe

require_once __DIR__ . '/../../models/catalogo.php';

$producto = Catalogo::producto(parametroEntero('id', 'El producto', true));

if ($producto === null) {
    throw new ErrorApi('Producto no encontrado', 404);
}

responder(['producto' => $producto]);
