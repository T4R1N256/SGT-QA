<?php

require_once __DIR__ . '/../config/database.php';

/**
 * Ventas de SGT: se crean "en proceso" al agregar el primer producto, se completan al cobrar
 * (o se cancelan) y se consultan para el ticket y el historial.
 * Estados: enproceso, finalizada, cancelada.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Caja
 */
class Venta
{
    const TABLE = 'Venta';


    /**
     * Crea una venta en proceso en la caja. La fecha y la hora definitivas se guardan al completarla (regla 9).
     *
     * @param PDO $connection Conexión con la transacción abierta
     * @param int $ID_Caja    Caja abierta
     * @return string ID de la venta creada
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function openVenta($connection, $ID_Caja)
    {

        try {
            $Estado = 'enproceso';

            $sql = $connection->prepare('INSERT INTO ' . self::TABLE . ' (Fecha, Hora, Monto, Estado, ID_Caja) 
            VALUES(:Fecha, :Hora, :Monto, :Estado, :ID_Caja)');
            $sql->bindValue(':Fecha', date('Y-m-d'), PDO::PARAM_STR);
            $sql->bindValue(':Hora', date('H:i:s'), PDO::PARAM_STR);
            $sql->bindValue(':Monto', '0', PDO::PARAM_STR);
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $sql->bindValue(':ID_Caja', $ID_Caja, PDO::PARAM_INT);
            $sql->execute();
            return $connection->lastInsertId();

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion crear venta


    /**
     * Indica si una venta sigue en proceso dentro de una caja. Bloquea la fila hasta el commit (FOR UPDATE).
     *
     * @param PDO $connection Conexión con la transacción abierta
     * @param int $ID_Venta   Venta
     * @param int $ID_Caja    Caja abierta
     * @return bool true si la venta existe, es de esa caja y sigue en proceso
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function ventaEnProceso($connection, $ID_Venta, $ID_Caja)
    {
        try {
            $sql = $connection->prepare('SELECT ID_Venta FROM ' . self::TABLE . '
            WHERE ID_Venta = :ID_Venta AND ID_Caja = :ID_Caja AND Estado = :Estado FOR UPDATE');
            $sql->bindValue(':ID_Venta', $ID_Venta, PDO::PARAM_INT);
            $sql->bindValue(':ID_Caja', $ID_Caja, PDO::PARAM_INT);
            $sql->bindValue(':Estado', 'enproceso', PDO::PARAM_STR);
            $sql->execute();
            return (bool) $sql->fetch();
        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion venta en proceso


    /**
     * Cancela las ventas que quedaron en proceso en una caja (se abandonaron sin cobrar).
     *
     * @param PDO $connection Conexión con la transacción abierta
     * @param int $ID_Caja    Caja
     * @return int Número de ventas canceladas
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function cancelarEnProceso($connection, $ID_Caja)
    {
        try {
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Estado = :Cancelada
            WHERE ID_Caja = :ID_Caja AND Estado = :EnProceso');
            $sql->bindValue(':Cancelada', 'cancelada', PDO::PARAM_STR);
            $sql->bindValue(':EnProceso', 'enproceso', PDO::PARAM_STR);
            $sql->bindValue(':ID_Caja', $ID_Caja, PDO::PARAM_INT);
            $sql->execute();
            return $sql->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion cancelar ventas en proceso


    /**
     * Agrega un producto al detalle de la venta con su precio de ese momento.
     *
     * @param PDO   $connection      Conexión con la transacción abierta
     * @param int   $ID_Venta        Venta
     * @param int   $ID_Producto     Producto
     * @param int   $Cantidad        Piezas
     * @param float $Precio_Unitario Precio del producto en la base de datos
     * @return string ID del renglón de detalle
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function addProductoVenta($connection, $ID_Venta, $ID_Producto, $Cantidad, $Precio_Unitario)
    {
        try {
            $sql = $connection->prepare('INSERT INTO Detalle_Venta (Cantidad, Precio_Unitario, ID_Venta, ID_Producto)
            VALUES (:Cantidad, :Precio_Unitario, :ID_Venta, :ID_Producto)');
            $sql->bindValue(':Cantidad', $Cantidad, PDO::PARAM_INT);
            $sql->bindValue(':Precio_Unitario', $Precio_Unitario);
            $sql->bindValue(':ID_Venta', $ID_Venta, PDO::PARAM_INT);
            $sql->bindValue(':ID_Producto', $ID_Producto, PDO::PARAM_INT);
            $sql->execute();
            return $connection->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion agregar producto a detalle


    /**
     * Condiciones del historial de ventas: rango de fechas, estados y, opcionalmente, forma de pago.
     *
     * @param array $filtros ['desde', 'hasta', 'estados' => [...], 'forma_pago' => opcional]
     * @return array [WHERE, parámetros] para readHistorial() y resumenHistorial()
     */
    private static function filtroHistorial(array $filtros): array
    {
        $condiciones = ['v.Fecha BETWEEN :Desde AND :Hasta'];
        $parametros = [':Desde' => $filtros['desde'], ':Hasta' => $filtros['hasta']];

        $estados = [];
        foreach ($filtros['estados'] as $i => $estado) {
            $estados[] = ":Estado$i";
            $parametros[":Estado$i"] = $estado;
        }
        $condiciones[] = 'v.Estado IN (' . implode(', ', $estados) . ')';

        if (!empty($filtros['forma_pago'])) {
            $condiciones[] = 'v.Forma_Pago = :Forma_Pago';
            $parametros[':Forma_Pago'] = $filtros['forma_pago'];
        }

        return ['WHERE ' . implode(' AND ', $condiciones), $parametros];
    }


