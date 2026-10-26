<?php

require_once __DIR__ . "/../config/database.php";

/**
 * Cuentas de acceso a SGT (RF13): contraseña (hash) y estado (autorizado o no autorizado).
 * Los datos de la persona están en Empleado y Contacto_Empleado.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Empleado
 * @see Login
 */
class Usuario
{
    const TABLE = 'Usuario';
    // ID_Usuario, Password, Estado
    // 1 = correcto
    // 0 = no hay registro o no se encontró 
    // -1 = hubo un error en la ejecución



    /**
     * Crea un usuario. La contraseña se guarda con password_hash.
     * Se llama dentro de la transacción que también crea el empleado y su contacto.
     *
     * @param PDO    $connection Conexión con la transacción abierta
     * @param string $Password   Contraseña en texto plano
     * @param string $Estado     "autorizado" o "no autorizado"
     * @return string ID del usuario creado (es el que se usa para iniciar sesión)
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function createUsuario($connection, $Password, $Estado)
    {

        try {
            $sql = $connection->prepare(
                'INSERT INTO ' . self::TABLE . ' (Password, Estado) VALUES (:Password, :Estado)'
            );
            $sql->bindValue(':Password', password_hash($Password, PASSWORD_DEFAULT));
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $sql->execute();
            return $connection->lastInsertId();


        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion crear usuario


    /**
     * Usuarios de los empleados activos, con su nombre y su estado.
     *
     * @return array|int Los usuarios, o 0 si no hay
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function readAllUsuario()
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare(
                'SELECT u.ID_Usuario, e.Nombre, u.Estado FROM ' . self::TABLE . ' u 
                INNER JOIN Empleado e ON e.ID_Usuario = u.ID_Usuario WHERE e.Estado = "activo"'
            );
            $sql->execute();
            $usuarios = $sql->fetchAll();
            $connection = NULL;

            if ($usuarios) {
                return $usuarios;
            } else {
                return 0;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion leer todos los usuarios

    /**
     * Datos completos de un usuario: nombre, puesto, estado y contacto.
     *
     * @param int $ID_Usuario Usuario
     * @return array|int Los datos, o 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function readInfoUsuario($ID_Usuario)
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare(
                'SELECT e.Nombre, e.Puesto, u.Estado, c.Telefono, c.Correo,
                c.Calle, c.Colonia, c.Codigo_Postal FROM Empleado e 
                INNER JOIN Contacto_Empleado c ON c.ID_Contacto_Empleado = e.ID_Contacto_Empleado
                INNER JOIN Usuario u ON u.ID_Usuario = e.ID_Usuario
                WHERE e.ID_Usuario = :ID_Usuario'
            );
            $sql->bindValue(':ID_Usuario', $ID_Usuario, PDO::PARAM_INT);
            $sql->execute();
            $usuario = $sql->fetch();
            $connection = NULL;

            if ($usuario) {
                return $usuario;
            } else {
                return 0;
            }
        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion info de usuario

    /**
     * Cambia el estado de un usuario (autorizado o no autorizado).
     *
     * @param int      $ID_Usuario Usuario
     * @param string   $Estado     "autorizado" o "no autorizado"
     * @param PDO|null $connection Conexión de la transacción en curso, o null para abrir una
     * @return int 1 si se actualizó, 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function updateEstado($ID_Usuario, $Estado, $connection = NULL)
    {

        try {
            $conn = $connection ?? new Conexion;


            $sql = $conn->prepare(
                'UPDATE ' . self::TABLE . ' SET Estado = :Estado WHERE ID_Usuario = :ID_Usuario'
            );
            $sql->bindValue(':ID_Usuario', $ID_Usuario, PDO::PARAM_INT);
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $sql->execute();
            $row = $sql->rowCount();
            $conn = NULL;

            if ($row > 0) {
                return 1;
            } else {
                return 0;

            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion actualizar estado de usuario

    /**
     * Cambia la contraseña de un usuario (se guarda con password_hash).
     *
     * @param int    $ID_Usuario Usuario
     * @param string $Password   Contraseña nueva en texto plano
     * @return int 1 si se actualizó, 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function updatePassword($ID_Usuario, $Password)
    {

        try {
            $connection = new Conexion();

            $sql = $connection->prepare(
                'UPDATE ' . self::TABLE . ' SET Password = :Password WHERE ID_Usuario = :ID_Usuario'
            );
            $sql->bindValue(':ID_Usuario', $ID_Usuario, PDO::PARAM_INT);
            $sql->bindValue(':Password', password_hash($Password, PASSWORD_DEFAULT));
            $sql->execute();
            $row = $sql->rowCount();
            $connection = NULL;


            if ($row > 0) {
                return 1;
            } else {
                return 0;
            }


        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion password estado de usuario

}//--Fin clase Usuario