<?php

require_once __DIR__ . '/../config/database.php';

/**
 * Catálogo de la tienda en línea: lo que ve el cliente.
 * Solo productos activos de categorías activas. Nunca se exponen costos, proveedores ni cantidades mínimas.
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Producto
 */
class Catalogo
{
    const POR_PAGINA = 12;

    // Orden de la lista => ORDER BY. El nombre y el ID desempatan para que la paginación sea estable
    const ORDENES = [
        'nombre' => 'p.Nombre_Producto, p.ID_Producto',
        'precio_asc' => 'p.Precio, p.Nombre_Producto, p.ID_Producto',
        'precio_desc' => 'p.Precio DESC, p.Nombre_Producto, p.ID_Producto',
    ];

    private const COLUMNAS = 'p.ID_Producto, p.Nombre_Producto, p.Descripcion, p.Precio, p.Cantidad, p.Cantidad_Minima,
        p.Unidad_Medida, c.ID_Categoria, c.Nombre_Categoria';

    private const VISIBLES = "FROM Producto p INNER JOIN Categoria c ON c.ID_Categoria = p.ID_Categoria
        WHERE p.Activo = 1 AND c.Estado = 'activo'";


    /**
     * Disponibilidad que ve el cliente: la misma regla que el Estado de SGT.
     *
     * @param int $Cantidad        Existencias
     * @param int $Cantidad_Minima Cantidad mínima
     * @return string "agotado", "pocas_piezas" o "disponible"
     * @see Producto::calcularEstado()
     */
    public static function disponibilidad(int $Cantidad, int $Cantidad_Minima): string
    {
        if ($Cantidad <= 0) {
            return 'agotado';
        }
        return $Cantidad > $Cantidad_Minima ? 'disponible' : 'pocas_piezas';
    }//-- Fin funcion disponibilidad


    /**
     * Convierte una fila de la base de datos en el producto público (sin costo, proveedor ni mínimo).
     *
     * @param array $fila Fila con las columnas de Catalogo::COLUMNAS
     * @return array ['id', 'nombre', 'descripcion', 'precio', 'unidad', 'existencia', 'disponibilidad', 'categoria']
     */
    private static function publico(array $fila): array
    {
        $existencia = max((int) $fila['Cantidad'], 0);

        return [
            'id' => (int) $fila['ID_Producto'],
            'nombre' => $fila['Nombre_Producto'],
            'descripcion' => $fila['Descripcion'],
            'precio' => (float) $fila['Precio'],
            'unidad' => $fila['Unidad_Medida'],
            'existencia' => $existencia,
            'disponibilidad' => self::disponibilidad($existencia, (int) $fila['Cantidad_Minima']),
            'categoria' => ['id' => (int) $fila['ID_Categoria'], 'nombre' => $fila['Nombre_Categoria']],
        ];
    }//-- Fin funcion publico


