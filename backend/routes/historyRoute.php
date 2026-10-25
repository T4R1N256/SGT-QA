<?php
// Rutas del historial: ventas pasadas y cortes de caja (solo admin)

date_default_timezone_set('America/Mazatlan');

require_once __DIR__ . '/../models/venta.php';
require_once __DIR__ . '/../models/caja.php';

const VENTAS_POR_PAGINA = 25;


// Funcion para leer el rango de fechas de los filtros; por defecto, los últimos 30 días
function rangoFechas(array $parametros): array
{
    $Hasta = campoFecha($parametros, 'hasta', false) ?? date('Y-m-d');
    $Desde = campoFecha($parametros, 'desde', false) ?? date('Y-m-d', strtotime("$Hasta -30 days"));

    if ($Desde > $Hasta) {
        throw new ErrorValidacion('La fecha inicial no puede ser posterior a la final');
    }
    return [$Desde, $Hasta];
}


if ($path === '/api/getSalesHistory') { // Ruta para el historial de ventas con filtros y paginación

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    [$Desde, $Hasta] = rangoFechas($_GET);
    $estado = campoOpcion($_GET, 'estado', ['finalizada', 'cancelada', 'todas'], false) ?? 'finalizada';
    $pagina = campoEntero($_GET, 'pagina', false, 1) ?? 1;

    $filtros = [
        'desde' => $Desde,
        'hasta' => $Hasta,
        // Las ventas "enproceso" no son historial: todavía no se cobran o se abandonaron
        'estados' => $estado === 'todas' ? ['finalizada', 'cancelada'] : [$estado],
        'forma_pago' => campoOpcion($_GET, 'forma_pago', ['efectivo', 'tarjeta', 'transferencia'], false),
    ];

    $resumen = Venta::resumenHistorial($filtros);

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'desde' => $Desde,
        'hasta' => $Hasta,
        'pagina' => $pagina,
        'paginas' => max(1, (int) ceil($resumen['ventas'] / VENTAS_POR_PAGINA)),
        'resumen' => $resumen,
        'ventas' => Venta::readHistorial($filtros, VENTAS_POR_PAGINA, ($pagina - 1) * VENTAS_POR_PAGINA),
    ]);

    //-- Termina case getSalesHistory

} elseif ($path === '/api/getCashRegisters') { // Ruta para el historial de cortes de caja

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
        'cajas' => Caja::readHistorial($Desde, $Hasta),
    ]);

    //-- Termina case getCashRegisters

} elseif ($path === '/api/getCashRegisterDetail') { // Ruta para el detalle de un corte: totales y ventas

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
        return;
    }

    $ID_Caja = campoEntero($_GET, 'id', true, 1);
    $caja = Caja::readCajaHistorial($ID_Caja);

    if (!$caja) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró la caja']);
        return;
    }

    $totales = Caja::totalesVentas($ID_Caja);
    // Con la caja abierta el efectivo esperado todavía no está guardado: se calcula en este momento
    $esperado = $caja['Monto_Final_Sistema'] ?? (aCentavos($caja['Monto_Inicial']) + aCentavos($totales['efectivo'])) / 100;

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'caja' => $caja,
        'Efectivo_Esperado' => (float) $esperado,
        'totales' => $totales,
        'canceladas' => Venta::contarCanceladas($ID_Caja),
        'ventas' => Venta::readAllVentas($ID_Caja),
    ]);
}//-- Termina case getCashRegisterDetail
