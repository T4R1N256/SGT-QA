<?php
// Rutas para surtidos: compras a proveedores que reabastecen el inventario (RF05, solo admin)

date_default_timezone_set('America/Mazatlan');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/surtir.php';


if ($path === '/api/createRestock') { // Ruta para registrar un surtido

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $data = leerJson();
    $ID_Proveedor = campoEntero($data, 'ID_Proveedor', true, 1);
    $Notas = campoTexto($data, 'Notas', false) ?? '';

    if (!isset($data['productos']) || !is_array($data['productos']) || count($data['productos']) === 0) {
        throw new ErrorValidacion('El surtido no tiene productos');
    }

    // Cantidad y costo de compra por unidad de cada producto
    $lineas = [];
    foreach ($data['productos'] as $item) {
        if (!is_array($item)) {
            throw new ErrorValidacion('Producto no válido');
        }
        $ID_Producto = campoEntero($item, 'ID_Producto', true, 1);
        if (isset($lineas[$ID_Producto])) {
            throw new ErrorValidacion("El producto $ID_Producto está repetido en el surtido");
        }
        $lineas[$ID_Producto] = [
            'Cantidad' => campoEntero($item, 'Cantidad', true, 1),
            'Costo' => campoDinero($item, 'Costo'),
        ];
    }

    require_once __DIR__ . '/../models/proveedor.php';
    require_once __DIR__ . '/../models/producto.php';

    $proveedor = Proveedor::readProveedor($ID_Proveedor);
    if (!$proveedor) {
        throw new ErrorApi('No se encontró el proveedor', 404);
    }
    if ($proveedor['Estado'] !== 'activo') {
        throw new ErrorApi("El proveedor {$proveedor['Nombre_Proveedor']} está dado de baja", 409);
    }

    $connection = new Conexion();

    try {
        $connection->beginTransaction();

        $productos = Producto::bloquearProductos($connection, array_keys($lineas));

        $totalCentavos = 0;
        foreach ($lineas as $ID_Producto => $linea) {
            $producto = $productos[$ID_Producto] ?? null;
            if (!$producto) {
                throw new ErrorApi("El producto $ID_Producto no existe", 404);
            }
            if (!(int) $producto['Activo']) {
                throw new ErrorApi("{$producto['Nombre_Producto']} está dado de baja", 409);
            }
            $totalCentavos += aCentavos($linea['Costo']) * $linea['Cantidad'];
        }

        $ID_Surtir = Surtir::crearSurtido(
            $connection,
            date('Y-m-d'),
            date('H:i:s'),
            $totalCentavos / 100,
            $ID_Proveedor,
            (int) $_SESSION['ID_Usuario'],
            $Notas
        );

        foreach ($lineas as $ID_Producto => $linea) {
            Surtir::addProductoSurtido($connection, $ID_Surtir, $ID_Producto, $linea['Cantidad'], $linea['Costo']);
            Producto::sumarInventario($connection, $ID_Producto, $linea['Cantidad'], $linea['Costo']);
        }

        $connection->commit();

    } catch (Throwable $e) {
        $connection->rollBack();
        throw $e;
    }

    http_response_code(201);
    echo json_encode([
        'ok' => true,
        'mensaje' => 'Surtido registrado correctamente',
        'ID_Surtir' => $ID_Surtir,
        'surtido' => Surtir::readSurtido($ID_Surtir),
    ]);

    //-- Termina case createRestock

} elseif ($path === '/api/getAllRestocks') { // Ruta para listar surtidos por rango de fechas (por defecto, los últimos 30 días)

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    [$Desde, $Hasta] = rangoFechas($_GET);

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'desde' => $Desde,
        'hasta' => $Hasta,
        'surtidos' => Surtir::readAllSurtidos($Desde, $Hasta),
    ]);

    //-- Termina case getAllRestocks

} elseif ($path === '/api/getRestock') { // Ruta para leer un surtido con sus productos

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $surtido = Surtir::readSurtido(campoEntero($_GET, 'id', true, 1));

    if (!$surtido) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el surtido']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'surtido' => $surtido]);
}//-- Termina case getRestock
