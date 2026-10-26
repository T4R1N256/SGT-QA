<?php
// Rutas para categoria

if ($path === '/api/createCategory') { // Ruta para crear categoria
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }


    $data = leerJson();
    $Nombre_Categoria = campoTexto($data, 'Nombre_Categoria', true, 50);

    require_once __DIR__ . ('/../models/categoria.php');

    Categoria::validarNombreLibre($Nombre_Categoria);

    $res = Categoria::createCategoria($Nombre_Categoria);

    if ($res) {
        http_response_code(201);
        echo json_encode(['ok' => true, 'mensaje' => 'Categoría creada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'La categoría no se pudo crear']);
    }

    //-- Termina case createCategory

} elseif ($path === '/api/getAllCategories') { // Ruta para leer todas las categorias

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    require_once __DIR__ . '/../models/categoria.php';

    // ?todas=1: también las inactivas (pantalla de Categorías); sin él, solo las activas (Inventario)
    $res = Categoria::readAllCategoria(($_GET['todas'] ?? '') === '1');

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No hay categorias']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'categorias' => $res]);

    //-- Termina case readAllCategories

} elseif ($path === '/api/getCategory') {// Ruta para leer una categoria

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $ID_Categoria = campoEntero($_GET, 'id', true, 1);

    require_once __DIR__ . '/../models/categoria.php';


    $res = Categoria::readCategoria($ID_Categoria);

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró la categoria']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'categoria' => $res]);


    //-- Termina case getCategoria

} else if ($path === '/api/modifyCategory') { // Ruta para modificar categoria

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }


    $data = leerJson();
    $ID_Categoria = campoEntero($data, 'ID_Categoria', true, 1);
    $Nombre_Categoria = campoTexto($data, 'Nombre_Categoria', false, 50) ?: null;
    $Estado = campoOpcion($data, 'Estado', ['activo', 'inactivo'], false);

    require_once __DIR__ . '/../models/categoria.php';

    $infoActual = Categoria::readCategoria($ID_Categoria);

    if (!$infoActual) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró la categoria']);
        return;
    }

    // El nombre solo se revisa si cambia: un repetido de antes no impide cambiar el estado
    if ($Nombre_Categoria !== null && $Nombre_Categoria !== $infoActual['Nombre_Categoria']) {
        Categoria::validarNombreLibre($Nombre_Categoria, $ID_Categoria);
    }
    if ($Estado === 'inactivo' && $infoActual['Estado'] !== 'inactivo') {
        Categoria::validarDesactivar($ID_Categoria, $infoActual['Nombre_Categoria'], ($data['Confirmar'] ?? false) === true);
    }

    $Nombre_Categoria = $Nombre_Categoria ?? $infoActual['Nombre_Categoria'];

    $res = Categoria::updateCategoria($ID_Categoria, $Nombre_Categoria);
    if ($res === 1 && $Estado !== null) {
        $res = Categoria::cambiarEstado($ID_Categoria, $Estado);
    }

    if ($res !== 1) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró la categoria']);
        return;
    } else {
        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Categoria actualizada correctamente']);
    }

    //-- Termina ruta modifyCategoria

} else if ($path === '/api/deleteCategory') { // Ruta delete (cambiar estado) a categoria

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        return;
    }


    $data = leerJson();
    $ID_Categoria = campoEntero($data, 'ID_Categoria', true, 1);

    require_once __DIR__ . '/../models/categoria.php';

    $infoActual = Categoria::readCategoria($ID_Categoria);

    if (!$infoActual) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró la categoria']);
        return;
    }

    // Con productos activos se necesita {"Confirmar": true}
    if ($infoActual['Estado'] !== 'inactivo') {
        Categoria::validarDesactivar($ID_Categoria, $infoActual['Nombre_Categoria'], ($data['Confirmar'] ?? false) === true);
    }

    $res = Categoria::cambiarEstado($ID_Categoria, 'inactivo');

    if ($res !== 1) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró la categoria']);
        return;
    } else {
        http_response_code(200);
        echo json_encode(['ok' => true, 'mensaje' => 'Categoría desactivada correctamente']);
    }

    //-- Termina ruta deleteCategory
}
