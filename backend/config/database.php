<?php
//Clase para crear la conexión a la base de datos
//Mysql utilizando PDO


$backendPath = dirname(__DIR__, 1);

require_once $backendPath . '/vendor/autoload.php';

// safeLoad() skips missing files — Docker injects vars via env_file instead of a file
Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

date_default_timezone_set('America/Mazatlan');


// Variable de entorno: primero la del proceso (getenv) y después la del .env ($_ENV)
// Electron y Docker pasan las credenciales de la base de datos en el entorno del proceso, y deben ganarle a un .env
// que haya en la carpeta: con `php -S`, Dotenv no ve las variables del proceso y escribe en $_ENV las del .env.
// Con variables_order = "GPCS" (php.ini de Ubuntu, por ejemplo) $_ENV no trae las variables del proceso
function variableEntorno(string $nombre, ?string $defecto = null): ?string
{
   $valor = getenv($nombre);
   if ($valor === false || $valor === '') {
      $valor = $_ENV[$nombre] ?? '';
   }
   return $valor === '' ? $defecto : $valor;
}


if (variableEntorno('APP_ENV', 'production') === 'development') {
   error_reporting(E_ALL);
   ini_set('display_errors', '1');
} else {
   error_reporting(0);
   ini_set('display_errors', '0');
}


class Conexion extends PDO
{
   public function __construct()
   {
      //Sobreescribo el método constructor de la clase PDO.
      try {
         // Pdo\Mysql existe desde PHP 8.4; en versiones anteriores se usan las constantes de PDO
         $pdoMysql = class_exists('Pdo\Mysql');
         $initCommand = $pdoMysql ? Pdo\Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND;
         $foundRows = $pdoMysql ? Pdo\Mysql::ATTR_FOUND_ROWS : PDO::MYSQL_ATTR_FOUND_ROWS;
         $options = array(
            $initCommand => 'SET NAMES utf8mb4',
            // rowCount() cuenta las filas encontradas, no solo las que cambiaron:
            // guardar un registro sin modificarlo ya no responde "no se encontró"
            $foundRows => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, #Cuando haya un error retornar excep en vez de false
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC #Obtener los datos de la db en array asociativo con nombres de las columnas
         );
         parent::__construct(
            'mysql:host=' . variableEntorno('DB_HOST') . ';port=' . variableEntorno('DB_PORT', '3306') . ';dbname=' . variableEntorno('DB_NAME'),
            variableEntorno('DB_USER'),
            variableEntorno('DB_PASS'),
            $options
         );
      } catch (PDOException $e) {
         $mensaje = 'Ha surgido un error y no se puede conectar a la base de datos. Detalle: ' . $e->getMessage();
         // En el API se responde JSON con código 500 para que la app muestre el error
         if (PHP_SAPI !== 'cli') {
            http_response_code(500);
            echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
         } else {
            echo $mensaje . "\n";
         }
         exit;
      }
   }
}//--Fin clase Conexión
