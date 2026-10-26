<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/validar.php';

/**
 * Proveedores de los productos. No se borran: se activan o desactivan. Un proveedor inactivo
 * no se ofrece en Inventario ni en Surtir.
 *
 * @author Miguel Coronado
 * @version 1.0.0
 * @since 1.0.0
 * @see Producto
 * @see Surtir
 */
class Proveedor
{
    const TABLE = "Proveedor";
    // ID_Proveedor, Nombre_Proveedor, Telefono, Estado

    /**
     * Crea un proveedor activo. El nombre ya se validó con validarNombreLibre().
     *
     * @param string      $Nombre_Proveedor Nombre
     * @param string|null $Telefono         Teléfono (10 o 12 dígitos), opcional
     * @return string ID del proveedor creado
     * @throws Exception Si falla la consulta a la base de datos
     * @see Proveedor::validarNombreLibre()
     */
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

    /**
     * Proveedores con su teléfono y su estado.
     *
     * @param bool $todos false: solo los activos (Inventario, Surtir); true: todos (la pantalla de Proveedores,
     *                    que permite volver a activarlos)
     * @return array|false Los proveedores, o false si no hay
     * @throws Exception Si falla la consulta a la base de datos
     */
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

    /**
     * Lee un proveedor.
     *
     * @param int $ID_Proveedor Proveedor
     * @return array|false ['Nombre_Proveedor', 'Telefono', 'Estado'], o false si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Cambia el nombre y el teléfono de un proveedor.
     *
     * @param int         $ID_Proveedor     Proveedor
     * @param string      $Nombre_Proveedor Nombre nuevo
     * @param string|null $Telefono         Teléfono (10 o 12 dígitos), opcional
     * @return int 1 si se actualizó, 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     * @see Proveedor::validarNombreLibre()
     */
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

    /**
     * Activa o desactiva un proveedor (no se borra).
     *
     * @param int    $ID_Proveedor Proveedor
     * @param string $Estado       "activo" o "inactivo"
     * @return int 1 si se actualizó, 0 si no existe
     * @throws Exception Si falla la consulta a la base de datos
     */
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


    /**
     * Verifica que no haya otro proveedor con el mismo nombre, activo o no.
     * La intercalación de la tabla (utf8mb4_unicode_ci) no distingue mayúsculas ni acentos.
     *
     * @param string   $Nombre_Proveedor Nombre que se quiere usar
     * @param int|null $excepto          Proveedor que se está editando (no cuenta como repetido)
     * @return void
     * @throws ErrorDatos 409 "Ya existe un proveedor llamado ..." con ['campo' => 'Nombre_Proveedor'];
     *                    si el otro está inactivo, el mensaje sugiere volver a activarlo
     * @throws PDOException Si falla la consulta
     */
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
