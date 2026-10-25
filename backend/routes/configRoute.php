<?php
// Rutas para la configuración de la tienda (datos del ticket)

require_once __DIR__ . '/../models/configuracion.php';

if ($path === '/api/getConfig') { // Ruta para leer la configuración (pública: el nombre de la tienda se ve en el login)

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $res = Configuracion::readConfiguracion();

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No hay configuración']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'configuracion' => $res]);

    //-- Termina case getConfig

} elseif ($path === '/api/updateConfig') { // Ruta para modificar la configuración (solo admin)

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $data = leerJson();

    // Solo se actualizan los campos que vienen; el nombre de la tienda no puede quedar vacío
    if (campoTexto($data, 'Nombre_Tienda', false, 100) === '') {
        throw new ErrorValidacion('El nombre de la tienda es obligatorio');
    }
    $datos = array_filter([
        'Nombre_Tienda' => campoTexto($data, 'Nombre_Tienda', false, 100),
        'Direccion' => campoTexto($data, 'Direccion', false),
        'Telefono' => campoTexto($data, 'Telefono', false, 20),
        'RFC' => campoTexto($data, 'RFC', false, 13),
        'Mensaje_Ticket' => campoTexto($data, 'Mensaje_Ticket', false),
    ], fn($valor) => $valor !== null);

    if (isset($datos['RFC']) && $datos['RFC'] !== '' && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/u', strtoupper($datos['RFC']))) {
        throw new ErrorValidacion('El RFC no tiene un formato válido');
    }
    if (isset($datos['RFC'])) {
        $datos['RFC'] = strtoupper($datos['RFC']);
    }

    Configuracion::updateConfiguracion($datos);

    http_response_code(200);
    echo json_encode(['ok' => true, 'mensaje' => 'Configuración actualizada', 'configuracion' => Configuracion::readConfiguracion()]);

    //-- Termina case updateConfig
}
