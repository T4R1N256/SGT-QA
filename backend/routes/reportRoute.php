<?php
// Rutas de reportes (RF11, solo admin; regla 5): ventas, surtidos y compras, stock y resumen del periodo

date_default_timezone_set('America/Mazatlan');

require_once __DIR__ . '/../models/reporte.php';


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    return;
}

// Fecha y hora en que se generó el reporte (aparecen en el PDF)
$generado = date('Y-m-d H:i:s');


if ($path === '/api/getSalesReport') { // Ruta para el reporte de ventas del periodo

    [$Desde, $Hasta] = rangoFechas($_GET);

    http_response_code(200);
    echo json_encode(['ok' => true, 'desde' => $Desde, 'hasta' => $Hasta, 'generado' => $generado]
        + Reporte::ventas($Desde, $Hasta));

    //-- Termina case getSalesReport

} elseif ($path === '/api/getRestockReport') { // Ruta para el reporte de surtidos y compras del periodo

    [$Desde, $Hasta] = rangoFechas($_GET);

    http_response_code(200);
    echo json_encode(['ok' => true, 'desde' => $Desde, 'hasta' => $Hasta, 'generado' => $generado]
        + Reporte::surtidos($Desde, $Hasta));

    //-- Termina case getRestockReport

} elseif ($path === '/api/getStockReport') { // Ruta para el reporte de stock en este momento

    http_response_code(200);
    echo json_encode(['ok' => true, 'generado' => $generado] + Reporte::stock());

    //-- Termina case getStockReport

} elseif ($path === '/api/getSummaryReport') { // Ruta para el resumen del periodo: ventas contra compras

    [$Desde, $Hasta] = rangoFechas($_GET);

    http_response_code(200);
    echo json_encode(['ok' => true, 'desde' => $Desde, 'hasta' => $Hasta, 'generado' => $generado]
        + Reporte::resumen($Desde, $Hasta));
}//-- Termina case getSummaryReport
