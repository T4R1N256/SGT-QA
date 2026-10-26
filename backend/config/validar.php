<?php
/**
 * Lectura y validación de los datos que llegan al API.
 * Reemplaza el htmlspecialchars que se aplicaba a las entradas: el texto se guarda tal cual
 * (React lo escapa al mostrarlo) y los números se validan como números.
 * Las funciones lanzan ErrorValidacion, que index.php convierte en una respuesta 400 con el mensaje.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 */


/**
 * Error que se le responde al cliente: el código de la excepción es el código HTTP.
 * Las rutas lo lanzan y index.php responde {ok: false, mensaje} con ese código.
 */
class ErrorApi extends Exception
{
}

/**
 * Dato de entrada inválido o faltante: se responde con 400.
 *
 * @see campoTexto()
 */
class ErrorValidacion extends ErrorApi
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje, 400);
    }
}


/**
 * Error que lleva datos para el cliente además del mensaje; index.php los agrega a la respuesta.
 * Por ejemplo, los avisos del carrito cuando un pedido no se pudo confirmar, o requiere_confirmacion
 * al desactivar una categoría con productos activos.
 */
class ErrorDatos extends ErrorApi
{
    public function __construct(string $mensaje, int $codigo, public readonly array $datos)
    {
        parent::__construct($mensaje, $codigo);
    }
}

/**
 * Varios campos inválidos a la vez (formularios de la tienda en línea).
 * index.php responde {ok: false, mensaje, errores: {campo: mensaje}} para marcar cada campo.
 */
class ErrorCampos extends ErrorDatos
{
    public function __construct(array $errores, string $mensaje = 'Revisa los datos marcados', int $codigo = 400)
    {
        parent::__construct($mensaje, $codigo, ['errores' => $errores]);
    }
}


/**
 * Lee el cuerpo JSON de la petición.
 *
 * @return array Los datos, o [] si el cuerpo viene vacío o no es JSON
 */
function leerJson(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}


/**
 * Nombre legible de un campo para los mensajes: Monto_Inicial -> "Monto inicial".
 *
 * @param string $campo Nombre del campo como llega en la petición
 * @return string El nombre para mostrar
 */
function nombreCampo(string $campo): string
{
    return ucfirst(strtolower(str_replace('_', ' ', $campo)));
}


/**
 * Lee un texto y le quita los espacios al inicio y al final.
 *
 * @param array  $data      Datos de la petición
 * @param string $campo     Nombre del campo
 * @param bool   $requerido Si es obligatorio
 * @param int    $maximo    Largo máximo en caracteres
 * @return string|null El texto; '' si viene vacío y no es requerido (para poder borrarlo); null si no viene
 * @throws ErrorValidacion Si falta siendo requerido, no es texto o pasa del largo máximo
 */
function campoTexto(array $data, string $campo, bool $requerido = true, int $maximo = 255): ?string
{
    $valor = $data[$campo] ?? null;

    if ($valor === null) {
        if ($requerido) {
            throw new ErrorValidacion(nombreCampo($campo) . ' es obligatorio');
        }
        return null;
    }
    if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
        throw new ErrorValidacion(nombreCampo($campo) . ' no es válido');
    }

    $valor = trim((string) $valor);

    if ($valor === '' && $requerido) {
        throw new ErrorValidacion(nombreCampo($campo) . ' es obligatorio');
    }
    if (!preg_match('/^.{0,' . $maximo . '}$/us', $valor)) {
        throw new ErrorValidacion(nombreCampo($campo) . " no puede tener más de $maximo caracteres");
    }
    return $valor;
}


/**
 * Lee un teléfono opcional: 10 o 12 dígitos, la misma regla que el formulario de Usuarios.
 *
 * @param array  $data  Datos de la petición
 * @param string $campo Nombre del campo
 * @return string|null El teléfono; '' si viene vacío (para borrarlo); null si no viene
 * @throws ErrorValidacion Si trae algo distinto de 10 o 12 dígitos
 * @see campoTexto()
 */
function campoTelefono(array $data, string $campo = 'Telefono'): ?string
{
    $valor = campoTexto($data, $campo, false, 20);

    if ($valor !== null && $valor !== '' && !preg_match('/^(\d{10}|\d{12})$/', $valor)) {
        throw new ErrorValidacion('El teléfono debe tener 10 o 12 dígitos numéricos');
    }
    return $valor;
}


/**
 * Lee la contraseña: es obligatoria y no se le recortan los espacios.
 *
 * @param array  $data  Datos de la petición
 * @param string $campo Nombre del campo
 * @return string La contraseña tal cual
 * @throws ErrorValidacion Si falta o está vacía
 */
function campoPassword(array $data, string $campo = 'Password'): string
{
    $valor = $data[$campo] ?? null;
    if (!is_string($valor) || $valor === '') {
        throw new ErrorValidacion('La contraseña es obligatoria');
    }
    return $valor;
}


