<?php
/**
 * Datos de demostración para desarrollo y QA.
 * Se cargan desde seeder.php solo si SEED_DEMO=1 y la tabla Producto está vacía.
 * Incluye productos en los tres estados (Disponible, Stock bajo, Agotado) y un usuario empleado.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 */


require_once __DIR__ . '/../models/producto.php';


/**
 * Carga las categorías, proveedores y productos de demostración y el usuario empleado.
 *
 * @param PDO $connection Conexión a la base de datos
 * @return bool false si ya había productos y no se cargó nada
 */
function cargarDatosDemo(PDO $connection): bool
{
    $hayProductos = $connection->query('SELECT COUNT(*) FROM Producto')->fetchColumn();
    if ($hayProductos > 0) {
        return false;
    }

    $categorias = ['Abarrotes', 'Bebidas', 'Lácteos y huevo', 'Botanas y dulces', 'Panadería', 'Limpieza', 'Higiene personal'];
    $proveedores = [
        ['Distribuidora La Central', '6561000001'],
        ['Refrescos del Norte', '6561000002'],
        ['Lácteos del Valle', '6561000003'],
        ['Botanas Juárez', '6561000004'],
        ['Panificadora San José', '6561000005'],
        ['Limpieza Total', '6561000006'],
    ];

    // Nombre, precio, cantidad, unidad, cantidad mínima, categoría, proveedor (índices de los arreglos de arriba)
    $productos = [
        ['Arroz 1 kg', 32.50, 40, 'pieza', 10, 0, 0],
        ['Frijol negro 1 kg', 38.00, 25, 'pieza', 10, 0, 0],
        ['Aceite vegetal 1 L', 45.90, 18, 'pieza', 6, 0, 0],
        ['Azúcar estándar 1 kg', 34.00, 30, 'pieza', 10, 0, 0],
        ['Sal de mesa 1 kg', 14.50, 12, 'pieza', 5, 0, 0],
        ['Harina de maíz 1 kg', 24.90, 8, 'pieza', 10, 0, 0],
        ['Atún en agua 140 g', 21.50, 36, 'pieza', 12, 0, 0],
        ['Pasta para sopa 200 g', 11.00, 0, 'pieza', 15, 0, 0],
        ['Café soluble 100 g', 79.90, 9, 'pieza', 4, 0, 0],
        ['Frijol a granel', 36.00, 15, 'kg', 5, 0, 0],
        ['Refresco de cola 600 ml', 19.00, 48, 'pieza', 24, 1, 1],
        ['Refresco de naranja 600 ml', 18.00, 20, 'pieza', 24, 1, 1],
        ['Agua natural 1 L', 13.50, 36, 'pieza', 12, 1, 1],
        ['Jugo de manzana 1 L', 29.90, 0, 'pieza', 6, 1, 1],
        ['Leche entera 1 L', 28.50, 24, 'litro', 12, 2, 2],
        ['Leche deslactosada 1 L', 30.50, 6, 'litro', 12, 2, 2],
        ['Yogur bebible 250 ml', 12.00, 20, 'pieza', 8, 2, 2],
        ['Queso fresco', 120.00, 4, 'kg', 2, 2, 2],
        ['Huevo blanco 12 piezas', 42.00, 15, 'pieza', 5, 2, 2],
        ['Papas fritas 45 g', 18.50, 30, 'pieza', 10, 3, 3],
        ['Cacahuates enchilados 100 g', 16.00, 3, 'pieza', 6, 3, 3],
        ['Galletas de chocolate 120 g', 22.00, 25, 'pieza', 8, 3, 3],
        ['Chicles menta 10 piezas', 9.50, 0, 'pieza', 10, 3, 3],
        ['Pan blanco grande', 48.00, 10, 'pieza', 4, 4, 4],
        ['Bolillo', 3.50, 60, 'pieza', 20, 4, 4],
        ['Tortillas de harina 10 piezas', 27.00, 5, 'pieza', 6, 4, 4],
        ['Detergente en polvo 1 kg', 39.90, 14, 'pieza', 5, 5, 5],
        ['Jabón para trastes 750 ml', 32.00, 9, 'pieza', 4, 5, 5],
        ['Cloro 1 L', 17.50, 2, 'litro', 4, 5, 5],
        ['Papel higiénico 4 rollos', 38.50, 20, 'pieza', 8, 6, 5],
        ['Jabón de tocador 150 g', 19.90, 16, 'pieza', 6, 6, 5],
        ['Pasta dental 100 ml', 34.90, 0, 'pieza', 4, 6, 5],
    ];

    $connection->beginTransaction();
    try {
        $idsCategoria = [];
        $sql = $connection->prepare('INSERT INTO Categoria (Nombre_Categoria, Estado) VALUES (:Nombre, :Estado)');
        foreach ($categorias as $nombre) {
            $sql->execute([':Nombre' => $nombre, ':Estado' => 'activo']);
            $idsCategoria[] = (int) $connection->lastInsertId();
        }

        $idsProveedor = [];
        $sql = $connection->prepare('INSERT INTO Proveedor (Nombre_Proveedor, Telefono, Estado) VALUES (:Nombre, :Telefono, :Estado)');
        foreach ($proveedores as [$nombre, $telefono]) {
            $sql->execute([':Nombre' => $nombre, ':Telefono' => $telefono, ':Estado' => 'activo']);
            $idsProveedor[] = (int) $connection->lastInsertId();
        }

        $sql = $connection->prepare('INSERT INTO Producto (Nombre_Producto, Precio, Cantidad, Unidad_Medida, Estado,
            Cantidad_Minima, ID_Categoria, ID_Proveedor) VALUES (:Nombre, :Precio, :Cantidad, :Unidad, :Estado,
            :Minima, :Categoria, :Proveedor)');
        foreach ($productos as [$nombre, $precio, $cantidad, $unidad, $minima, $categoria, $proveedor]) {
            $sql->execute([
                ':Nombre' => $nombre,
                ':Precio' => $precio,
                ':Cantidad' => $cantidad,
                ':Unidad' => $unidad,
                ':Estado' => Producto::calcularEstado($cantidad, $minima),
                ':Minima' => $minima,
                ':Categoria' => $idsCategoria[$categoria],
                ':Proveedor' => $idsProveedor[$proveedor],
            ]);
        }

        // Usuario empleado para probar permisos por rol
        $sql = $connection->prepare('INSERT INTO Usuario (Password, Estado) VALUES (:Password, :Estado)');
        $sql->execute([':Password' => password_hash('empleado123', PASSWORD_DEFAULT), ':Estado' => 'autorizado']);
        $ID_Usuario = (int) $connection->lastInsertId();

        $sql = $connection->prepare('INSERT INTO Contacto_Empleado (Telefono, Correo, Calle, Colonia, Codigo_Postal)
            VALUES (:Telefono, :Correo, :Calle, :Colonia, :Codigo_Postal)');
        $sql->execute([
            ':Telefono' => '6561111111',
            ':Correo' => 'empleado.demo@sgt.local',
            ':Calle' => 'Calle Demo 123',
            ':Colonia' => 'Centro',
            ':Codigo_Postal' => '32000',
        ]);
        $ID_Contacto = (int) $connection->lastInsertId();

        $sql = $connection->prepare('INSERT INTO Empleado (Nombre, Puesto, Estado, ID_Contacto_Empleado, ID_Usuario)
            VALUES (:Nombre, :Puesto, :Estado, :ID_Contacto, :ID_Usuario)');
        $sql->execute([
            ':Nombre' => 'Empleado Demo',
            ':Puesto' => 'empleado',
            ':Estado' => 'activo',
            ':ID_Contacto' => $ID_Contacto,
            ':ID_Usuario' => $ID_Usuario,
        ]);

        $connection->commit();
        echo "Datos de demostración cargados. Empleado demo: ID $ID_Usuario, contraseña empleado123\n\n";
        return true;

    } catch (Exception $e) {
        $connection->rollBack();
        throw $e;
    }
}//-- Fin funcion cargar datos demo
