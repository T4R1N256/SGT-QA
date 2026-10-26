<?php

require_once __DIR__ . "/../config/database.php";

/**
 * Caja registradora: apertura, corte y consultas del historial de cajas.
 * Solo puede haber una caja abierta a la vez (regla 2: sin caja abierta no hay ventas).
 * Tabla Caja: ID_Caja, Fecha, Hora, Monto_Inicial, Monto_Final, Monto_Final_Sistema, Estado, Estado_Final,
 * ID_Usuario, ID_Usuario_Cierre, Hora_Final.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Venta
 */
class Caja
{

    const TABLE = "Caja";
    // ID_Caja, Fecha, Hora, Monto_Inicial, Monto_Final, Monto_Final_Sistema, Estado, Estado_Final, ID_Usuario, ID_Usuario_Cierre, Hora_Final

    /**
     * Abre (crea) una caja.
     *
     * @param string $Fecha         Fecha de apertura (AAAA-MM-DD)
     * @param string $Hora          Hora de apertura (HH:MM:SS)
     * @param float  $Monto_Inicial Efectivo con el que se abre
     * @param string $Estado        Estado inicial ("abierta")
     * @param int    $ID_Usuario    Usuario de la sesión que la abre
     * @return string ID de la caja creada
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Cierra la caja y guarda el corte. Se llama dentro de la transacción de la ruta closeCashRegister.
     *
     * @param PDO    $connection          Conexión con la transacción abierta
     * @param int    $ID_Caja             Caja que se cierra
     * @param float  $Monto_Final         Efectivo que contó el cajero
     * @param float  $Monto_Final_Sistema Efectivo esperado: monto inicial + ventas en efectivo
     * @param string $Estado_Final        "cuadrada" o "descuadrada"
     * @param string $Hora_Final          Hora del corte (HH:MM:SS)
     * @param int    $ID_Usuario_Cierre   Usuario de la sesión que hace el corte
     * @return int 1 si se cerró la caja, 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Totales de las ventas finalizadas de una caja, por forma de pago.
     * El cuadre de caja solo cuenta el efectivo.
     *
     * @param int      $ID_Caja    Caja
     * @param PDO|null $connection Conexión de la transacción en curso, o null para abrir una
     * @return array ['efectivo', 'tarjeta', 'transferencia', 'total' => float, 'ventas' => int]
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Caja abierta en este momento.
     *
     * @return array|false ID_Caja, Fecha, Hora, Monto_Inicial e ID_Usuario, o false si no hay caja abierta
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Caja abierta, o error si no hay (regla 2: sin caja abierta no hay ventas).
     *
     * @return array La caja abierta
     * @throws ErrorApi 409 "No hay caja abierta"
     * @see Caja::cajaAbierta()
     */
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


    /**
     * Cajas abiertas en un rango de fechas, la más reciente primero, con quién abrió y cerró,
     * su diferencia, el número de ventas y su total.
     *
     * @param string $Desde Fecha inicial (AAAA-MM-DD)
     * @param string $Hasta Fecha final (AAAA-MM-DD)
     * @return array Lista de cajas
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Una caja del historial con los mismos datos que readHistorial().
     *
     * @param int $ID_Caja Caja
     * @return array|false La caja, o false si no existe
     * @throws Exception Si falla la consulta a la base de datos
     * @see Caja::readHistorial()
     */
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


    /**
     * Último corte: la caja cerrada más reciente.
     *
     * @return array|false La caja con el nombre de quien la abrió, o false si no hay cortes
     * @throws Exception Si falla la consulta a la base de datos
     */
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
