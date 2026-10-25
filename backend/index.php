<?php
/*
 * Punto de entrada para la API
 * Autorizacion mediante sesiones de PHP
 * El estado se resetea al cerrar el navegador
 */
require_once __DIR__ . '/config/validar.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// La tienda en línea (/api/tienda/...) usa su propia cookie de sesión:
// un cliente nunca comparte sesión con un empleado de SGT
$esTienda = str_starts_with($path, '/api/tienda/');

ini_set('session.gc_maxlifetime', 86400); // 24 hours
if ($esTienda) {
    session_name('SGT_TIENDA');
    session_set_cookie_params(['lifetime' => 86400, 'httponly' => true, 'samesite' => 'Lax']);
} else {
    session_set_cookie_params(['lifetime' => 86400]);
}
session_start();


//Headers 
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
// Never cache GET requests
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');



$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Orígenes que pueden hacer peticiones con credenciales:
// Vite en desarrollo y el servidor local con el que Electron sirve la app compilada (APP_ORIGIN, puerto variable)
$allowed = array_filter(['http://localhost:5173', 'http://localhost:8000', getenv('APP_ORIGIN') ?: null]);

// Mismo origen: la página la sirve el mismo host que el API (la tienda detrás de nginx o del proxy de Vite).
// El navegador manda Origin incluso así (por ejemplo, en un POST), pero no es una petición CORS
$host = $_SERVER['HTTP_HOST'] ?? '';
$mismoOrigen = $host !== '' && in_array($origin, ["http://$host", "https://$host"], true);

