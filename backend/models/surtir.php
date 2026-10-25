<?php
// Clase para surtidos (compras a proveedores que reabastecen el inventario, RF05)
// Un surtido tiene un proveedor y uno o más productos con su cantidad y su costo de compra por unidad

require_once __DIR__ . "/../config/database.php";

class Surtir
{
    const TABLE = 'Surtir';
    // ID_Surtir, Fecha, Hora, Monto, ID_Proveedor, ID_Usuario, Notas

    // Funcion para crear el surtido (dentro de la transacción de la ruta)
    public static function crearSurtido($connection, $Fecha, $Hora, $Monto, $ID_Proveedor, $ID_Usuario, $Notas)
    {
        try {
            $sql = $connection->prepare('INSERT INTO ' . self::TABLE . ' (Fecha, Hora, Monto, ID_Proveedor, ID_Usuario, Notas)
            VALUES (:Fecha, :Hora, :Monto, :ID_Proveedor, :ID_Usuario, :Notas)');
            $sql->bindValue(':Fecha', $Fecha);
            $sql->bindValue(':Hora', $Hora);
            $sql->bindValue(':Monto', $Monto);
            $sql->bindValue(':ID_Proveedor', $ID_Proveedor, PDO::PARAM_INT);
            $sql->bindValue(':ID_Usuario', $ID_Usuario, PDO::PARAM_INT);
            $sql->bindValue(':Notas', $Notas);
            $sql->execute();

            return (int) $connection->lastInsertId();

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion crear surtido


    // Funcion para agregar un producto al surtido (dentro de la transacción de la ruta)
    public static function addProductoSurtido($connection, $ID_Surtir, $ID_Producto, $Cantidad, $Costo)
    {
        try {
            $sql = $connection->prepare('INSERT INTO Detalle_Surtir (Cantidad, Precio_Unitario, ID_Surtir, ID_Producto)
            VALUES (:Cantidad, :Precio_Unitario, :ID_Surtir, :ID_Producto)');
            $sql->bindValue(':Cantidad', $Cantidad, PDO::PARAM_INT);
            $sql->bindValue(':Precio_Unitario', $Costo);
            $sql->bindValue(':ID_Surtir', $ID_Surtir, PDO::PARAM_INT);
            $sql->bindValue(':ID_Producto', $ID_Producto, PDO::PARAM_INT);
            $sql->execute();

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion agregar producto al surtido


    // Funcion para leer los surtidos de un rango de fechas (el más reciente primero)
    public static function readAllSurtidos($Desde, $Hasta)
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT s.ID_Surtir, s.Fecha, s.Hora, s.Monto, s.Notas, s.ID_Proveedor,
            p.Nombre_Proveedor, e.Nombre AS Registro,
            (SELECT COUNT(*) FROM Detalle_Surtir d WHERE d.ID_Surtir = s.ID_Surtir) AS Productos,
            (SELECT COALESCE(SUM(d.Cantidad), 0) FROM Detalle_Surtir d WHERE d.ID_Surtir = s.ID_Surtir) AS Unidades
            FROM ' . self::TABLE . ' s
            LEFT JOIN Proveedor p ON p.ID_Proveedor = s.ID_Proveedor
            LEFT JOIN Empleado e ON e.ID_Usuario = s.ID_Usuario
            WHERE s.Fecha BETWEEN :Desde AND :Hasta
            ORDER BY s.Fecha DESC, s.Hora DESC, s.ID_Surtir DESC');
            $sql->bindValue(':Desde', $Desde);
            $sql->bindValue(':Hasta', $Hasta);
            $sql->execute();
            $surtidos = $sql->fetchAll();
            $connection = NULL;

            return $surtidos;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion leer surtidos


    // Funcion para leer un surtido con sus productos
    public static function readSurtido($ID_Surtir)
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT s.ID_Surtir, s.Fecha, s.Hora, s.Monto, s.Notas, s.ID_Proveedor,
            p.Nombre_Proveedor, e.Nombre AS Registro
            FROM ' . self::TABLE . ' s
            LEFT JOIN Proveedor p ON p.ID_Proveedor = s.ID_Proveedor
            LEFT JOIN Empleado e ON e.ID_Usuario = s.ID_Usuario
            WHERE s.ID_Surtir = :ID_Surtir');
            $sql->bindValue(':ID_Surtir', $ID_Surtir, PDO::PARAM_INT);
            $sql->execute();
            $surtido = $sql->fetch();

            if (!$surtido) {
                return false;
            }

            $sql = $connection->prepare('SELECT d.ID_Producto, pr.Nombre_Producto, pr.Unidad_Medida, d.Cantidad,
            d.Precio_Unitario AS Costo, d.Cantidad * d.Precio_Unitario AS Importe
            FROM Detalle_Surtir d INNER JOIN Producto pr ON pr.ID_Producto = d.ID_Producto
            WHERE d.ID_Surtir = :ID_Surtir ORDER BY d.ID_Detalle_Surtir');
            $sql->bindValue(':ID_Surtir', $ID_Surtir, PDO::PARAM_INT);
            $sql->execute();
            $surtido['productos'] = $sql->fetchAll();
            $connection = NULL;

            return $surtido;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion leer surtido
}//-- Fin clase Surtir