    /**
     * Una página del historial de ventas, la más reciente primero.
     * El cajero de una venta cancelada es quien abrió la caja (la venta no llegó a cobrarse).
     *
     * @param array $filtros        Filtros del historial (ver filtroHistorial())
     * @param int   $limite         Ventas por página
     * @param int   $desplazamiento Ventas que se saltan (páginas anteriores)
     * @return array Ventas con su cajero y número de artículos
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function readHistorial(array $filtros, int $limite, int $desplazamiento)
    {
        try {
            [$where, $parametros] = self::filtroHistorial($filtros);
            $connection = new Conexion();

            $sql = $connection->prepare("SELECT v.ID_Venta, v.Fecha, v.Hora, v.Monto, v.Forma_Pago, v.Estado, v.ID_Caja,
            e.Nombre AS Cajero,
            (SELECT COALESCE(SUM(d.Cantidad), 0) FROM Detalle_Venta d WHERE d.ID_Venta = v.ID_Venta) AS Articulos
            FROM " . self::TABLE . " v
            LEFT JOIN Caja c ON c.ID_Caja = v.ID_Caja
            LEFT JOIN Empleado e ON e.ID_Usuario = COALESCE(v.ID_Usuario, c.ID_Usuario)
            $where
            ORDER BY v.Fecha DESC, v.Hora DESC, v.ID_Venta DESC
            LIMIT :Limite OFFSET :Desplazamiento");
            foreach ($parametros as $nombre => $valor) {
                $sql->bindValue($nombre, $valor);
            }
            $sql->bindValue(':Limite', $limite, PDO::PARAM_INT);
            $sql->bindValue(':Desplazamiento', $desplazamiento, PDO::PARAM_INT);
            $sql->execute();
            $ventas = $sql->fetchAll();
            $connection = NULL;

            return $ventas;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion leer historial


    /**
     * Resumen del historial con los mismos filtros: número de ventas y total cobrado.
     *
     * @param array $filtros Filtros del historial (ver filtroHistorial())
     * @return array ['ventas' => int, 'total' => float]
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function resumenHistorial(array $filtros)
    {
        try {
            [$where, $parametros] = self::filtroHistorial($filtros);
            $connection = new Conexion();

            $sql = $connection->prepare("SELECT COUNT(*) AS Ventas,
            COALESCE(SUM(CASE WHEN v.Estado = 'finalizada' THEN v.Monto END), 0) AS Total
            FROM " . self::TABLE . " v $where");
            $sql->execute($parametros);
            $resumen = $sql->fetch();
            $connection = NULL;

            return ['ventas' => (int) $resumen['Ventas'], 'total' => (float) $resumen['Total']];

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion resumen historial


    /**
     * Cuenta las ventas canceladas de una caja.
     *
     * @param int $ID_Caja Caja
     * @return int Número de ventas canceladas
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function contarCanceladas($ID_Caja)
    {
        try {
            $connection = new Conexion();
            $sql = $connection->prepare("SELECT COUNT(*) FROM " . self::TABLE . "
            WHERE ID_Caja = :ID_Caja AND Estado = 'cancelada'");
            $sql->bindValue(':ID_Caja', $ID_Caja, PDO::PARAM_INT);
            $sql->execute();
            return (int) $sql->fetchColumn();
        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion contar canceladas


    /**
     * Datos de una venta finalizada para su ticket (regla 8): folio, fecha, montos, pago, cambio,
     * forma de pago, cajero y productos con su importe.
     *
     * @param int $ID_Venta Venta
     * @return array|false La venta con sus productos, o false si no existe o no está finalizada
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function readTicket($ID_Venta)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('SELECT v.ID_Venta, v.Fecha, v.Hora, v.Monto, v.Pago_Recibido, v.Cambio,
            v.Forma_Pago, v.ID_Caja, e.Nombre AS Cajero FROM ' . self::TABLE . ' v
            LEFT JOIN Empleado e ON e.ID_Usuario = v.ID_Usuario
            WHERE v.ID_Venta = :ID_Venta AND v.Estado = :Estado');
            $sql->bindValue(':ID_Venta', $ID_Venta, PDO::PARAM_INT);
            $sql->bindValue(':Estado', 'finalizada', PDO::PARAM_STR);
            $sql->execute();
            $venta = $sql->fetch();

            if (!$venta) {
                return false;
            }

            $sql = $connection->prepare('SELECT d.ID_Producto, p.Nombre_Producto, p.Unidad_Medida, d.Cantidad,
            d.Precio_Unitario, d.Cantidad * d.Precio_Unitario AS Importe
            FROM Detalle_Venta d INNER JOIN Producto p ON p.ID_Producto = d.ID_Producto
            WHERE d.ID_Venta = :ID_Venta ORDER BY d.ID_Detalle_Venta');
            $sql->bindValue(':ID_Venta', $ID_Venta, PDO::PARAM_INT);
            $sql->execute();
            $venta['productos'] = $sql->fetchAll();
            $connection = NULL;

            return $venta;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion leer ticket


    /**
     * Productos y cantidades de una venta.
     *
     * @param int $ID_Venta Venta
     * @return array Renglones con ID_Producto y Cantidad
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function getDetalleVenta($ID_Venta)
    {
        try {
            $connection = new Conexion();
            $sql = $connection->prepare('SELECT ID_Producto, Cantidad FROM Detalle_Venta
        WHERE ID_Venta = :ID_Venta');
            $sql->bindValue(':ID_Venta', $ID_Venta, PDO::PARAM_INT);
            $sql->execute();
            return $sql->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion obtener detalle venta

    /**
     * Completa la venta al cobrar. Se llama dentro de la transacción de la ruta completeSale, que ya validó
     * existencias y montos con los precios de la base de datos.
     *
     * @param PDO    $connection    Conexión con la transacción abierta
     * @param int    $ID_Venta      Venta en proceso
     * @param string $Fecha         Fecha del cobro (regla 9)
     * @param string $Hora          Hora del cobro (regla 9)
     * @param float  $Monto         Total de la venta
     * @param float  $Pago_Recibido Pago del cliente (en efectivo; con tarjeta o transferencia, el total)
     * @param float  $Cambio        Cambio entregado
     * @param string $Forma_Pago    efectivo, tarjeta o transferencia
     * @param int    $ID_Usuario    Cajero que cobra
     * @return int 1 si se completó, 0 si la venta no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function completeVenta($connection, $ID_Venta, $Fecha, $Hora, $Monto, $Pago_Recibido, $Cambio, $Forma_Pago, $ID_Usuario)
    {
        try {
            $Estado = 'finalizada';

            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Fecha = :Fecha, Hora = :Hora,
            Monto = :Monto, Pago_Recibido = :Pago_Recibido, Cambio = :Cambio, Estado = :Estado,
            Forma_Pago = :Forma_Pago, ID_Usuario = :ID_Usuario
            WHERE ID_Venta = :ID_Venta');
            $sql->bindValue(':Fecha', $Fecha, PDO::PARAM_STR);
            $sql->bindValue(':Hora', $Hora, PDO::PARAM_STR);
            $sql->bindValue(':Monto', $Monto, PDO::PARAM_STR);
            $sql->bindValue(':Pago_Recibido', $Pago_Recibido, PDO::PARAM_STR);
            $sql->bindValue(':Cambio', $Cambio, PDO::PARAM_STR);
            $sql->bindValue(':ID_Usuario', $ID_Usuario, PDO::PARAM_INT);
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $sql->bindValue(':Forma_Pago', $Forma_Pago, PDO::PARAM_STR);
            $sql->bindValue(':ID_Venta', $ID_Venta, PDO::PARAM_INT);
            $sql->execute();

            $row = $sql->rowCount();

            return $row > 0 ? 1 : 0;


        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion para completar venta


    /**
     * Cancela una venta que sigue en proceso (vaciar el carrito).
     *
     * @param int $ID_Venta Venta
     * @return int 1 si se canceló, 0 si no existe o ya no estaba en proceso
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function cancelVenta($ID_Venta)
    {
        try {
            $connection = new Conexion();
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Estado = :Estado
        WHERE ID_Venta = :ID_Venta AND Estado = :EnProceso');
            $sql->bindValue(':Estado', 'cancelada', PDO::PARAM_STR);
            $sql->bindValue(':EnProceso', 'enproceso', PDO::PARAM_STR);
            $sql->bindValue(':ID_Venta', $ID_Venta, PDO::PARAM_INT);
            $sql->execute();
            return $sql->rowCount() > 0 ? 1 : 0;
        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- fin funcion cancelar venta


    /**
     * Ventas finalizadas de una caja, en orden de folio.
     *
     * @param int $ID_Caja Caja
     * @return array Ventas con folio, fecha, hora, monto y forma de pago
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function readAllVentas($ID_Caja)
    {
        try {
            $Estado = 'finalizada';
            $connection = new Conexion();
            $sql = $connection->prepare('SELECT ID_Venta, Fecha, Hora, Monto, Forma_Pago FROM ' . self::TABLE . '
        WHERE ID_Caja = :ID_Caja AND Estado = :Estado ORDER BY ID_Venta');
            $sql->bindValue(':ID_Caja', $ID_Caja, PDO::PARAM_INT);
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $sql->execute();
            $ventas = $sql->fetchAll();
            $connection = NULL;

            return $ventas;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }
}