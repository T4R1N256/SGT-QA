<?php
// Clase para los datos de la tienda que aparecen en el ticket (una sola fila, ID_Configuracion = 1)

require_once __DIR__ . "/../config/database.php";

class Configuracion
{
    const TABLE = 'Configuracion';
    // ID_Configuracion, Nombre_Tienda, Direccion, Telefono, RFC, Mensaje_Ticket

    const CAMPOS = ['Nombre_Tienda', 'Direccion', 'Telefono', 'RFC', 'Mensaje_Ticket'];

    // Funcion para leer la configuración de la tienda
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


    // Funcion para leer la tasa de IVA en porcentaje (16.0 si no está configurada)
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


    // Funcion para actualizar la configuración de la tienda
    // $datos trae solo los campos que cambian
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
