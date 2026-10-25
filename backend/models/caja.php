<?php

//Rutas de caja

require_once __DIR__ . "/../config/database.php";

class Caja
{

    const TABLE = "Caja";
    // ID_Caja, Fecha, Hora, Monto_Inicial, Monto_Final, Monto_Final_Sistema, Estado, Estado_Final, ID_Usuario, ID_Usuario_Cierre, Hora_Final

    // Funcion para abrir (crear) caja
    public static function openCaja($Fecha, $Hora, $Monto_Inicial, $Estado, $ID_Usuario)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('INSERT INTO ' . self::TABLE . ' (Fecha, Hora, Monto_Inicial,
            Estado, ID_Usuario) VALUES (:Fecha, :Hora, :Monto_Inicial,
            :Estado, :ID_Usuario)');
            $sql->bindValue(':Fecha', $Fecha);
            $sql->bindValue(':Hora', $Hora);
            $sql->bindValue(':Monto_Inicial', $Monto_Inicial);
            $sql->bindValue(':Estado', $Estado);
            $sql->bindValue(':ID_Usuario', $ID_Usuario);
            $sql->execute();

            $ID_Caja = $connection->lastInsertId();
            $connection = NULL;


            return $ID_Caja;



        } catch (PDOException $e) {
            throw new Exception("Hubo un error " . $e->getMessage());
        }
    }//-- Fin funcion abrir caja


    //Funcion cerrar caja (dentro de la transacción de la ruta)
    public static function closeCaja($connection, $ID_Caja, $Monto_Final, $Monto_Final_Sistema, $Estado_Final, $Hora_Final, $ID_Usuario_Cierre)
    {
        try {
            $Estado = 'cerrada';

            $sql = $connection->prepare("UPDATE " . self::TABLE . " SET Monto_Final
            = :Monto_Final, Monto_Final_Sistema = :Monto_Final_Sistema, Estado = :Estado, Estado_Final = :Estado_Final,
            Hora_Final = :Hora_Final, ID_Usuario_Cierre = :ID_Usuario_Cierre
            WHERE ID_Caja = :ID_Caja");
            $sql->bindValue(":ID_Caja", $ID_Caja, PDO::PARAM_INT);
            $sql->bindValue(":Monto_Final", $Monto_Final);
            $sql->bindValue(":Monto_Final_Sistema", $Monto_Final_Sistema);
            $sql->bindValue(":Estado", $Estado);
            $sql->bindValue(":Estado_Final", $Estado_Final);
            $sql->bindValue(":Hora_Final", $Hora_Final);
            $sql->bindValue(":ID_Usuario_Cierre", $ID_Usuario_Cierre, PDO::PARAM_INT);
            $sql->execute();

            return $sql->rowCount() > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error " . $e->getMessage());
        }
    }//-- Fin funcion cerrar caja


    // Funcion para obtener los totales de las ventas finalizadas de una caja, por forma de pago
    // El cuadre de caja solo cuenta el efectivo
    public static function totalesVentas($ID_Caja, $connection = NULL)
    {
        try {
            $conn = $connection ?? new Conexion;

            $sql = $conn->prepare("SELECT
                COALESCE(SUM(CASE WHEN Forma_Pago = 'efectivo' THEN Monto END), 0) AS efectivo,
                COALESCE(SUM(CASE WHEN Forma_Pago = 'tarjeta' THEN Monto END), 0) AS tarjeta,
                COALESCE(SUM(CASE WHEN Forma_Pago = 'transferencia' THEN Monto END), 0) AS transferencia,
                COALESCE(SUM(Monto), 0) AS total,
                COUNT(*) AS ventas
                FROM Venta WHERE ID_Caja = :ID_Caja AND Estado = 'finalizada'");
            $sql->bindValue(':ID_Caja', $ID_Caja, PDO::PARAM_INT);
            $sql->execute();
            $totales = $sql->fetch();
            $conn = NULL;

            return [
                'efectivo' => (float) $totales['efectivo'],
                'tarjeta' => (float) $totales['tarjeta'],
                'transferencia' => (float) $totales['transferencia'],
                'total' => (float) $totales['total'],
                'ventas' => (int) $totales['ventas'],
            ];

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion totales de ventas


    // Funcion para saber si existe alguna caja abierta
    public static function cajaAbierta()
    {
        try {
            $connection = new Conexion();
            $sql = $connection->prepare('SELECT ID_Caja, Fecha, Hora, Monto_Inicial, ID_Usuario FROM ' . self::TABLE . '
            WHERE Estado = :Estado ORDER BY ID_Caja DESC LIMIT 1');
            $sql->bindValue(':Estado', 'abierta');
            $sql->execute();
            $caja = $sql->fetch();
            $connection = NULL;

            return $caja ? $caja : false;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion caja abierta


    // Funcion para obtener la caja abierta o responder 409 (regla 2: sin caja abierta no hay ventas)
    public static function requerirAbierta()
    {
        $caja = self::cajaAbierta();
        if (!$caja) {
            throw new ErrorApi('No hay caja abierta', 409);
        }
        return $caja;
    }//-- Fin funcion requerir caja abierta


    // Columnas comunes del historial y del detalle de cajas
    const COLUMNAS_HISTORIAL = 'c.ID_Caja, c.Fecha, c.Hora, c.Hora_Final, c.Monto_Inicial, c.Monto_Final_Sistema,
            c.Monto_Final, c.Monto_Final - c.Monto_Final_Sistema AS Diferencia, c.Estado, c.Estado_Final,
            ea.Nombre AS Abrio, ec.Nombre AS Cerro,
            (SELECT COUNT(*) FROM Venta v WHERE v.ID_Caja = c.ID_Caja AND v.Estado = \'finalizada\') AS Ventas,
            (SELECT COALESCE(SUM(v.Monto), 0) FROM Venta v WHERE v.ID_Caja = c.ID_Caja AND v.Estado = \'finalizada\') AS Total_Ventas';

    const JOINS_HISTORIAL = 'LEFT JOIN Empleado ea ON ea.ID_Usuario = c.ID_Usuario
            LEFT JOIN Empleado ec ON ec.ID_Usuario = c.ID_Usuario_Cierre';


    // Funcion para leer las cajas abiertas en un rango de fechas (la más reciente primero)
    public static function readHistorial($Desde, $Hasta)
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT ' . self::COLUMNAS_HISTORIAL . ' FROM ' . self::TABLE . ' c
            ' . self::JOINS_HISTORIAL . '
            WHERE c.Fecha BETWEEN :Desde AND :Hasta
            ORDER BY c.Fecha DESC, c.Hora DESC, c.ID_Caja DESC');
            $sql->bindValue(':Desde', $Desde);
            $sql->bindValue(':Hasta', $Hasta);
            $sql->execute();
            $cajas = $sql->fetchAll();
            $connection = NULL;

            return $cajas;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion leer historial de cajas


    // Funcion para leer una caja del historial
    public static function readCajaHistorial($ID_Caja)
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT ' . self::COLUMNAS_HISTORIAL . ' FROM ' . self::TABLE . ' c
            ' . self::JOINS_HISTORIAL . '
            WHERE c.ID_Caja = :ID_Caja');
            $sql->bindValue(':ID_Caja', $ID_Caja, PDO::PARAM_INT);
            $sql->execute();
            $caja = $sql->fetch();
            $connection = NULL;

            return $caja ?: false;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion leer caja del historial


    // Funcion para leer el último corte (la caja cerrada más reciente)
    public static function readCaja()
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT c.ID_Caja, c.Fecha, c.Hora, c.Monto_Inicial, c.Monto_Final,
            c.Monto_Final_Sistema, c.Estado_Final, c.Hora_Final, e.Nombre  FROM ' . self::TABLE . ' c
            INNER JOIN Empleado e ON e.ID_Usuario = c.ID_Usuario
            WHERE c.Estado = :Estado
            ORDER BY c.Fecha DESC, c.Hora_Final DESC, c.ID_Caja DESC LIMIT 1');
            $sql->bindValue(':Estado', 'cerrada');
            $sql->execute();
            $caja = $sql->fetch();
            $connection = NULL;

            if ($caja) {
                return $caja;
            } else {
                return false;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }

    }

}//-- Fin clase Caja
