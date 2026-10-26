<?php
// Rutas para proveedor

if ($path === '/api/createSupplier') { // Ruta para crear proveedor
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }


    $data = leerJson();
    $Nombre_Proveedor = campoTexto($data, 'Nombre_Proveedor');
    $Telefono = campoTelefono($data) ?? '';

    require_once __DIR__ . ('/../models/proveedor.php');

    Proveedor::validarNombreLibre($Nombre_Proveedor);

    $res = Proveedor::createProveedor($Nombre_Proveedor, $Telefono);

    if ($res) {
        http_response_code(201);
        echo json_encode(['ok' => true, 'mensaje' => 'Proveedor creado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'El proveedor no se pudo crear']);
    }

    //-- Termina case createSupplier

} elseif ($path === '/api/getAllSuppliers') { // Ruta para leer todos los proveedores

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    require_once __DIR__ . '/../models/proveedor.php';

    // ?todos=1: también los inactivos (pantalla de Proveedores); sin él, solo los activos (Inventario y Surtir)
    $res = Proveedor::readAllProveedores(($_GET['todos'] ?? '') === '1');

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No hay proveedores']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'proveedores' => $res]);

    //-- Termina case readAllSuppliers

} elseif ($path === '/api/getSupplier') {// Ruta para leer un proveedor

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $ID_Proveedor = campoEntero($_GET, 'id', true, 1);

    require_once __DIR__ . '/../models/proveedor.php';


    $res = Proveedor::readProveedor($ID_Proveedor);

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el proveedor']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'proveedor' => $res]);


    //-- Termina case getSupplier

} else if ($path === '/api/modifySupplier') { // Ruta para modificar proveedor

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }


    $data = leerJson();
    $ID_Proveedor = campoEntero($data, 'ID_Proveedor', true, 1);
    $Nombre_Proveedor = campoTexto($data, 'Nombre_Proveedor', false) ?: null;
    $Telefono = campoTelefono($data);
    $Estado = campoOpcion($data, 'Estado', ['activo', 'inactivo'], false);

    require_once __DIR__ . '/../models/proveedor.php';

    $infoActual = Proveedor::readProveedor($ID_Proveedor);

    if (!$infoActual) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el proveedor']);
        return;
    }

    // El nombre solo se revisa si cambia: un repetido de antes no impide cambiar el teléfono o el estado
    if ($Nombre_Proveedor !== null && $Nombre_Proveedor !== $infoActual['Nombre_Proveedor']) {
        Proveedor::validarNombreLibre($Nombre_Proveedor, $ID_Proveedor);
    }

    $Nombre_Proveedor = $Nombre_Proveedor ?? $infoActual['Nombre_Proveedor'];
    $Telefono = $Telefono ?? $infoActual['Telefono'];

    $res = Proveedor::updateProveedor($ID_Proveedor, $Nombre_Proveedor, $Telefono);
    if ($res === 1 && $Estado !== null) {
        $res = Proveedor::cambiarEstado($ID_Proveedor, $Estado);
    }

    if ($res !== 1) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el proveedor']);
        return;
    } else {
        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Proveedor actualizado correctamente']);
    }

    //-- Termina ruta modifySupplier

} else if ($path === '/api/deleteSupplier') { // Ruta delete (cambiar estado) a proveedor

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }


    $data = leerJson();
    $ID_Proveedor = campoEntero($data, 'ID_Proveedor', true, 1);

    require_once __DIR__ . '/../models/proveedor.php';

    $res = Proveedor::cambiarEstado($ID_Proveedor, 'inactivo');

    if ($res !== 1) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el proveedor']);
        return;
    } else {
        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Proveedor desactivado correctamente']);
    }

    //-- Termina ruta deleteSupplier
}
