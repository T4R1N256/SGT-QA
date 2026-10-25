<?php
//Clase con funcion que valida credenciales para el login

require_once __DIR__ . "/../config/database.php";


class Login
{
    const TABLE = 'Usuario';
    // ID_Usuario, Password, Estado
    // 1 = correcto
    // 0 = no hay registro o no se encontró 
    // -1 = hubo un error en la ejecución

    //Función para validar credenciales
    public static function validar($ID_Usuario, $Password)
    {

        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT u.Password, u.Estado, e.Puesto FROM ' . self::TABLE . ' u 
            INNER JOIN Empleado e ON e.ID_Usuario = u.ID_Usuario
            WHERE u.ID_Usuario = :ID_Usuario');
            $sql->bindValue(':ID_Usuario', $ID_Usuario);
            $sql->execute();
            $row = $sql->fetch();
            $connection = NULL;

            if ($row) {
                $valida = password_verify($Password, $row['Password']);

                // Antes de la fase 1 las contraseñas se escapaban con htmlspecialchars antes de hashearlas
                // ("a&b" se guardó como hash de "a&amp;b"). Si coincide la versión escapada, se vuelve a hashear la real.
                $escapada = htmlspecialchars($Password);
                if (!$valida && $escapada !== $Password && password_verify($escapada, $row['Password'])) {
                    require_once __DIR__ . '/usuario.php';
                    Usuario::updatePassword($ID_Usuario, $Password);
                    $valida = true;
                }

                if ($valida && $row['Estado'] == 'autorizado') {
                    return $row;
                } elseif ($valida && $row['Estado'] !== 'autorizado') {
                    return -2;
                } else {
                    return 0;
                }
            } else {
                return -1;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//---- Fin Funcion Validar 
}//--Fin Clase Login