<?php
// Router de la tienda en línea: /api/tienda/<ruta>
// Cada ruta se atiende en su propio archivo. Las respuestas siguen el formato del API: {ok, ...} o {ok: false, mensaje}

date_default_timezone_set('America/Mazatlan');

// Ruta => [método HTTP, archivo que la atiende]
const RUTAS_TIENDA = [
    'config' => ['GET', 'configuracion.php'],
];

// Responde {ok: true, ...datos} con el código HTTP indicado
function responder(array $datos = [], int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => true] + $datos);
}

$ruta = substr($path, strlen('/api/tienda/'));

if (!isset(RUTAS_TIENDA[$ruta])) {
    throw new ErrorApi('Ruta no encontrada', 404);
}

[$metodo, $archivo] = RUTAS_TIENDA[$ruta];
if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
    throw new ErrorApi('Método no permitido', 405);
}

require __DIR__ . '/' . $archivo;
