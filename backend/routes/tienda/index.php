<?php
/**
 * Router de la tienda en línea: /api/tienda/<ruta>.
 * Cada ruta se atiende en su propio archivo. Las respuestas siguen el formato del API:
 * {ok, ...} o {ok: false, mensaje}
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 */

date_default_timezone_set('America/Mazatlan');

// Ruta => [método HTTP (o lista de métodos), archivo que la atiende]
const RUTAS_TIENDA = [
    'config' => ['GET', 'configuracion.php'],
    'categorias' => ['GET', 'categorias.php'],
    'destacados' => ['GET', 'destacados.php'],
    'productos' => ['GET', 'productos.php'],
    'producto' => ['GET', 'producto.php'],
    'carrito/revisar' => ['POST', 'carrito.php'],
    'registro' => ['POST', 'registro.php'],
    'ingresar' => ['POST', 'ingresar.php'],
    'salir' => ['POST', 'salir.php'],
    'cuenta' => ['GET', 'cuenta.php'],
    'entrega' => [['GET', 'POST'], 'entrega.php'],
    'pedidos' => [['GET', 'POST'], 'pedidos.php'],
    'pedido' => ['GET', 'pedido.php'],
    'pedido/pagar' => ['POST', 'pagar.php'],
];

/**
 * Responde {ok: true, ...datos} con el código HTTP indicado.
 * Los acentos van tal cual (UTF-8), sin escapar como \u00e1: más ligero y legible en el navegador.
 *
 * @param array $datos  Datos que se agregan a la respuesta
 * @param int   $codigo Código HTTP
 * @return void
 */
function responder(array $datos = [], int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => true] + $datos, JSON_UNESCAPED_UNICODE);
}

/**
 * Cliente con la sesión iniciada en la tienda.
 *
 * @return array|null Los datos del cliente, o null sin sesión
 * @see Cliente::leer()
 */
function clienteActual(): ?array
{
    if (!isset($_SESSION['ID_Cliente'])) {
        return null;
    }
    require_once __DIR__ . '/../../models/cliente.php';
    return Cliente::leer((int) $_SESSION['ID_Cliente']);
}

/**
 * Cliente con la sesión iniciada; lo usan las rutas de compra y pedidos.
 *
 * @return array Los datos del cliente
 * @throws ErrorApi 401 "Inicia sesión para continuar" si no hay sesión
 * @see clienteActual()
 */
function requerirCliente(): array
{
    return clienteActual() ?? throw new ErrorApi('Inicia sesión para continuar', 401);
}

/**
 * Inicia la sesión del cliente con un ID de sesión nuevo (evita que alguien fije la sesión antes
 * de ingresar) y sin datos de otra sesión (por ejemplo, la dirección de entrega de otro cliente).
 *
 * @param int $ID_Cliente Cliente que ingresa
 * @return void
 */
function iniciarSesionCliente(int $ID_Cliente): void
{
    session_regenerate_id(true);
    $_SESSION = ['ID_Cliente' => $ID_Cliente];
}

/**
 * Lee un parámetro entero (mayor o igual a 1) de la URL: ?nombre=valor.
 *
 * @param string $nombre    Nombre del parámetro en la URL
 * @param string $etiqueta  Cómo se llama en los mensajes de error
 * @param bool   $requerido Si es obligatorio
 * @return int|null El número, o null si no viene y no es requerido
 * @throws ErrorValidacion Si no es un entero mayor o igual a 1, o falta siendo requerido
 * @see campoEntero()
 */
function parametroEntero(string $nombre, string $etiqueta, bool $requerido = false): ?int
{
    return campoEntero([$etiqueta => $_GET[$nombre] ?? null], $etiqueta, $requerido, 1);
}

$ruta = substr($path, strlen('/api/tienda/'));

if (!isset(RUTAS_TIENDA[$ruta])) {
    throw new ErrorApi('Ruta no encontrada', 404);
}

[$metodos, $archivo] = RUTAS_TIENDA[$ruta];
if (!in_array($_SERVER['REQUEST_METHOD'], (array) $metodos, true)) {
    throw new ErrorApi('Método no permitido', 405);
}

require __DIR__ . '/' . $archivo;
