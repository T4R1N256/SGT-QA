<?php

require_once __DIR__ . "/../config/database.php";

/**
 * Surtidos: compras a proveedores que reabastecen el inventario (RF05).
 * Un surtido tiene un proveedor y uno o más productos con su cantidad y su costo de compra por unidad.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Producto::sumarInventario()
 */
class Surtir
{
    const TABLE = 'Surtir';
    // ID_Surtir, Fecha, Hora, Monto, ID_Proveedor, ID_Usuario, Notas

    /**
     * Crea el surtido. Se llama dentro de la transacción de la ruta createRestock.
     *
     * @param PDO         $connection   Conexión con la transacción abierta
     * @param string      $Fecha        Fecha del surtido (AAAA-MM-DD)
     * @param string      $Hora         Hora del surtido (HH:MM:SS)
     * @param float       $Monto        Total: suma de cantidad × costo de cada producto
     * @param int         $ID_Proveedor Proveedor
     * @param int         $ID_Usuario   Usuario de la sesión que lo registra
     * @param string|null $Notas        Notas opcionales (por ejemplo, el número de factura)
     * @return int ID del surtido creado
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Agrega un producto al surtido. Se llama dentro de la transacción de la ruta.
     *
     * @param PDO   $connection  Conexión con la transacción abierta
     * @param int   $ID_Surtir   Surtido
     * @param int   $ID_Producto Producto
     * @param int   $Cantidad    Piezas compradas
     * @param float $Costo       Costo de compra por unidad
     * @return void
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Surtidos de un rango de fechas, el más reciente primero, con su proveedor, quién lo registró,
     * el número de productos y las unidades.
     *
     * @param string $Desde Fecha inicial (AAAA-MM-DD)
     * @param string $Hasta Fecha final (AAAA-MM-DD)
     * @return array Lista de surtidos
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Un surtido con sus productos, su costo y su importe.
     *
     * @param int $ID_Surtir Surtido
     * @return array|false El surtido con la llave 'productos', o false si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
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
