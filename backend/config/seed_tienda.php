<?php
// Catálogo de prueba de la tienda en línea (catalogo_tienda.php)
// Se carga desde seeder.php solo si SEED_TIENDA=1 y la tabla Producto está vacía (por ejemplo, con Docker)

require_once __DIR__ . '/../models/producto.php';

// Correo y contraseña del cliente de prueba (README, sección "Tienda en línea")
const CLIENTE_PRUEBA = ['cliente@ejemplo.mx', 'prueba123'];


// Funcion para cargar el catálogo de la tienda
// Regresa false si ya había productos y no se cargó nada
function cargarCatalogoTienda(PDO $connection): bool
{
    $hayProductos = $connection->query('SELECT COUNT(*) FROM Producto')->fetchColumn();
    if ($hayProductos > 0) {
        return false;
    }

    $catalogo = require __DIR__ . '/catalogo_tienda.php';

    $connection->beginTransaction();
    try {
        $idsProveedor = [];
        $sql = $connection->prepare('INSERT INTO Proveedor (Nombre_Proveedor, Telefono, Estado) VALUES (:Nombre, :Telefono, :Estado)');
        foreach ($catalogo['proveedores'] as $clave => [$nombre, $telefono]) {
            $sql->execute([':Nombre' => $nombre, ':Telefono' => $telefono, ':Estado' => 'activo']);
            $idsProveedor[$clave] = (int) $connection->lastInsertId();
        }

        $categoria = $connection->prepare('INSERT INTO Categoria (Nombre_Categoria, Estado) VALUES (:Nombre, :Estado)');
        $producto = $connection->prepare('INSERT INTO Producto (Nombre_Producto, Descripcion, Precio, Cantidad, Unidad_Medida,
            Estado, Cantidad_Minima, ID_Categoria, ID_Proveedor) VALUES (:Nombre, :Descripcion, :Precio, :Cantidad, :Unidad,
            :Estado, :Minima, :Categoria, :Proveedor)');

        $total = 0;
        foreach ($catalogo['categorias'] as $nombreCategoria => [$proveedor, $productos]) {
            $categoria->execute([':Nombre' => $nombreCategoria, ':Estado' => 'activo']);
            $ID_Categoria = (int) $connection->lastInsertId();

            foreach ($productos as [$nombre, $precio, $cantidad, $unidad, $minima, $descripcion]) {
                $producto->execute([
                    ':Nombre' => $nombre,
                    ':Descripcion' => $descripcion,
                    ':Precio' => $precio,
                    ':Cantidad' => $cantidad,
                    ':Unidad' => $unidad,
                    ':Estado' => Producto::calcularEstado($cantidad, $minima),
                    ':Minima' => $minima,
                    ':Categoria' => $ID_Categoria,
                    ':Proveedor' => $idsProveedor[$proveedor],
                ]);
                $total++;
            }
        }

        // Cliente de prueba para recorrer la compra sin registrarse (también se puede crear otra cuenta)
        $cliente = $connection->prepare('INSERT IGNORE INTO Cliente (Nombre, Apellidos, Correo, Telefono, Password)
            VALUES (:Nombre, :Apellidos, :Correo, :Telefono, :Password)');
        $cliente->execute([
            ':Nombre' => 'Cliente',
            ':Apellidos' => 'De Prueba',
            ':Correo' => CLIENTE_PRUEBA[0],
            ':Telefono' => '6560000000',
            ':Password' => password_hash(CLIENTE_PRUEBA[1], PASSWORD_DEFAULT),
        ]);

        $connection->commit();
        echo "Catálogo de la tienda cargado: " . count($catalogo['categorias']) . " categorías y $total productos\n";
        echo "Cliente de prueba de la tienda: " . CLIENTE_PRUEBA[0] . ' / ' . CLIENTE_PRUEBA[1] . "\n\n";
        return true;

    } catch (Exception $e) {
        $connection->rollBack();
        throw $e;
    }
}//-- Fin funcion cargar catálogo de la tienda
