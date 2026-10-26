<?php

require_once __DIR__ . "/../config/database.php";

/**
 * Catálogo de productos de SGT (RF04, RF06) y movimientos de inventario.
 * Los productos dados de baja quedan con Activo = 0: no se muestran ni se venden, pero sus ventas
 * pasadas los siguen referenciando.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Venta
 * @see Surtir
 */
class Producto
{
    const TABLE = 'Producto';
    // ID_Producto, Nombre_Producto, Precio, Cantidad, Unidad_Medida, Estado, Cantidad_Minima, ID_Categoria, ID_Proveedor
    // 1 = correcto
    // 0 = no hay registro o no se encontró 
    // -1 = hubo un error en la ejecución


    /**
     * Estado del producto según sus existencias y su cantidad mínima.
     *
     * @param int $Cantidad        Existencias
     * @param int $Cantidad_Minima Cantidad mínima
     * @return string "Disponible" si hay más que el mínimo, "Stock bajo" si hay pocas y "Agotado" si no hay
     */
    public static function calcularEstado(int $Cantidad, int $Cantidad_Minima): string
    {
        if ($Cantidad > $Cantidad_Minima) {
            return 'Disponible';
        }
        return $Cantidad > 0 ? 'Stock bajo' : 'Agotado';
    }//-- Fin funcion calcular estado


