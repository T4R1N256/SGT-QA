<?php

require_once __DIR__ . "/../config/database.php";

/**
 * Datos de la tienda que aparecen en el ticket y en la tienda en línea
 * (una sola fila, ID_Configuracion = 1).
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Venta::readTicket()
 */
class Configuracion
{
    const TABLE = 'Configuracion';
    // ID_Configuracion, Nombre_Tienda, Direccion, Telefono, RFC, Mensaje_Ticket

    const CAMPOS = ['Nombre_Tienda', 'Direccion', 'Telefono', 'RFC', 'Mensaje_Ticket'];

    /**
     * Lee la configuración de la tienda.
     *
     * @return array|false Nombre_Tienda, Direccion, Telefono, RFC y Mensaje_Ticket, o false si no hay fila
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function readConfiguracion()
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT ' . implode(', ', self::CAMPOS) . ' FROM ' . self::TABLE . '
            WHERE ID_Configuracion = 1');
            $sql->execute();
            $configuracion = $sql->fetch();
            $connection = NULL;

            return $configuracion ?: false;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion leer configuracion


    /**
     * Tasa de IVA en porcentaje; la usan el carrito, los pedidos y la configuración de la tienda en línea.
     *
     * @return float La tasa (16.0 si no está configurada)
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function tasaIVA(): float
    {
        try {
            $connection = new Conexion;
            $tasa = $connection->query('SELECT Tasa_IVA FROM ' . self::TABLE . ' WHERE ID_Configuracion = 1')->fetchColumn();
            $connection = NULL;

            return $tasa === false ? 16.0 : (float) $tasa;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion tasa de IVA


    /**
     * Actualiza la configuración de la tienda. Los campos ya vienen validados por la ruta.
     *
     * @param array $datos Solo los campos que cambian (de Configuracion::CAMPOS), con su valor
     * @return int 1 si se actualizó o no había cambios, 0 si no existe la fila
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function updateConfiguracion(array $datos)
    {
        try {
            $asignaciones = [];
            foreach (array_keys($datos) as $campo) {
                $asignaciones[] = "$campo = :$campo";
            }
            if (!$asignaciones) {
                return 1;
            }

            $connection = new Conexion;
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET ' . implode(', ', $asignaciones) . '
            WHERE ID_Configuracion = 1');
            foreach ($datos as $campo => $valor) {
                $sql->bindValue(":$campo", $valor, PDO::PARAM_STR);
            }
            $sql->execute();
            $row = $sql->rowCount();
            $connection = NULL;

            return $row > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion actualizar configuracion
}//-- Fin clase Configuracion
