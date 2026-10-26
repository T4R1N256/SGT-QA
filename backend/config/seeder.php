<?php
/**
 * Se ejecuta en cada arranque (Docker y Electron): aplica las migraciones pendientes y crea el usuario
 * administrador por defecto (ID 1, contraseña passadmin). Con SEED_TIENDA=1 o SEED_DEMO=1 carga además
 * el catálogo de la tienda o los datos de demostración, si la tabla de productos está vacía.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see aplicarMigraciones()
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/migrate.php';

try {
    $nuevas = aplicarMigraciones(new Conexion, __DIR__ . '/migrations');
    echo $nuevas ? 'Migraciones aplicadas: ' . implode(', ', $nuevas) . "\n\n" : "No hay migraciones pendientes\n\n";
} catch (Exception $e) {
    echo "Error en migraciones: " . $e->getMessage() . "\n\n";
}

$connection = NULL;
try {
    $connection = new Conexion;

    $check = $connection->prepare('SELECT ID_Usuario FROM Usuario WHERE ID_Usuario = 1');
    $check->execute();

    if (!$check->fetch()) {
        $connection->beginTransaction();

        //Insertar información en tabla Usuario
        $sql = $connection->prepare('INSERT INTO Usuario (Password, Estado) VALUES (:Password, :Estado)');
        $sql->bindValue(':Password', password_hash('passadmin', PASSWORD_DEFAULT));
        $sql->bindValue(':Estado', 'autorizado');
        $sql->execute();
        $ID_Usuario = $connection->lastInsertId();


        //Insertar información en tabla Contacto_Empleado
        $sql = $connection->prepare('INSERT INTO Contacto_Empleado (Telefono, Correo, Calle, Colonia, Codigo_Postal) 
        VALUES (:Telefono, :Correo, :Calle, :Colonia, :Codigo_Postal)');
        $sql->bindValue(':Telefono', '0000000000');
        $sql->bindValue(':Correo', 'correoadmin@gmail.com');
        $sql->bindValue(':Calle', 'Calle');
        $sql->bindValue(':Colonia', 'Colonia');
        $sql->bindValue(':Codigo_Postal', '00000');
        $sql->execute();
        $ID_Contacto = $connection->lastInsertId();



        //Insertar información en tabla Empleado
        $sql = $connection->prepare('INSERT INTO Empleado (Nombre, Puesto, Estado, ID_Contacto_Empleado, ID_Usuario) 
        VALUES (:Nombre, :Puesto, :Estado, :ID_Contacto, :ID_Usuario)');
        $sql->bindValue(':Nombre', 'Usuario Administrador');
        $sql->bindValue(':Puesto', 'admin');
        $sql->bindValue(':Estado', 'activo');
        $sql->bindValue(':ID_Contacto', $ID_Contacto, PDO::PARAM_INT);
        $sql->bindValue(':ID_Usuario', $ID_Usuario, PDO::PARAM_INT);
        $sql->execute();

        $connection->commit();
        $connection = NULL;

        echo "Usuario admin creado correctamente\n\n";
    } else {
        echo "El usuario admin ya existe\n\n";
    }

} catch (Exception $e) {
    if ($connection)
        $connection->rollBack();
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Catálogo de prueba de la tienda en línea (Docker): 180 productos en 12 categorías
// Va antes que los datos de demostración: los dos se cargan solo si no hay productos
if (in_array(strtolower(variableEntorno('SEED_TIENDA', '')), ['1', 'true'], true)) {
    require_once __DIR__ . '/seed_tienda.php';

    try {
        if (!cargarCatalogoTienda(new Conexion)) {
            echo "Ya existen productos, no se cargó el catálogo de la tienda\n\n";
        }
    } catch (Exception $e) {
        echo "Error en el catálogo de la tienda: " . $e->getMessage() . "\n\n";
    }
}

// Datos de demostración (solo desarrollo y QA)
if (in_array(strtolower(variableEntorno('SEED_DEMO', '')), ['1', 'true'], true)) {
    require_once __DIR__ . '/seed_demo.php';

    try {
        if (!cargarDatosDemo(new Conexion)) {
            echo "Ya existen productos, no se cargaron datos de demostración\n\n";
        }
    } catch (Exception $e) {
        echo "Error en datos de demostración: " . $e->getMessage() . "\n\n";
    }
}