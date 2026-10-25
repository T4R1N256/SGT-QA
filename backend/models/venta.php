<?php

// Clase para venta 

require_once __DIR__ . '/../config/database.php';

class Venta
{
    const TABLE = 'Venta';


    // Funcion para crear venta en proceso
    // Fecha y Hora definitivas se guardan al completarla (regla 9)
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


    // Funcion para saber si una venta sigue en proceso dentro de una caja (bloquea la fila hasta el commit)
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


    // Funcion para cancelar las ventas que quedaron en proceso en una caja (se abandonaron sin cobrar)
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


    // Funcion para agregar producto a detalle
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
/*
        // Funcion para quitar producto del detalle
        public static function removeProductoVenta($ID_Detalle_Venta)
        {
            try {
                $connection = new Conexion();
                $sql = $connection->prepare('DELETE FROM Detalle_Venta WHERE ID_Detalle_Venta = :ID_Detalle_Venta');
                $sql->bindValue(':ID_Detalle_Venta', $ID_Detalle_Venta, PDO::PARAM_INT);
                $sql->execute();
                return $sql->rowCount() > 0 ? 1 : 0;
            } catch (PDOException $e) {
                throw new Exception("Hubo un error: " . $e->getMessage());
            }
        }//-- Fin funcion quitar producto de detalle
    */

    // Condiciones del historial de ventas: rango de fechas, estados y, opcionalmente, forma de pago
    // Regresa [WHERE, parámetros] para readHistorial y resumenHistorial
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


    // Funcion para leer una página del historial de ventas (la más reciente primero)
    // El cajero de una venta cancelada es quien abrió la caja (la venta no llegó a cobrarse)
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


    // Funcion para el resumen del historial con los mismos filtros: número de ventas y total cobrado
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


    // Funcion para contar las ventas canceladas de una caja
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


    // Funcion para obtener los datos de una venta finalizada para su ticket (regla 8)
    // Regresa false si la venta no existe o no está finalizada
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


    // Funcion para obtener detalles de una venta
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

    // Funcion para completar venta (dentro de la transacción de la ruta)
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


    // Funcion para cancelar venta
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