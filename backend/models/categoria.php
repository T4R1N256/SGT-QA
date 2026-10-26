<?php

// Clase con CRUD para categorias

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/validar.php';

class Categoria
{
    const TABLE = "Categoria";
    // ID_Categoria, Nombre_Categoria, Estado


    // Funcion para crear una nueva categoria
    public static function createCategoria($Nombre_Categoria)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('INSERT INTO ' . self::TABLE . ' (Nombre_Categoria, Estado)
            VALUES (:Nombre_Categoria, :Estado)');
            $sql->bindValue(':Nombre_Categoria', $Nombre_Categoria);
            $sql->bindValue(':Estado', 'activo');
            $sql->execute();
            $ID_Categoria = $connection->lastInsertId();
            $connection = NULL;

            return $ID_Categoria;


        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion crear categoria

    // Funcion para leer las categorias con su estado y cuántos productos activos tiene cada una
    // Solo las activas (Inventario) o todas ($todas, la pantalla de Categorías, que permite volver a activarlas)
    public static function readAllCategoria(bool $todas = false)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('SELECT c.ID_Categoria, c.Nombre_Categoria, c.Estado,
                (SELECT COUNT(*) FROM Producto p WHERE p.ID_Categoria = c.ID_Categoria AND p.Activo = 1) AS Productos
                FROM ' . self::TABLE . ' c' . ($todas ? '' : " WHERE c.Estado = 'activo'") . '
                ORDER BY c.ID_Categoria');
            $sql->execute();
            $categorias = $sql->fetchAll();

            $connection = NULL;

            if ($categorias) {
                return array_map(fn($c) => array_merge($c, ['Productos' => (int) $c['Productos']]), $categorias);
            } else {
                return false;
            }

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }

    }//-- Fin funcion leer todas las categorias

    // Funcion para leer 1 categoria
    public static function readCategoria($ID_Categoria)
    {
        try {
            $connection = new Conexion();

            $sql = $connection->prepare('SELECT Nombre_Categoria, Estado FROM ' . self::TABLE . '
            WHERE ID_Categoria = :ID_Categoria');
            $sql->bindValue(':ID_Categoria', $ID_Categoria, PDO::PARAM_INT);
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

    }//-- Fin funcion leer categoria


    // Funcion para modificar una categoria
    public static function updateCategoria($ID_Categoria, $Nombre_Categoria)
    {
        try {

            $connection = new Conexion();
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Nombre_Categoria = :Nombre_Categoria
            WHERE ID_Categoria = :ID_Categoria');
            $sql->bindValue(':Nombre_Categoria', $Nombre_Categoria);
            $sql->bindValue(':ID_Categoria', $ID_Categoria, PDO::PARAM_INT);

            $sql->execute();
            $row = $sql->rowCount();
            $connection = NULL;


            return $row > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion modificar categoria


    // Funcion para activar o desactivar una categoria (no se borra)
    public static function cambiarEstado($ID_Categoria, $Estado)
    {
        try {
            $connection = new Conexion();
            $sql = $connection->prepare('UPDATE ' . self::TABLE . ' SET Estado = :Estado
            WHERE ID_Categoria = :ID_Categoria');
            $sql->bindValue(':Estado', $Estado);
            $sql->bindValue(':ID_Categoria', $ID_Categoria, PDO::PARAM_INT);
            $sql->execute();
            $row = $sql->rowCount();
            $connection = NULL;


            return $row > 0 ? 1 : 0;

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion cambiar estado


    // No puede haber dos categorías con el mismo nombre, activas o no
    // La intercalación de la tabla (utf8mb4_unicode_ci) no distingue mayúsculas ni acentos: "lacteos" = "Lácteos"
    public static function validarNombreLibre(string $Nombre_Categoria, ?int $excepto = null): void
    {
        $connection = new Conexion();
        $sql = $connection->prepare('SELECT Nombre_Categoria, Estado FROM ' . self::TABLE . '
            WHERE Nombre_Categoria = :Nombre_Categoria AND ID_Categoria <> :excepto LIMIT 1');
        $sql->execute([':Nombre_Categoria' => $Nombre_Categoria, ':excepto' => $excepto ?? 0]);
        $otra = $sql->fetch();

        if ($otra) {
            $mensaje = "Ya existe una categoría llamada «{$otra['Nombre_Categoria']}»";
            if ($otra['Estado'] === 'inactivo') {
                $mensaje .= '. Está inactiva: puedes volver a activarla';
            }
            throw new ErrorDatos($mensaje, 409, ['campo' => 'Nombre_Categoria']);
        }
    }//-- Fin funcion validar nombre libre


    // Desactivar una categoría con productos activos los quita de la tienda en línea: el admin debe confirmarlo
    // Sin confirmación se bloquea con 409 y se responde cuántos productos tiene para que la pantalla lo avise
    public static function validarDesactivar(int $ID_Categoria, string $Nombre_Categoria, bool $confirmado): void
    {
        if ($confirmado) {
            return;
        }
        $connection = new Conexion();
        $sql = $connection->prepare('SELECT COUNT(*) FROM Producto WHERE ID_Categoria = :ID_Categoria AND Activo = 1');
        $sql->execute([':ID_Categoria' => $ID_Categoria]);
        $productos = (int) $sql->fetchColumn();

        if ($productos > 0) {
            $cuantos = $productos === 1 ? '1 producto activo' : "$productos productos activos";
            throw new ErrorDatos(
                "«{$Nombre_Categoria}» tiene $cuantos. Si la desactivas, la tienda en línea deja de mostrarlos. Confirma para desactivarla.",
                409,
                ['requiere_confirmacion' => true, 'productos' => $productos]
            );
        }
    }//-- Fin funcion validar desactivar

}
