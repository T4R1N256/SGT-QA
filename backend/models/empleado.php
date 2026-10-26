<?php

require_once __DIR__ . "/../config/database.php";

/**
 * Datos de la persona detrás de cada usuario: nombre, puesto (admin o empleado) y estado
 * (activo o inactivo). Un empleado inactivo no aparece en la lista de usuarios.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Usuario
 * @see Contacto_Empleado
 */
class Empleado
{
    const TABLE = 'Empleado';
    // ID_Empleado, Nombre, Puesto, Estado, ID_Contacto_Empleado, ID_Usuario
    // 1 = correcto
    // 0 = no hay registro o no se encontró 
    // -1 = hubo un error en la ejecución

    /**
     * Crea el empleado de un usuario. Se llama dentro de la transacción que crea el usuario.
     *
     * @param PDO    $connection           Conexión con la transacción abierta
     * @param string $Nombre               Nombre completo
     * @param string $Puesto               "admin" o "empleado"
     * @param string $Estado               "activo"
     * @param int    $ID_Contacto_Empleado Contacto ya creado
     * @param int    $ID_Usuario           Usuario ya creado
     * @return string ID del empleado creado
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function crearEmpleado($connection, $Nombre, $Puesto, $Estado, $ID_Contacto_Empleado, $ID_Usuario)
    {

        try {
            $sql = $connection->prepare(
                'INSERT INTO ' . self::TABLE . ' (Nombre, Puesto, Estado, ID_Contacto_Empleado, ID_Usuario) 
                VALUES (:Nombre, :Puesto, :Estado, :ID_Contacto_Empleado, :ID_Usuario)'
            );
            $sql->bindValue(':Nombre', $Nombre, PDO::PARAM_STR);
            $sql->bindValue(':Puesto', $Puesto, PDO::PARAM_STR);
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $sql->bindValue(':ID_Contacto_Empleado', $ID_Contacto_Empleado, PDO::PARAM_INT);
            $sql->bindValue(':ID_Usuario', $ID_Usuario, PDO::PARAM_INT);
            $sql->execute();
            return $connection->lastInsertId();


        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion crear empleado 

    /**
     * IDs del empleado y de su contacto a partir del usuario.
     *
     * @param int $ID_Usuario Usuario
     * @return array|false ['ID_Empleado', 'ID_Contacto_Empleado'], o false si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function getIDsByUsuario($ID_Usuario)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('SELECT ID_Empleado, ID_Contacto_Empleado FROM ' . self::TABLE . ' 
            WHERE ID_Usuario = :ID_Usuario');
            $sql->bindValue(':ID_Usuario', $ID_Usuario, PDO::PARAM_INT);
            $sql->execute();
            $row = $sql->fetch();
            $connection = NULL;

            if ($row) {
                return $row;
            } else {
                return false;
            }


        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }

    }//-- Fin funcion obtener IDs

    /**
     * Actualiza nombre, puesto y estado de un empleado.
     *
     * @param PDO    $connection  Conexión con la transacción abierta
     * @param int    $ID_Empleado Empleado
     * @param string $Nombre      Nombre completo
     * @param string $Puesto      "admin" o "empleado"
     * @param string $Estado      "activo" o "inactivo"
     * @return int 1 si se actualizó, 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function updateEmpleado($connection, $ID_Empleado, $Nombre, $Puesto, $Estado)
    {

        try {
            $sql = $connection->prepare(
                'UPDATE ' . self::TABLE . ' SET Nombre = :Nombre, Puesto = :Puesto, Estado = :Estado WHERE ID_Empleado = :ID_Empleado'
            );
            $sql->bindValue(':ID_Empleado', $ID_Empleado, PDO::PARAM_INT);
            $sql->bindValue(':Nombre', $Nombre, PDO::PARAM_STR);
            $sql->bindValue(':Puesto', $Puesto, PDO::PARAM_STR);
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $valid = $sql->execute();
            $row = $sql->rowCount();


            if ($row > 0) {
                return 1;
            } else {
                return 0;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion actualizar empleado 

    /**
     * Da de baja a un empleado (Estado = inactivo); su usuario se marca como no autorizado
     * en la misma transacción.
     *
     * @param PDO $connection  Conexión con la transacción abierta
     * @param int $ID_Empleado Empleado
     * @return int 1 si se desactivó, 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     * @see Usuario::updateEstado()
     */
    public static function desactivarEmpleado($connection, $ID_Empleado)
    {
        try {
            $sql = $connection->prepare(
                'UPDATE ' . self::TABLE . ' SET Estado = :Estado WHERE ID_Empleado = :ID_Empleado'
            );
            $sql->bindValue(':ID_Empleado', $ID_Empleado, PDO::PARAM_INT);
            $sql->bindValue(':Estado', 'inactivo');
            $sql->execute();
            return $sql->rowCount() > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }

    }//-- Fin funcion eliminar empleado
}//--Fin clase Empleado
