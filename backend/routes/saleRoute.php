<?php

// Rutas para venta
// La caja activa se obtiene de la base de datos (no de la sesión), así una venta funciona
// aunque la sesión PHP se haya reiniciado con la caja abierta
date_default_timezone_set('America/Mazatlan');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/caja.php';
require_once __DIR__ . '/../models/venta.php';

const FORMAS_PAGO = ['efectivo', 'tarjeta', 'transferencia'];


if ($path === '/api/openSale') { // Ruta para abrir venta

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $caja = Caja::requerirAbierta();

    $connection = new Conexion();
    // Solo hay un punto de venta: una venta anterior que quedó en proceso se abandonó
    Venta::cancelarEnProceso($connection, $caja['ID_Caja']);
    $res = Venta::openVenta($connection, $caja['ID_Caja']);
    $connection = NULL;

    $_SESSION['ID_Venta'] = (int) $res;
    http_response_code(201);
    echo json_encode(['ok' => true, 'mensaje' => 'Venta iniciada', 'ID_Venta' => (int) $res]);

    //-- Fin case openSale

} elseif ($path === '/api/completeSale') { // Ruta para completar venta

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $data = leerJson();
    $Forma_Pago = campoOpcion($data, 'Forma_Pago', FORMAS_PAGO);
    // Total que el cajero le cobró al cliente; debe coincidir con los precios de la base de datos
    $Monto = campoDinero($data, 'Monto');

    if (!isset($data['productos']) || !is_array($data['productos']) || count($data['productos']) === 0) {
        throw new ErrorValidacion('La venta no tiene productos');
    }

    // Cantidad por producto (si el mismo producto viene dos veces, se suman)
    $cantidades = [];
    foreach ($data['productos'] as $item) {
        if (!is_array($item)) {
            throw new ErrorValidacion('Producto no válido');
        }
        $ID_Producto = campoEntero($item, 'ID_Producto', true, 1);
        $cantidades[$ID_Producto] = ($cantidades[$ID_Producto] ?? 0) + campoEntero($item, 'Cantidad', true, 1);
    }

    require_once __DIR__ . '/../models/producto.php';

    $caja = Caja::requerirAbierta();
    $connection = new Conexion();

    try {
        $connection->beginTransaction();

        // Se bloquean los productos para que nadie cambie su existencia hasta terminar
        $productos = Producto::bloquearProductos($connection, array_keys($cantidades));

        $totalCentavos = 0;
        foreach ($cantidades as $ID_Producto => $Cantidad) {
            $producto = $productos[$ID_Producto] ?? null;
            if (!$producto) {
                throw new ErrorApi("El producto $ID_Producto no existe", 404);
            }
            // Reglas 1 y 6: no se vende más de lo que hay
            if ((int) $producto['Cantidad'] < $Cantidad) {
                throw new ErrorApi(
                    "No hay suficiente {$producto['Nombre_Producto']}: quedan {$producto['Cantidad']}",
                    409
                );
            }
            $totalCentavos += aCentavos($producto['Precio']) * $Cantidad;
        }

        if ($totalCentavos !== aCentavos($Monto)) {
            throw new ErrorApi('El total no coincide con los precios actuales. Revisa la venta e intenta de nuevo.', 409);
        }

        // La venta abierta de esta sesión; si se perdió la sesión, se crea una nueva
        $ID_Venta = (int) ($_SESSION['ID_Venta'] ?? 0);
        if (!$ID_Venta || !Venta::ventaEnProceso($connection, $ID_Venta, $caja['ID_Caja'])) {
            $ID_Venta = (int) Venta::openVenta($connection, $caja['ID_Caja']);
        }

        foreach ($cantidades as $ID_Producto => $Cantidad) {
            Venta::addProductoVenta($connection, $ID_Venta, $ID_Producto, $Cantidad, $productos[$ID_Producto]['Precio']);
            Producto::descontarInventario($connection, $ID_Producto, $Cantidad);
        }

        $Monto_Total = $totalCentavos / 100;
        Venta::completeVenta($connection, $ID_Venta, date('Y-m-d'), date('H:i:s'), $Monto_Total, $Forma_Pago);

        $connection->commit();
        unset($_SESSION['ID_Venta']);

    } catch (Throwable $e) {
        $connection->rollBack();
        throw $e;
    }

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'mensaje' => 'Venta completada correctamente',
        'ID_Venta' => $ID_Venta,
        'Monto' => $Monto_Total,
    ]);

    //-- Fin case completeSale

} elseif ($path === '/api/cancelSale') { // Ruta para cancelar venta

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $ID_Venta = (int) ($_SESSION['ID_Venta'] ?? 0);

    if (!$ID_Venta) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'No hay venta en proceso']);
        return;
    }

    $res = Venta::cancelVenta($ID_Venta);
    unset($_SESSION['ID_Venta']);

    if ($res === 1) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Venta cancelada']);
    } else {
        http_response_code(409);
        echo json_encode(['ok' => false, 'mensaje' => 'La venta ya no está en proceso']);
    }

    //-- Fin case cancelSale
}
