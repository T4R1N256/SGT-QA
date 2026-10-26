<?php

require_once __DIR__ . "/../config/database.php";

/**
 * Información de contacto de un empleado: teléfono, correo y domicilio.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Empleado
 */
class Contacto_Empleado
{
    const TABLE = 'Contacto_Empleado';
    // ID_Contacto_Empleado, Telefono, Correo, Calle, Colonia, Codigo_Postal
    // 1 = correcto
    // 0 = no hay registro o no se encontró 
    // -1 = hubo un error en la ejecución


    /**
     * Crea el contacto de un empleado. Se llama dentro de la transacción que crea el usuario.
     *
     * @param PDO         $connection    Conexión con la transacción abierta
     * @param string|null $Telefono      Teléfono (10 o 12 dígitos)
     * @param string|null $Correo        Correo electrónico
     * @param string|null $Calle         Calle y número
     * @param string|null $Colonia       Colonia
     * @param string|null $Codigo_Postal Código postal
     * @return string ID del contacto creado
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function crearContacto($connection, $Telefono, $Correo, $Calle, $Colonia, $Codigo_Postal)
    {

        try {
            $sql = $connection->prepare(
                'INSERT INTO ' . self::TABLE . ' (Telefono, Correo, Calle, Colonia, Codigo_Postal) 
                VALUES (:Telefono, :Correo, :Calle, :Colonia, :Codigo_Postal)'
            );
            $sql->bindValue(':Telefono', $Telefono, PDO::PARAM_STR);
            $sql->bindValue(':Correo', $Correo, PDO::PARAM_STR);
            $sql->bindValue(':Calle', $Calle, PDO::PARAM_STR);
            $sql->bindValue(':Colonia', $Colonia, PDO::PARAM_STR);
            $sql->bindValue(':Codigo_Postal', $Codigo_Postal, PDO::PARAM_STR);
            $sql->execute();
            return $connection->lastInsertId();


        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion crear info de contacto

    /**
     * Actualiza el contacto de un empleado.
     *
     * @param PDO         $connection           Conexión con la transacción abierta
     * @param int         $ID_Contacto_Empleado Contacto
     * @param string|null $Telefono             Teléfono (10 o 12 dígitos)
     * @param string|null $Correo               Correo electrónico
     * @param string|null $Calle                Calle y número
     * @param string|null $Colonia              Colonia
     * @param string|null $Codigo_Postal        Código postal
     * @return int 1 si se actualizó, 0 si no existe, -1 si la consulta no se ejecutó
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function updateContacto($connection, $ID_Contacto_Empleado, $Telefono, $Correo, $Calle, $Colonia, $Codigo_Postal)
    {

        try {
            $sql = $connection->prepare(
                'UPDATE ' . self::TABLE . ' SET Telefono = :Telefono, Correo = :Correo, 
                Calle = :Calle, Colonia = :Colonia, Codigo_Postal = :Codigo_Postal 
                WHERE ID_Contacto_Empleado = :ID_Contacto_Empleado'
            );
            $sql->bindValue(':ID_Contacto_Empleado', $ID_Contacto_Empleado, PDO::PARAM_INT);
            $sql->bindValue(':Telefono', $Telefono, PDO::PARAM_STR);
            $sql->bindValue(':Correo', $Correo, PDO::PARAM_STR);
            $sql->bindValue(':Calle', $Calle, PDO::PARAM_STR);
            $sql->bindValue(':Colonia', $Colonia, PDO::PARAM_STR);
            $sql->bindValue(':Codigo_Postal', $Codigo_Postal, PDO::PARAM_STR);
            $valid = $sql->execute();
            $row = $sql->rowCount();
            if ($valid) {
                if ($row > 0) {
                    return 1;
                } else {
                    return 0;
                }
            } else {
                return -1;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin actualizar contacto

}//--Fin clase Contacto_Empleado