    /**
     * Categorías activas que tienen productos visibles, con cuántos tiene cada una.
     *
     * @return array Lista de ['id', 'nombre', 'productos'], por nombre
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     */
    public static function categorias(): array
    {
        $connection = new Conexion;
        $filas = $connection->query("SELECT c.ID_Categoria, c.Nombre_Categoria, COUNT(*) AS Productos
            FROM Categoria c INNER JOIN Producto p ON p.ID_Categoria = c.ID_Categoria AND p.Activo = 1
            WHERE c.Estado = 'activo'
            GROUP BY c.ID_Categoria, c.Nombre_Categoria
            ORDER BY c.Nombre_Categoria")->fetchAll();

        return array_map(fn($fila) => [
            'id' => (int) $fila['ID_Categoria'],
            'nombre' => $fila['Nombre_Categoria'],
            'productos' => (int) $fila['Productos'],
        ], $filas);
    }//-- Fin funcion categorias


    /**
     * Una categoría visible en la tienda.
     *
     * @param int $ID_Categoria Categoría
     * @return array|null ['id', 'nombre', 'productos'], o null si no existe, está desactivada o no tiene productos
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     * @see Catalogo::categorias()
     */
    public static function categoria(int $ID_Categoria): ?array
    {
        foreach (self::categorias() as $categoria) {
            if ($categoria['id'] === $ID_Categoria) {
                return $categoria;
            }
        }
        return null;
    }//-- Fin funcion categoria


    /**
     * Productos con búsqueda, filtros, orden y paginación.
     * Cada palabra de la búsqueda debe aparecer en el nombre, la descripción o la categoría. La intercalación
     * de las tablas (utf8mb4_unicode_ci) no distingue mayúsculas ni acentos: "azucar" encuentra "Azúcar".
     *
     * @param string   $q               Texto de búsqueda (se usan hasta 8 palabras)
     * @param int|null $ID_Categoria    Categoría, o null para todas
     * @param bool     $soloDisponibles Si se ocultan los agotados
     * @param string   $orden           Llave de Catalogo::ORDENES
     * @param int      $pagina          Página (desde 1)
     * @return array [productos de la página, total de productos que cumplen los filtros]
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     */
    public static function productos(string $q, ?int $ID_Categoria, bool $soloDisponibles, string $orden, int $pagina): array
    {
        $condiciones = '';
        $parametros = [];

        $palabras = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        foreach (array_slice($palabras, 0, 8) as $i => $palabra) {
            // Los comodines de LIKE escritos por el cliente se buscan como texto
            $parametros[":q$i"] = '%' . addcslashes($palabra, '%_\\') . '%';
            $condiciones .= " AND (p.Nombre_Producto LIKE :q$i OR p.Descripcion LIKE :q$i OR c.Nombre_Categoria LIKE :q$i)";
        }
        if ($ID_Categoria !== null) {
            $condiciones .= ' AND p.ID_Categoria = :categoria';
            $parametros[':categoria'] = $ID_Categoria;
        }
        if ($soloDisponibles) {
            $condiciones .= ' AND p.Cantidad > 0';
        }

        $connection = new Conexion;

        $total = $connection->prepare('SELECT COUNT(*) ' . self::VISIBLES . $condiciones);
        $total->execute($parametros);
        $total = (int) $total->fetchColumn();

        // Una página después de la última no tiene productos (y no se calcula un OFFSET enorme)
        if (($pagina - 1) * self::POR_PAGINA >= $total) {
            return [[], $total];
        }

        $sql = $connection->prepare('SELECT ' . self::COLUMNAS . ' ' . self::VISIBLES . $condiciones
            . ' ORDER BY ' . self::ORDENES[$orden]
            . ' LIMIT ' . self::POR_PAGINA . ' OFFSET ' . (($pagina - 1) * self::POR_PAGINA));
        $sql->execute($parametros);

        return [array_map([self::class, 'publico'], $sql->fetchAll()), $total];
    }//-- Fin funcion productos


    /**
     * Productos destacados del inicio: el de más existencias de cada categoría, sin agotados.
     *
     * @return array Productos públicos, por categoría
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     */
    public static function destacados(): array
    {
        $connection = new Conexion;
        $filas = $connection->query('SELECT * FROM (SELECT ' . self::COLUMNAS . ',
                ROW_NUMBER() OVER (PARTITION BY p.ID_Categoria ORDER BY p.Cantidad DESC, p.ID_Producto) AS Lugar
                ' . self::VISIBLES . ' AND p.Cantidad > 0) d
            WHERE d.Lugar = 1
            ORDER BY d.Nombre_Categoria')->fetchAll();

        return array_map([self::class, 'publico'], $filas);
    }//-- Fin funcion destacados


    /**
     * Un producto visible en la tienda.
     *
     * @param int $ID_Producto Producto
     * @return array|null El producto público, o null si no existe o no se muestra en la tienda
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     */
    public static function producto(int $ID_Producto): ?array
    {
        $connection = new Conexion;
        $sql = $connection->prepare('SELECT ' . self::COLUMNAS . ' ' . self::VISIBLES . ' AND p.ID_Producto = :id');
        $sql->execute([':id' => $ID_Producto]);
        $fila = $sql->fetch();

        return $fila ? self::publico($fila) : null;
    }//-- Fin funcion producto


    /**
     * Productos visibles con esos IDs (los que no se muestran en la tienda no vienen).
     *
     * @param array $IDs IDs de productos
     * @return array Productos públicos indexados por ID
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     */
    public static function productosPorId(array $IDs): array
    {
        if (!$IDs) {
            return [];
        }
        $connection = new Conexion;
        $marcadores = implode(', ', array_fill(0, count($IDs), '?'));
        $sql = $connection->prepare('SELECT ' . self::COLUMNAS . ' ' . self::VISIBLES . " AND p.ID_Producto IN ($marcadores)");
        $sql->execute(array_values($IDs));

        $productos = [];
        foreach ($sql->fetchAll() as $fila) {
            $productos[(int) $fila['ID_Producto']] = self::publico($fila);
        }
        return $productos;
    }//-- Fin funcion productos por ID


    /**
     * Nombres de productos, aunque ya no se muestren (para avisar cuál se quitó del carrito).
     *
     * @param array $IDs IDs de productos
     * @return array [ID => nombre]
     * @throws PDOException Si falla la consulta a la base de datos (index.php responde 500)
     */
    public static function nombres(array $IDs): array
    {
        if (!$IDs) {
            return [];
        }
        $connection = new Conexion;
        $marcadores = implode(', ', array_fill(0, count($IDs), '?'));
        $sql = $connection->prepare("SELECT ID_Producto, Nombre_Producto FROM Producto WHERE ID_Producto IN ($marcadores)");
        $sql->execute(array_values($IDs));
        return array_map('strval', $sql->fetchAll(PDO::FETCH_KEY_PAIR));
    }//-- Fin funcion nombres
}