    /**
     * Registra un producto.
     *
     * @param string $Nombre_Producto Nombre
     * @param float  $Precio          Precio de venta
     * @param int    $Cantidad        Existencias iniciales
     * @param string $Unidad_Medida   Unidad de medida (pieza, kg, litro...)
     * @param string $Estado          Estado calculado con calcularEstado()
     * @param int    $Cantidad_Minima Cantidad mínima antes de marcar stock bajo
     * @param int    $ID_Categoria    Categoría
     * @param int    $ID_Proveedor    Proveedor
     * @param string $Descripcion     Descripción opcional (la muestra la tienda en línea)
     * @return string ID del producto creado
     * @throws Exception Si falla la consulta a la base de datos
     * @see Producto::calcularEstado()
     */
    public static function crearProducto(
        $Nombre_Producto,
        $Precio,
        $Cantidad,
        $Unidad_Medida,
        $Estado,
        $Cantidad_Minima,
        $ID_Categoria,
        $ID_Proveedor,
        $Descripcion = ''
    ) {

        try {
            $connection = new Conexion;

            $sql = $connection->prepare(
                'INSERT INTO ' . self::TABLE . ' (Nombre_Producto, Descripcion, Precio, Cantidad, Unidad_Medida, 
                Estado, Cantidad_Minima, ID_Categoria, ID_Proveedor) 
                VALUES (:Nombre_Producto, :Descripcion, :Precio, :Cantidad, :Unidad_Medida, 
                :Estado, :Cantidad_Minima, :ID_Categoria, :ID_Proveedor)'
            );
            $sql->bindValue(':Descripcion', $Descripcion, PDO::PARAM_STR);
            $sql->bindValue(':Nombre_Producto', $Nombre_Producto, PDO::PARAM_STR);
            $sql->bindValue(':Precio', $Precio, PDO::PARAM_STR);
            $sql->bindValue(':Cantidad', $Cantidad, PDO::PARAM_INT);
            $sql->bindValue(':Unidad_Medida', $Unidad_Medida, PDO::PARAM_STR);
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $sql->bindValue(':Cantidad_Minima', $Cantidad_Minima, PDO::PARAM_INT);
            $sql->bindValue(':ID_Categoria', $ID_Categoria, PDO::PARAM_INT);
            $sql->bindValue(':ID_Proveedor', $ID_Proveedor, PDO::PARAM_INT);
            $sql->execute();
            $ID_Producto = $connection->lastInsertId();
            $connection = NULL;

            return $ID_Producto;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion crear producto


    /**
     * Productos activos (los dados de baja no se muestran ni se venden), con su categoría,
     * su proveedor y su último costo de compra.
     *
     * @return array|false Los productos, o false si no hay
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function readAllProductos()
    {

        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT p.ID_Producto, p.Nombre_Producto, p.Descripcion, p.Precio, p.Costo, p.Cantidad, p.Unidad_Medida, p.Estado,
            p.Cantidad_Minima, c.Nombre_Categoria, d.ID_Proveedor, d.Nombre_Proveedor  FROM ' . self::TABLE . ' p 
            INNER JOIN Categoria c ON c.ID_Categoria = p.ID_Categoria
            INNER JOIN Proveedor d ON d.ID_Proveedor = p.ID_Proveedor
            WHERE p.Activo = 1');
            $sql->execute();
            $productos = $sql->fetchAll();
            $connection = NULL;

            if ($productos) {
                return $productos;
            } else {
                return false;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//--Fin funcion leer todos los productos 

    /**
     * Un producto activo con su categoría y su proveedor.
     *
     * @param int $ID_Producto Producto
     * @return array|false El producto, o false si no existe o está dado de baja
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function readProducto($ID_Producto)
    {

        try {
            $connection = new Conexion;

            $sql = $connection->prepare('SELECT p.ID_Producto, p.Nombre_Producto, p.Descripcion, p.Precio, p.Cantidad, p.Unidad_Medida, p.Estado,
            p.Cantidad_Minima, c.ID_Categoria, c.Nombre_Categoria, d.ID_Proveedor, d.Nombre_Proveedor  FROM ' . self::TABLE . ' p 
            INNER JOIN Categoria c ON c.ID_Categoria = p.ID_Categoria
            INNER JOIN Proveedor d ON d.ID_Proveedor = p.ID_Proveedor
            WHERE p.ID_Producto = :ID_Producto AND p.Activo = 1');
            $sql->bindValue(':ID_Producto', $ID_Producto, PDO::PARAM_INT);
            $sql->execute();
            $producto = $sql->fetch();
            $connection = NULL;

            if ($producto) {
                return $producto;
            } else {
                return false;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//--Fin funcion leer producto 

    /**
     * Actualiza los datos de un producto.
     *
     * @param int    $ID_Producto     Producto
     * @param string $Nombre_Producto Nombre
     * @param float  $Precio          Precio de venta
     * @param int    $Cantidad        Existencias
     * @param string $Unidad_Medida   Unidad de medida
     * @param string $Estado          Estado calculado con calcularEstado()
     * @param int    $Cantidad_Minima Cantidad mínima
     * @param int    $ID_Categoria    Categoría
     * @param int    $ID_Proveedor    Proveedor
     * @param string $Descripcion     Descripción opcional
     * @return int 1 si se actualizó, 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function updateProducto(
        $ID_Producto,
        $Nombre_Producto,
        $Precio,
        $Cantidad,
        $Unidad_Medida,
        $Estado,
        $Cantidad_Minima,
        $ID_Categoria,
        $ID_Proveedor,
        $Descripcion = ''
    ) {

        try {
            $connection = new Conexion;

            $sql = $connection->prepare(
                'UPDATE ' . self::TABLE . ' SET Nombre_Producto = :Nombre_Producto, Descripcion = :Descripcion, Precio = :Precio, 
                Cantidad = :Cantidad, Unidad_Medida = :Unidad_Medida, Estado = :Estado, 
                Cantidad_Minima = :Cantidad_Minima, ID_Categoria = :ID_Categoria, ID_Proveedor = :ID_Proveedor
                WHERE ID_Producto = :ID_Producto'
            );
            $sql->bindValue(':ID_Producto', $ID_Producto, PDO::PARAM_INT);
            $sql->bindValue(':Nombre_Producto', $Nombre_Producto, PDO::PARAM_STR);
            $sql->bindValue(':Descripcion', $Descripcion, PDO::PARAM_STR);
            $sql->bindValue(':Precio', $Precio, PDO::PARAM_STR);
            $sql->bindValue(':Cantidad', $Cantidad, PDO::PARAM_INT);
            $sql->bindValue(':Unidad_Medida', $Unidad_Medida, PDO::PARAM_STR);
            $sql->bindValue(':Estado', $Estado, PDO::PARAM_STR);
            $sql->bindValue(':Cantidad_Minima', $Cantidad_Minima, PDO::PARAM_INT);
            $sql->bindValue(':ID_Categoria', $ID_Categoria, PDO::PARAM_INT);
            $sql->bindValue(':ID_Proveedor', $ID_Proveedor, PDO::PARAM_INT);

            $sql->execute();
            $row = $sql->rowCount();
            $connection = NULL;

            return $row > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//--Fin funcion actualizar producto 


    /**
     * Lee y bloquea (FOR UPDATE) los productos de una venta o un surtido hasta el commit,
     * para que dos operaciones al mismo tiempo no vendan las mismas existencias.
     *
     * @param PDO   $connection Conexión con la transacción abierta
     * @param array $IDs        IDs de los productos
     * @return array Productos (ID, nombre, precio, cantidad, mínimo y Activo) indexados por ID_Producto
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function bloquearProductos($connection, array $IDs)
    {
        try {
            $marcadores = implode(', ', array_fill(0, count($IDs), '?'));
            $sql = $connection->prepare('SELECT ID_Producto, Nombre_Producto, Precio, Cantidad, Cantidad_Minima, Activo FROM '
                . self::TABLE . " WHERE ID_Producto IN ($marcadores) FOR UPDATE");
            $sql->execute(array_values($IDs));

            $productos = [];
            foreach ($sql->fetchAll() as $producto) {
                $productos[(int) $producto['ID_Producto']] = $producto;
            }
            return $productos;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion bloquear productos


    /**
     * Suma al inventario lo que llegó en un surtido, guarda su último costo de compra
     * y recalcula su estado.
     *
     * @param PDO   $connection  Conexión con la transacción abierta
     * @param int   $ID_Producto Producto
     * @param int   $Cantidad    Piezas que llegaron
     * @param float $Costo       Costo de compra por unidad
     * @return void
     * @throws Exception Si falla la consulta a la base de datos
     * @see Producto::actualizarEstado()
     */
    public static function sumarInventario($connection, $ID_Producto, $Cantidad, $Costo)
    {
        try {
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Cantidad = Cantidad + :Cantidad, Costo = :Costo
            WHERE ID_Producto = :ID_Producto');
            $sql->bindValue(':Cantidad', $Cantidad, PDO::PARAM_INT);
            $sql->bindValue(':Costo', $Costo);
            $sql->bindValue(':ID_Producto', $ID_Producto, PDO::PARAM_INT);
            $sql->execute();

            self::actualizarEstado($connection, $ID_Producto);

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion sumar inventario


    /**
     * Recalcula el estado de un producto según su existencia (Disponible, Stock bajo, Agotado),
     * con la misma regla que calcularEstado().
     *
     * @param PDO $connection  Conexión con la transacción abierta
     * @param int $ID_Producto Producto
     * @return void
     * @throws PDOException Si falla la consulta (la atrapa quien la llama)
     * @see Producto::calcularEstado()
     */
    public static function actualizarEstado($connection, $ID_Producto)
    {
        $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Estado =
        CASE
            WHEN Cantidad > Cantidad_Minima THEN "Disponible"
            WHEN Cantidad > 0 AND Cantidad <= Cantidad_Minima THEN "Stock bajo"
            ELSE "Agotado"
        END
        WHERE ID_Producto = :ID_Producto');
        $sql->bindValue(':ID_Producto', $ID_Producto, PDO::PARAM_INT);
        $sql->execute();
    }//-- Fin funcion actualizar estado


    /**
     * Descuenta del inventario lo vendido y recalcula el estado del producto.
     *
     * @param PDO $connection  Conexión con la transacción abierta
     * @param int $ID_Producto Producto
     * @param int $Cantidad    Piezas vendidas
     * @return void
     * @throws Exception Si falla la consulta a la base de datos
     * @see Producto::actualizarEstado()
     */
    public static function descontarInventario($connection, $ID_Producto, $Cantidad)
    {
        try {
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Cantidad = Cantidad - :Cantidad
        WHERE ID_Producto = :ID_Producto');
            $sql->bindValue(':Cantidad', $Cantidad, PDO::PARAM_INT);
            $sql->bindValue(':ID_Producto', $ID_Producto, PDO::PARAM_INT);
            $sql->execute();

            self::actualizarEstado($connection, $ID_Producto);

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion descontar inventario

    /**
     * Da de baja un producto (baja lógica, RF06). No se borra: sus ventas pasadas lo siguen referenciando.
     *
     * @param int $ID_Producto Producto
     * @return int 1 si se dio de baja, 0 si no existe o ya estaba dado de baja
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function desactivarProducto($ID_Producto)
    {
        try {
            $connection = new Conexion;

            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Activo = 0 WHERE ID_Producto = :ID_Producto AND Activo = 1');
            $sql->bindValue(':ID_Producto', $ID_Producto, PDO::PARAM_INT);
            $sql->execute();
            $row = $sql->rowCount();
            $connection = NULL;

            return $row > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion desactivar producto
}