// Las peticiones sin Origin (terminal, pruebas) o del mismo origen no son CORS y no necesitan cabecera
if ($origin !== '' && !$mismoOrigen) {
    if (!in_array($origin, $allowed, true)) {
        http_response_code(403);
        exit;
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


function esAutorizado(): bool
{
    return isset($_SESSION['ID_Usuario']);
}

function esAdmin(): bool
{
    return isset($_SESSION['Puesto']) && $_SESSION['Puesto'] === 'admin';
}

function requerirAutorizacion(): void
{
    if (!esAutorizado()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
        exit;
    }
}

function requerirAdmin(): void
{
    requerirAutorizacion();
    if (!esAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']);
        exit;
    }
}


// Rutas
// Un ErrorApi responde con su código HTTP y su mensaje (por ejemplo, datos inválidos: 400); cualquier otra excepción responde 500
try {
switch (true) {

    case $path === '/api/ping': // Ruta de prueba para verificar que PHP responde
        // El pid permite a Electron reconocer y cerrar un PHP que quedó abierto de una ejecución anterior
        echo json_encode(['ok' => true, 'mensaje' => 'PHP respondiendo', 'pid' => getmypid()]);
        break;

    case $path === '/api/logout':
    case $path === '/api/login':
        require_once __DIR__ . '/routes/authRoute.php';
        break;


    case $path === '/api/getUserInfo':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/usuarioRoute.php';
        break;

    case $path === '/api/getUsers':
        requerirAdmin();
        require_once __DIR__ . '/routes/usuarioRoute.php';
        break;

    case $path === '/api/createUser':
        requerirAdmin();
        require_once __DIR__ . '/routes/usuarioRoute.php';
        break;

    case $path === '/api/modifyInfoUser':
        requerirAdmin();
        require_once __DIR__ . '/routes/usuarioRoute.php';
        break;

    case $path === '/api/modifyPasswordUser':
        requerirAdmin();
        require_once __DIR__ . '/routes/usuarioRoute.php';
        break;

    case $path === '/api/modifyEstadoUser':
        requerirAdmin();
        require_once __DIR__ . '/routes/usuarioRoute.php';
        break;

    case $path === '/api/deleteUser':
        requerirAdmin();
        require_once __DIR__ . '/routes/usuarioRoute.php';
        break;

    case $path === '/api/createProduct':
        requerirAdmin();
        require_once __DIR__ . '/routes/productRoute.php';
        break;

    case $path === '/api/getProduct':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/productRoute.php';
        break;

    case $path === '/api/getAllProducts':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/productRoute.php';
        break;

    case $path === '/api/modifyProduct':
        requerirAdmin();
        require_once __DIR__ . '/routes/productRoute.php';
        break;

    case $path === '/api/deleteProduct':
        requerirAdmin();
        require_once __DIR__ . '/routes/productRoute.php';
        break;

    case $path === '/api/createCategory':
        requerirAdmin();
        require_once __DIR__ . '/routes/categoryRoute.php';
        break;

    case $path === '/api/getCategory':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/categoryRoute.php';
        break;

    case $path === '/api/getAllCategories':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/categoryRoute.php';
        break;

    case $path === '/api/modifyCategory':
        requerirAdmin();
        require_once __DIR__ . '/routes/categoryRoute.php';
        break;

    case $path === '/api/deleteCategory':
        requerirAdmin();
        require_once __DIR__ . '/routes/categoryRoute.php';
        break;

    case $path === '/api/createSupplier':
        requerirAdmin();
        require_once __DIR__ . '/routes/supplierRoute.php';
        break;

    case $path === '/api/getSupplier':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/supplierRoute.php';
        break;

    case $path === '/api/getAllSuppliers':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/supplierRoute.php';
        break;

    case $path === '/api/modifySupplier':
        requerirAdmin();
        require_once __DIR__ . '/routes/supplierRoute.php';
        break;

    case $path === '/api/deleteSupplier':
        requerirAdmin();
        require_once __DIR__ . '/routes/supplierRoute.php';
        break;

    case $path === '/api/openCashRegister':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/cashRegisterRoute.php';
        break;

    case $path === '/api/closeCashRegister':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/cashRegisterRoute.php';
        break;

    case $path === '/api/getCashRegister':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/cashRegisterRoute.php';
        break;

    case $path === '/api/getMontoFinal':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/cashRegisterRoute.php';
        break;

    case $path === '/api/openSale':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/saleRoute.php';
        break;

    case $path === '/api/completeSale':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/saleRoute.php';
        break;

    case $path === '/api/cancelSale':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/saleRoute.php';
        break;

    case $path === '/api/getVenta':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/saleRoute.php';
        break;

    case $path === '/api/getSalesHistory':
    case $path === '/api/getCashRegisters':
    case $path === '/api/getCashRegisterDetail':
        requerirAdmin();
        require_once __DIR__ . '/routes/historyRoute.php';
        break;

    case $path === '/api/getSalesReport':
    case $path === '/api/getRestockReport':
    case $path === '/api/getStockReport':
    case $path === '/api/getSummaryReport':
        requerirAdmin(); // Regla 5: los reportes son solo para el administrador
        require_once __DIR__ . '/routes/reportRoute.php';
        break;

    case $path === '/api/createRestock':
    case $path === '/api/getAllRestocks':
    case $path === '/api/getRestock':
        requerirAdmin();
        require_once __DIR__ . '/routes/restockRoute.php';
        break;

    case $esTienda: // Tienda en línea: su propio router, rutas públicas y de clientes
        require_once __DIR__ . '/routes/tienda/index.php';
        break;

    case $path === '/api/getConfig': // Pública: el nombre de la tienda se muestra en el login
        require_once __DIR__ . '/routes/configRoute.php';
        break;

    case $path === '/api/updateConfig':
        requerirAdmin();
        require_once __DIR__ . '/routes/configRoute.php';
        break;

    case $path === '/api/getAllVentas':
        requerirAutorizacion();
        require_once __DIR__ . '/routes/cashRegisterRoute.php';
        break;

    default:
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Ruta no encontrada']);
        break;

}// --Fin switch principal

} catch (ErrorApi $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[API] ' . $path . ': ' . $e->getMessage());
    http_response_code(500);
    $detalle = ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'development' ? ': ' . $e->getMessage() : '';
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno del servidor' . $detalle]);
}
