<?php

// Clase con CRUD para proveedores

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/validar.php';

class Proveedor
{
    const TABLE = "Proveedor";
    // ID_Proveedor, Nombre_Proveedor, Telefono, Estado

    // Funcion para crear un nuevo proveedor
    public static function createProveedor($Nombre_Proveedor, $Telefono)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('INSERT INTO ' . self::TABLE . ' (Nombre_Proveedor, Telefono, Estado)
            VALUES (:Nombre_Proveedor, :Telefono, :Estado)');
            $sql->bindValue(':Nombre_Proveedor', $Nombre_Proveedor);
            $sql->bindValue(':Telefono', $Telefono);
            $sql->bindValue(':Estado', 'activo');
            $sql->execute();
            $ID_Proveedor = $connection->lastInsertId();
            $connection = NULL;

            return $ID_Proveedor;


        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion crear proveedor

    // Funcion para leer los proveedores con su estado
    // Solo los activos (Inventario, Surtir) o todos ($todos, la pantalla de Proveedores, que permite volver a activarlos)
    public static function readAllProveedores(bool $todos = false)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('SELECT ID_Proveedor, Nombre_Proveedor, Telefono, Estado FROM ' . self::TABLE .
                ($todos ? '' : " WHERE Estado = 'activo'") . ' ORDER BY ID_Proveedor');
            $sql->execute();
            $proveedores = $sql->fetchAll();

            $connection = NULL;

            if ($proveedores) {
                return $proveedores;
            } else {
                return false;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }

    }//-- Fin funcion leer todos los proveedores

    // Funcion para leer 1 proveedor
    public static function readProveedor($ID_Proveedor)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('SELECT Nombre_Proveedor, Telefono, Estado FROM ' . self::TABLE . '
            WHERE ID_Proveedor = :ID_Proveedor');
            $sql->bindValue(':ID_Proveedor', $ID_Proveedor, PDO::PARAM_INT);
            $sql->execute();
            $row = $sql->fetch();

            $connection = NULL;

            if ($row) {
                return $row;
            } else {
                return false;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }

    }//-- Fin funcion leer proveedor


    // Funcion para modificar un proveedor
    public static function updateProveedor($ID_Proveedor, $Nombre_Proveedor, $Telefono)
    {
        try {

            $connection = new Conexion();
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Nombre_Proveedor = :Nombre_Proveedor,
            Telefono = :Telefono
            WHERE ID_Proveedor = :ID_Proveedor');
            $sql->bindValue(':Nombre_Proveedor', $Nombre_Proveedor);
            $sql->bindValue(':Telefono', $Telefono);
            $sql->bindValue(':ID_Proveedor', $ID_Proveedor, PDO::PARAM_INT);

            $sql->execute();
            $row = $sql->rowCount();
            $connection = NULL;


            return $row > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion modificar proveedor

    // Funcion para activar o desactivar un proveedor (no se borra)
    public static function cambiarEstado($ID_Proveedor, $Estado)
    {
        try {

            $connection = new Conexion();
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Estado = :Estado
            WHERE ID_Proveedor = :ID_Proveedor');
            $sql->bindValue(':Estado', $Estado);
            $sql->bindValue(':ID_Proveedor', $ID_Proveedor, PDO::PARAM_INT);
            $sql->execute();
            $row = $sql->rowCount();
            $connection = NULL;

            return $row > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion modificar estado


    // No puede haber dos proveedores con el mismo nombre, activos o no
    // La intercalación de la tabla (utf8mb4_unicode_ci) no distingue mayúsculas ni acentos
    public static function validarNombreLibre(string $Nombre_Proveedor, ?int $excepto = null): void
    {
        $connection = new Conexion();
        $sql = $connection->prepare('SELECT Nombre_Proveedor, Estado FROM ' . self::TABLE . '
            WHERE Nombre_Proveedor = :Nombre_Proveedor AND ID_Proveedor <> :excepto LIMIT 1');
        $sql->execute([':Nombre_Proveedor' => $Nombre_Proveedor, ':excepto' => $excepto ?? 0]);
        $otro = $sql->fetch();

        if ($otro) {
            $mensaje = "Ya existe un proveedor llamado «{$otro['Nombre_Proveedor']}»";
            if ($otro['Estado'] === 'inactivo') {
                $mensaje .= '. Está inactivo: puedes volver a activarlo';
            }
            throw new ErrorDatos($mensaje, 409, ['campo' => 'Nombre_Proveedor']);
        }
    }//-- Fin funcion validar nombre libre
}
