<?php

//Rutas para caja
// La caja activa se obtiene de la base de datos (no de la sesión), así sigue disponible
// aunque la sesión PHP se haya reiniciado con la caja abierta

date_default_timezone_set('America/Mazatlan');

require_once __DIR__ . '/../models/caja.php';


if ($path === '/api/openCashRegister') { // Ruta para abrir caja

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $data = leerJson();
    // Se permite abrir con $0
    $Monto_Inicial = campoDinero($data, 'Monto_Inicial');
    // Quien abre es el usuario de la sesión, no lo que mande el cliente
    $ID_Usuario = (int) $_SESSION['ID_Usuario'];

    if (Caja::cajaAbierta()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'mensaje' => 'Ya hay una caja abierta']);
        return;
    }

    $res = Caja::openCaja(date("Y-m-d"), date("H:i:s"), $Monto_Inicial, "abierta", $ID_Usuario);

    http_response_code(201);
    echo json_encode([
        'ok' => true,
        'mensaje' => 'Caja abierta correctamente',
        'caja' => ['ID_Caja' => (int) $res, 'Monto_Inicial' => $Monto_Inicial],
    ]);

    //-- Fin case openCashRegister

} else if ($path === '/api/closeCashRegister') { // Ruta para cerrar caja

    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $data = leerJson();
    // Efectivo contado al cerrar; puede ser $0
    $Monto_Final = campoDinero($data, 'Monto_Final');

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../models/venta.php';

    $caja = Caja::requerirAbierta();
    $ID_Caja = (int) $caja['ID_Caja'];

    $connection = new Conexion();

    try {
        $connection->beginTransaction();

        // Una venta que quedó en proceso al cerrar nunca se cobró
        Venta::cancelarEnProceso($connection, $ID_Caja);

        // Cuadre: solo cuenta el efectivo. Se compara en centavos para no depender del redondeo
        $totales = Caja::totalesVentas($ID_Caja, $connection);
        $esperadoCentavos = aCentavos($caja['Monto_Inicial']) + aCentavos($totales['efectivo']);
        $Estado_Final = aCentavos($Monto_Final) === $esperadoCentavos ? 'cuadrada' : 'descuadrada';

        Caja::closeCaja(
            $connection,
            $ID_Caja,
            $Monto_Final,
            $esperadoCentavos / 100,
            $Estado_Final,
            date("H:i:s"),
            (int) $_SESSION['ID_Usuario']
        );

        $connection->commit();
        unset($_SESSION['ID_Venta']);

    } catch (Throwable $e) {
        $connection->rollBack();
        throw $e;
    }

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'mensaje' => 'Caja cerrada correctamente',
        'corte' => [
            'Monto_Inicial' => (float) $caja['Monto_Inicial'],
            'Efectivo_Esperado' => $esperadoCentavos / 100,
            'Monto_Final' => $Monto_Final,
            'Diferencia' => (aCentavos($Monto_Final) - $esperadoCentavos) / 100,
            'Estado_Final' => $Estado_Final,
            'totales' => $totales,
        ],
    ]);

    //-- Fin case closeCashRegister

} else if ($path === '/api/getAllVentas') { //Ruta para obtener las ventas finalizadas de la caja abierta

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    require_once __DIR__ . '/../models/venta.php';

    $caja = Caja::requerirAbierta();

    http_response_code(200);
    echo json_encode(['ok' => true, 'ventas' => Venta::readAllVentas($caja['ID_Caja'])]);

    //-- Fin case getAllVentas

} else if ($path === '/api/getMontoFinal') { // Ruta para obtener los totales de la caja abierta y el efectivo esperado

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $caja = Caja::requerirAbierta();
    $totales = Caja::totalesVentas($caja['ID_Caja']);

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'Monto_Inicial' => (float) $caja['Monto_Inicial'],
        'Efectivo_Esperado' => (aCentavos($caja['Monto_Inicial']) + aCentavos($totales['efectivo'])) / 100,
        'totales' => $totales,
    ]);

    //-- Fin case getMontoFinal

} else if ($path === '/api/getCashRegister') { // Ruta para obtener el último corte de caja

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $res = Caja::readCaja();

    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No hay cortes registrados']);
        return;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'caja' => $res]);
}//-- Fin case getCashRegister
