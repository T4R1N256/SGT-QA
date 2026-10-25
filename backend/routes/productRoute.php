<?php
//Rutas para producto 


if ($path === '/api/createProduct') { // Ruta que crea productos

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    // Se permite registrar un producto con 0 existencias (queda "Agotado"); no se permiten negativos (regla 6)
    $data = leerJson();
    $Nombre_Producto = campoTexto($data, 'Nombre_Producto');
    $Precio = campoDinero($data, 'Precio', true, 0.01);
    $Cantidad = campoEntero($data, 'Cantidad');
    $Unidad_Medida = campoTexto($data, 'Unidad_Medida', true, 20);
    $Cantidad_Minima = campoEntero($data, 'Cantidad_Minima');
    $ID_Categoria = campoEntero($data, 'ID_Categoria', true, 1);
    $ID_Proveedor = campoEntero($data, 'ID_Proveedor', true, 1);
    // Descripción para la tienda en línea (opcional)
    $Descripcion = campoTexto($data, 'Descripcion', false, 500) ?? '';

    require_once __DIR__ . '/../models/producto.php';

    $Estado = Producto::calcularEstado($Cantidad, $Cantidad_Minima);

    $res = Producto::crearProducto(
        $Nombre_Producto,
        $Precio,
        $Cantidad,
        $Unidad_Medida,
        $Estado,
        $Cantidad_Minima,
        $ID_Categoria,
        $ID_Proveedor,
        $Descripcion
    );

    if ($res) {
        http_response_code(201);
        echo json_encode(['ok' => true, 'mensaje' => 'Producto creado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'El producto no se pudo crear']);
    }

    //-- Termina case createProduct

} elseif ($path === '/api/getAllProducts') { // Ruta que lee todos los productos 

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }

    require_once __DIR__ . '/../models/producto.php';

    $res = Producto::readAllProductos();

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No hay productos']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'productos' => $res]);


    //-- Termina case getAllProducts

} elseif ($path === '/api/getProduct') { // Ruta que lee un producto 

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }

    $ID_Producto = campoEntero($_GET, 'id', true, 1);

    require_once __DIR__ . '/../models/producto.php';


    $res = Producto::readProducto($ID_Producto);

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el producto']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'producto' => $res]);


    //-- Termina case getProduct

} elseif ($path === '/api/modifyProduct') {// Ruta para modificar un producto 
    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }


    $data = leerJson();
    $ID_Producto = campoEntero($data, 'ID_Producto', true, 1);
    $Nombre_Producto = campoTexto($data, 'Nombre_Producto', false) ?: null;
    $Precio = campoDinero($data, 'Precio', false, 0.01);
    $Cantidad = campoEntero($data, 'Cantidad', false);
    $Unidad_Medida = campoTexto($data, 'Unidad_Medida', false, 20) ?: null;
    $Cantidad_Minima = campoEntero($data, 'Cantidad_Minima', false);
    $ID_Categoria = campoEntero($data, 'ID_Categoria', false, 1);
    $ID_Proveedor = campoEntero($data, 'ID_Proveedor', false, 1);
    // Si viene vacía se borra; si no viene se conserva
    $Descripcion = campoTexto($data, 'Descripcion', false, 500);

    require_once __DIR__ . '/../models/producto.php';

    $infoActual = Producto::readProducto($ID_Producto);

    if (!$infoActual) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el producto']);
        return;
    }

    $Nombre_Producto = $Nombre_Producto ?? $infoActual['Nombre_Producto'];
    $Precio = $Precio ?? $infoActual['Precio'];
    $Cantidad = $Cantidad ?? $infoActual['Cantidad'];
    $Unidad_Medida = $Unidad_Medida ?? $infoActual['Unidad_Medida'];
    $Cantidad_Minima = $Cantidad_Minima ?? $infoActual['Cantidad_Minima'];
    $ID_Categoria = $ID_Categoria ?? $infoActual['ID_Categoria'];
    $ID_Proveedor = $ID_Proveedor ?? $infoActual['ID_Proveedor'];
    $Descripcion = $Descripcion ?? $infoActual['Descripcion'];

    $Estado = Producto::calcularEstado((int) $Cantidad, (int) $Cantidad_Minima);

    $res = Producto::updateProducto(
        $ID_Producto,
        $Nombre_Producto,
        $Precio,
        $Cantidad,
        $Unidad_Medida,
        $Estado,
        $Cantidad_Minima,
        $ID_Categoria,
        $ID_Proveedor,
        $Descripcion
    );

    if ($res !== 1) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el producto']);
        return;
    } else {
        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Producto actualizado correctamente']);
    }



    //-- Termina ruta modifyProduct

} elseif ($path === '/api/deleteProduct') { // Ruta para dar de baja un producto (baja lógica, RF06)

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }

    $data = leerJson();
    $ID_Producto = campoEntero($data, 'ID_Producto', true, 1);

    require_once __DIR__ . '/../models/producto.php';

    if (Producto::desactivarProducto($ID_Producto) !== 1) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el producto']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'mensaje' => 'Producto eliminado correctamente']);

}//-- Termina ruta deleteProduct 