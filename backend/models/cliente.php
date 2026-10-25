<?php
// Cuentas de cliente de la tienda en línea (D5: se compra con registro)
// Los clientes son independientes de los empleados de SGT (T-S7): su propia tabla y su propia sesión (SGT_TIENDA)

require_once __DIR__ . '/../config/database.php';

class Cliente
{
    // Hash de relleno: al ingresar con un correo que no existe también se verifica una contraseña,
    // para que el tiempo de respuesta no delate qué correos tienen cuenta
    private const HASH_RELLENO = '$2y$10$.C9Tn0.SSpqQR9sfVVMV/eJtkVBbV6ExVhK5RJ7EWhzlpIGXV2Z62';

    // Letras (con acentos), espacios, apóstrofos, puntos y guiones: "María José", "O'Connor", "Pérez-Díaz"
    private const NOMBRE = "/^\\p{L}[\\p{L}\\p{M} '.\\-]*$/u";


    // Valida los datos del registro. Regresa los datos limpios o lanza ErrorCampos con un mensaje por campo
    // Las reglas son las mismas que valida el navegador (src/tienda/cuenta/validaciones.js)
    public static function validar(array $data): array
    {
        $errores = [];
        $texto = function (string $campo) use ($data, &$errores): string {
            $valor = $data[$campo] ?? '';
            if (!is_string($valor)) {
                $errores[$campo] = 'Dato no válido';
                return '';
            }
            return trim($valor);
        };

        $nombre = $texto('nombre');
        $apellidos = $texto('apellidos');
        $correo = mb_strtolower($texto('correo'));
        $telefono = preg_replace('/[\s()\-]/', '', $texto('telefono'));
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        $nombres = [
            'nombre' => [$nombre, 'tu nombre', 'El nombre', 'puede'],
            'apellidos' => [$apellidos, 'tus apellidos', 'Los apellidos', 'pueden'],
        ];
        foreach ($nombres as $campo => [$valor, $falta, $etiqueta, $puede]) {
            if (isset($errores[$campo])) {
                continue;
            }
            if ($valor === '') {
                $errores[$campo] = "Escribe $falta";
            } elseif (mb_strlen($valor) > 100) {
                $errores[$campo] = "$etiqueta no $puede tener más de 100 caracteres";
            } elseif (!preg_match(self::NOMBRE, $valor)) {
                $errores[$campo] = "$etiqueta solo $puede tener letras, espacios, apóstrofos y guiones";
            }
        }

        if (!isset($errores['correo'])) {
            if ($correo === '') {
                $errores['correo'] = 'Escribe tu correo';
            } elseif (mb_strlen($correo) > 254) {
                $errores['correo'] = 'El correo no puede tener más de 254 caracteres';
            } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $errores['correo'] = 'Escribe un correo válido, como nombre@ejemplo.com';
            }
        }

        if (!isset($errores['telefono'])) {
            if ($telefono === '') {
                $errores['telefono'] = 'Escribe tu teléfono';
            } elseif (!preg_match('/^\d{10}$/', $telefono)) {
                $errores['telefono'] = 'El teléfono debe tener 10 dígitos';
            }
        }

        if ($password === '') {
            $errores['password'] = 'Escribe una contraseña';
        } elseif (mb_strlen($password) < 8) {
            $errores['password'] = 'La contraseña debe tener al menos 8 caracteres';
        } elseif (strlen($password) > 72) {
            // bcrypt solo usa los primeros 72 bytes
            $errores['password'] = 'La contraseña no puede tener más de 72 caracteres';
        } elseif (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
            $errores['password'] = 'La contraseña debe tener letras y números';
        }

        if ($errores) {
            throw new ErrorCampos($errores);
        }
        return compact('nombre', 'apellidos', 'correo', 'telefono', 'password');
    }//-- Fin funcion validar


    // Crea la cuenta y regresa su ID. Un correo repetido responde 409 en el campo correo
    public static function registrar(array $datos): int
    {
        $connection = new Conexion;
        try {
            $sql = $connection->prepare('INSERT INTO Cliente (Nombre, Apellidos, Correo, Telefono, Password)
                VALUES (:Nombre, :Apellidos, :Correo, :Telefono, :Password)');
            $sql->execute([
                ':Nombre' => $datos['nombre'],
                ':Apellidos' => $datos['apellidos'],
                ':Correo' => $datos['correo'],
                ':Telefono' => $datos['telefono'],
                ':Password' => password_hash($datos['password'], PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ErrorCampos(['correo' => 'Ya existe una cuenta con este correo'], 'Ya existe una cuenta con este correo', 409);
            }
            throw $e;
        }
        return (int) $connection->lastInsertId();
    }//-- Fin funcion registrar


    // Cliente activo con ese correo y contraseña, o null
    public static function ingresar(string $correo, string $password): ?array
    {
        $connection = new Conexion;
        $sql = $connection->prepare('SELECT * FROM Cliente WHERE Correo = :Correo AND Activo = 1');
        $sql->execute([':Correo' => mb_strtolower(trim($correo))]);
        $cliente = $sql->fetch();

        $valida = password_verify($password, $cliente ? $cliente['Password'] : self::HASH_RELLENO);
        return $cliente && $valida ? self::publico($cliente) : null;
    }//-- Fin funcion ingresar


    // Cliente activo por su ID, o null (por ejemplo, si se desactivó con la sesión abierta)
    public static function leer(int $ID_Cliente): ?array
    {
        $connection = new Conexion;
        $sql = $connection->prepare('SELECT * FROM Cliente WHERE ID_Cliente = :id AND Activo = 1');
        $sql->execute([':id' => $ID_Cliente]);
        $cliente = $sql->fetch();
        return $cliente ? self::publico($cliente) : null;
    }//-- Fin funcion leer


    // Datos que ve el cliente de su cuenta (nunca la contraseña)
    private static function publico(array $fila): array
    {
        return [
            'id' => (int) $fila['ID_Cliente'],
            'nombre' => $fila['Nombre'],
            'apellidos' => $fila['Apellidos'],
            'correo' => $fila['Correo'],
            'telefono' => $fila['Telefono'],
        ];
    }//-- Fin funcion publico
}
