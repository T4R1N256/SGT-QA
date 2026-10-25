<?php
// Lectura y validación de los datos que llegan al API
// Reemplaza el htmlspecialchars que se aplicaba a las entradas: el texto se guarda tal cual
// (React escapa al mostrar) y los números se validan como números.
// Las funciones lanzan ErrorValidacion; index.php la convierte en una respuesta 400 con el mensaje.


// Error que se le responde al cliente: el código de la excepción es el código HTTP
// Las rutas lo lanzan y index.php responde {ok: false, mensaje} con ese código
class ErrorApi extends Exception
{
}

// Dato de entrada inválido o faltante (400)
class ErrorValidacion extends ErrorApi
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje, 400);
    }
}


// Cuerpo JSON de la petición como arreglo ([] si viene vacío o no es JSON)
function leerJson(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}


// Nombre legible del campo para los mensajes: Monto_Inicial -> Monto inicial
function nombreCampo(string $campo): string
{
    return ucfirst(strtolower(str_replace('_', ' ', $campo)));
}


// Texto sin espacios al inicio ni al final
// Si no viene: error si es requerido, null si no. Si viene vacío y no es requerido regresa '' (para poder borrarlo)
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


// Contraseña: obligatoria y sin recortar espacios
function campoPassword(array $data, string $campo = 'Password'): string
{
    $valor = $data[$campo] ?? null;
    if (!is_string($valor) || $valor === '') {
        throw new ErrorValidacion('La contraseña es obligatoria');
    }
    return $valor;
}


// Número entero mayor o igual a $minimo
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


// Cantidad de dinero mayor o igual a $minimo, redondeada a centavos
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


// Fecha en formato AAAA-MM-DD (por ejemplo, para filtrar por rango)
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


// Rango de fechas de los filtros (desde, hasta); por defecto, los últimos 30 días
// Regresa [Desde, Hasta] en formato AAAA-MM-DD
function rangoFechas(array $parametros): array
{
    $Hasta = campoFecha($parametros, 'hasta', false) ?? date('Y-m-d');
    $Desde = campoFecha($parametros, 'desde', false) ?? date('Y-m-d', strtotime("$Hasta -30 days"));

    if ($Desde > $Hasta) {
        throw new ErrorValidacion('La fecha inicial no puede ser posterior a la final');
    }
    return [$Desde, $Hasta];
}


// Valor que debe ser una de las opciones permitidas
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


// Convierte una cantidad de dinero a centavos enteros para comparar sin errores de redondeo
function aCentavos(float|int|string|null $monto): int
{
    return (int) round(((float) $monto) * 100);
}
