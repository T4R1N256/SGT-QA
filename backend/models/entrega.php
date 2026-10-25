<?php
// Datos de entrega de un pedido (R11, R12). Se validan aquí y en el navegador con las mismas reglas
// (src/tienda/compra/validaciones.js). El envío no se cobra (D3)

require_once __DIR__ . '/../config/database.php';

class Entrega
{
    const ENTIDADES = [
        'Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche', 'Chiapas', 'Chihuahua',
        'Ciudad de México', 'Coahuila', 'Colima', 'Durango', 'Estado de México', 'Guanajuato', 'Guerrero',
        'Hidalgo', 'Jalisco', 'Michoacán', 'Morelos', 'Nayarit', 'Nuevo León', 'Oaxaca', 'Puebla', 'Querétaro',
        'Quintana Roo', 'San Luis Potosí', 'Sinaloa', 'Sonora', 'Tabasco', 'Tamaulipas', 'Tlaxcala', 'Veracruz',
        'Yucatán', 'Zacatecas',
    ];

    // Campo => [obligatorio, máximo de caracteres, mensaje si falta, etiqueta para los mensajes, verbo (puede/pueden)]
    private const CAMPOS = [
        'recibe' => [true, 150, 'Escribe quién recibe el pedido', 'El nombre de quien recibe'],
        'telefono' => [true, 20, 'Escribe un teléfono de contacto', 'El teléfono'],
        'calle' => [true, 100, 'Escribe la calle', 'La calle'],
        'numero_exterior' => [true, 10, 'Escribe el número exterior', 'El número exterior'],
        'numero_interior' => [false, 10, '', 'El número interior'],
        'colonia' => [true, 100, 'Escribe la colonia', 'La colonia'],
        'codigo_postal' => [true, 5, 'Escribe el código postal', 'El código postal'],
        'ciudad' => [true, 100, 'Escribe la ciudad o municipio', 'La ciudad o municipio'],
        'entidad' => [true, 40, 'Elige la entidad', 'La entidad'],
        'referencias' => [false, 255, '', 'Las referencias', 'pueden'],
    ];

    private const NOMBRE = "/^\\p{L}[\\p{L}\\p{M} '.\\-]*$/u";
    // Número exterior o interior: 123, 123-B, S/N, #12, Depto. 4
    private const NUMERO = '/^(?=.*[\p{L}\d])[\p{L}\d#][\p{L}\d \-\/#.]*$/u';

    // Valida los datos de entrega. Regresa los datos limpios o lanza ErrorCampos con un mensaje por campo
    public static function validar(array $data): array
    {
        $datos = [];
        $errores = [];

        foreach (self::CAMPOS as $campo => [$obligatorio, $maximo, $falta, $etiqueta]) {
            $puede = self::CAMPOS[$campo][4] ?? 'puede';
            $valor = $data[$campo] ?? '';
            if (!is_string($valor)) {
                $errores[$campo] = 'Dato no válido';
                continue;
            }
            // Los saltos de línea y espacios repetidos no aportan nada en una dirección
            $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
            if ($campo === 'telefono') {
                $valor = preg_replace('/[\s()\-]/', '', $valor);
            }
            $datos[$campo] = $valor;

            if ($valor === '') {
                if ($obligatorio) {
                    $errores[$campo] = $falta;
                }
            } elseif (mb_strlen($valor) > $maximo) {
                $errores[$campo] = "$etiqueta no $puede tener más de $maximo caracteres";
            }
        }

        $formato = function (string $campo, bool $valido, string $mensaje) use (&$errores, $datos) {
            if (!isset($errores[$campo]) && $datos[$campo] !== '' && !$valido) {
                $errores[$campo] = $mensaje;
            }
        };
        $formato('recibe', (bool) preg_match(self::NOMBRE, $datos['recibe'] ?? ''), 'El nombre de quien recibe solo puede tener letras, espacios, apóstrofos y guiones');
        $formato('telefono', (bool) preg_match('/^\d{10}$/', $datos['telefono'] ?? ''), 'El teléfono debe tener 10 dígitos');
        $formato('numero_exterior', (bool) preg_match(self::NUMERO, $datos['numero_exterior'] ?? ''), 'El número exterior solo puede tener letras, números, espacios y - / # .');
        $formato('numero_interior', (bool) preg_match(self::NUMERO, $datos['numero_interior'] ?? ''), 'El número interior solo puede tener letras, números, espacios y - / # .');
        $formato('codigo_postal', (bool) preg_match('/^\d{5}$/', $datos['codigo_postal'] ?? ''), 'El código postal debe tener 5 dígitos');
        $formato('entidad', in_array($datos['entidad'] ?? '', self::ENTIDADES, true), 'Elige una entidad de la lista');

        if ($errores) {
            throw new ErrorCampos($errores);
        }
        return $datos;
    }//-- Fin funcion validar


    // Datos de entrega del último pedido del cliente, para prellenar el formulario (T-S4), o null
    public static function ultima(int $ID_Cliente): ?array
    {
        $connection = new Conexion;
        $sql = $connection->prepare('SELECT Recibe, Telefono, Calle, Numero_Exterior, Numero_Interior, Colonia, Codigo_Postal,
            Ciudad, Entidad, Referencias FROM Pedido WHERE ID_Cliente = :id ORDER BY ID_Pedido DESC LIMIT 1');
        $sql->execute([':id' => $ID_Cliente]);
        $fila = $sql->fetch();
        return $fila ? self::desdeFila($fila) : null;
    }//-- Fin funcion ultima


    // Columnas de Pedido => datos de entrega
    public static function desdeFila(array $fila): array
    {
        return [
            'recibe' => $fila['Recibe'],
            'telefono' => $fila['Telefono'],
            'calle' => $fila['Calle'],
            'numero_exterior' => $fila['Numero_Exterior'],
            'numero_interior' => $fila['Numero_Interior'],
            'colonia' => $fila['Colonia'],
            'codigo_postal' => $fila['Codigo_Postal'],
            'ciudad' => $fila['Ciudad'],
            'entidad' => $fila['Entidad'],
            'referencias' => $fila['Referencias'],
        ];
    }//-- Fin funcion desde fila
}
