<?php
// Router de la tienda en línea: /api/tienda/<ruta>
// Cada ruta se atiende en su propio archivo. Las respuestas siguen el formato del API: {ok, ...} o {ok: false, mensaje}

date_default_timezone_set('America/Mazatlan');

// Ruta => [método HTTP, archivo que la atiende]
const RUTAS_TIENDA = [
    'config' => ['GET', 'configuracion.php'],
    'categorias' => ['GET', 'categorias.php'],
    'destacados' => ['GET', 'destacados.php'],
    'productos' => ['GET', 'productos.php'],
    'producto' => ['GET', 'producto.php'],
];

// Responde {ok: true, ...datos} con el código HTTP indicado
// Los acentos van tal cual (UTF-8), sin escapar como \u00e1: más ligero y legible en el navegador
function responder(array $datos = [], int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => true] + $datos, JSON_UNESCAPED_UNICODE);
}

// Parámetro entero de la URL (?nombre=valor). $etiqueta es como se llama en los mensajes de error
function parametroEntero(string $nombre, string $etiqueta, bool $requerido = false): ?int
{
    return campoEntero([$etiqueta => $_GET[$nombre] ?? null], $etiqueta, $requerido, 1);
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