/**
 * Lee un número entero mayor o igual a $minimo.
 *
 * @param array  $data      Datos de la petición
 * @param string $campo     Nombre del campo
 * @param bool   $requerido Si es obligatorio
 * @param int    $minimo    Valor mínimo permitido
 * @return int|null El número, o null si no viene y no es requerido
 * @throws ErrorValidacion Si falta siendo requerido, no es entero o es menor que $minimo
 */
function campoEntero(array $data, string $campo, bool $requerido = true, int $minimo = 0): ?int
{
    $valor = $data[$campo] ?? null;

    if ($valor === null || $valor === '') {
        if ($requerido) {
            throw new ErrorValidacion(nombreCampo($campo) . ' es obligatorio');
        }
        return null;
    }

    $entero = is_bool($valor) ? false : filter_var($valor, FILTER_VALIDATE_INT);
    if ($entero === false) {
        throw new ErrorValidacion(nombreCampo($campo) . ' debe ser un número entero');
    }
    if ($entero < $minimo) {
        throw new ErrorValidacion(nombreCampo($campo) . " debe ser mayor o igual a $minimo");
    }
    return $entero;
}


/**
 * Lee una cantidad de dinero mayor o igual a $minimo, redondeada a centavos.
 *
 * @param array  $data      Datos de la petición
 * @param string $campo     Nombre del campo
 * @param bool   $requerido Si es obligatorio
 * @param float  $minimo    Monto mínimo permitido
 * @return float|null El monto, o null si no viene y no es requerido
 * @throws ErrorValidacion Si falta siendo requerido, no es un número o es menor que $minimo
 */
function campoDinero(array $data, string $campo, bool $requerido = true, float $minimo = 0): ?float
{
    $valor = $data[$campo] ?? null;

    if ($valor === null || $valor === '') {
        if ($requerido) {
            throw new ErrorValidacion(nombreCampo($campo) . ' es obligatorio');
        }
        return null;
    }
    if (is_bool($valor) || !is_numeric($valor) || !is_finite((float) $valor)) {
        throw new ErrorValidacion(nombreCampo($campo) . ' debe ser una cantidad válida');
    }

    $dinero = round((float) $valor, 2);
    if ($dinero < $minimo) {
        throw new ErrorValidacion(nombreCampo($campo) . ' debe ser mayor o igual a ' . number_format($minimo, 2));
    }
    return $dinero;
}


/**
 * Lee una fecha en formato AAAA-MM-DD (por ejemplo, para filtrar por rango).
 *
 * @param array  $data      Datos de la petición
 * @param string $campo     Nombre del campo
 * @param bool   $requerido Si es obligatorio
 * @return string|null La fecha, o null si no viene
 * @throws ErrorValidacion Si no es una fecha válida en formato AAAA-MM-DD
 */
function campoFecha(array $data, string $campo, bool $requerido = true): ?string
{
    $valor = campoTexto($data, $campo, $requerido, 10);
    if ($valor === null || $valor === '') {
        return null;
    }

    $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
    if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
        throw new ErrorValidacion(nombreCampo($campo) . ' debe ser una fecha AAAA-MM-DD');
    }
    return $valor;
}


/**
 * Rango de fechas de los filtros (desde, hasta). Por defecto, los últimos 30 días.
 *
 * @param array $parametros Parámetros de la petición ($_GET)
 * @return array [Desde, Hasta] en formato AAAA-MM-DD
 * @throws ErrorValidacion Si alguna fecha no es válida o la inicial es posterior a la final
 * @see campoFecha()
 */
function rangoFechas(array $parametros): array
{
    $Hasta = campoFecha($parametros, 'hasta', false) ?? date('Y-m-d');
    $Desde = campoFecha($parametros, 'desde', false) ?? date('Y-m-d', strtotime("$Hasta -30 days"));

    if ($Desde > $Hasta) {
        throw new ErrorValidacion('La fecha inicial no puede ser posterior a la final');
    }
    return [$Desde, $Hasta];
}


/**
 * Lee un valor que debe ser una de las opciones permitidas.
 *
 * @param array  $data      Datos de la petición
 * @param string $campo     Nombre del campo
 * @param array  $opciones  Valores permitidos
 * @param bool   $requerido Si es obligatorio
 * @return string|null El valor, o null si no viene y no es requerido
 * @throws ErrorValidacion Si falta siendo requerido o no es una de las opciones
 */
function campoOpcion(array $data, string $campo, array $opciones, bool $requerido = true): ?string
{
    $valor = campoTexto($data, $campo, $requerido);

    if ($valor === null || ($valor === '' && !$requerido)) {
        return null;
    }
    if (!in_array($valor, $opciones, true)) {
        throw new ErrorValidacion(nombreCampo($campo) . ' debe ser: ' . implode(', ', $opciones));
    }
    return $valor;
}


/**
 * Convierte una cantidad de dinero a centavos enteros para comparar sin errores de redondeo.
 *
 * @param float|int|string|null $monto Cantidad en pesos
 * @return int La cantidad en centavos
 */
function aCentavos(float|int|string|null $monto): int
{
    return (int) round(((float) $monto) * 100);
}
