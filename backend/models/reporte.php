<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/producto.php';
require_once __DIR__ . '/surtir.php';

/**
 * Reportes (RF11, solo admin): ventas, surtidos y compras, stock y resumen del periodo.
 * Solo lee datos; las ventas que cuentan son las finalizadas (las canceladas se reportan aparte).
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 * @see Venta
 * @see Surtir
 */
class Reporte
{
    const PRODUCTOS_MAS_VENDIDOS = 10;


    /**
     * Ejecuta una consulta y regresa todas sus filas.
     *
     * @param PDO    $connection Conexión
     * @param string $sql        Consulta con marcadores
     * @param array  $parametros Valores de los marcadores
     * @return array Las filas
     * @throws PDOException Si falla la consulta (la atrapa el reporte que la llama)
     */
    private static function consultar($connection, string $sql, array $parametros = []): array
    {
        $consulta = $connection->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }


    /**
     * Convierte las columnas numéricas (PDO las regresa como texto) para que el JSON tenga números.
     *
     * @param array $filas   Filas de una consulta
     * @param array $enteros Columnas que se convierten a entero
     * @param array $dinero  Columnas que se convierten a número con dos decimales (null se queda null)
     * @return array Las filas con las columnas convertidas
     */
    private static function tipar(array $filas, array $enteros = [], array $dinero = []): array
    {
        foreach ($filas as &$fila) {
            foreach ($enteros as $columna) {
                $fila[$columna] = (int) $fila[$columna];
            }
            foreach ($dinero as $columna) {
                $fila[$columna] = $fila[$columna] === null ? null : round((float) $fila[$columna], 2);
            }
        }
        return $filas;
    }


    /**
     * Porcentaje con un decimal.
     *
     * @param float $parte Parte
     * @param float $total Total
     * @return float|null El porcentaje, o null si no hay base para calcularlo (total 0)
     */
    private static function porcentaje(float $parte, float $total): ?float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : null;
    }


    /**
     * Totales de las ventas finalizadas del periodo (los usan el reporte de ventas y el resumen).
     *
     * @param PDO   $connection Conexión
     * @param array $rango      [':Desde' => fecha, ':Hasta' => fecha]
     * @return array ['ventas' => int, 'total' => float]
     * @throws PDOException Si falla la consulta
     */
    private static function totalesVentas($connection, array $rango): array
    {
        $fila = self::consultar($connection, "SELECT COUNT(*) AS Ventas, COALESCE(SUM(v.Monto), 0) AS Total
        FROM Venta v WHERE v.Estado = 'finalizada' AND v.Fecha BETWEEN :Desde AND :Hasta", $rango)[0];

        return ['ventas' => (int) $fila['Ventas'], 'total' => round((float) $fila['Total'], 2)];
    }


    /**
     * Totales de los surtidos del periodo (los usan el reporte de surtidos y el resumen).
     *
     * @param PDO   $connection Conexión
     * @param array $rango      [':Desde' => fecha, ':Hasta' => fecha]
     * @return array ['surtidos' => int, 'total' => float]
     * @throws PDOException Si falla la consulta
     */
    private static function totalesSurtidos($connection, array $rango): array
    {
        $fila = self::consultar($connection, 'SELECT COUNT(*) AS Surtidos, COALESCE(SUM(s.Monto), 0) AS Total
        FROM Surtir s WHERE s.Fecha BETWEEN :Desde AND :Hasta', $rango)[0];

        return ['surtidos' => (int) $fila['Surtidos'], 'total' => round((float) $fila['Total'], 2)];
    }


    /**
     * Reporte de ventas: totales, formas de pago, días, productos más vendidos y cajeros.
     * El cajero es quien cobró; en las ventas anteriores a la fase 2 (no lo guardaban) se toma quien abrió la caja.
     *
     * @param string $Desde Fecha inicial (AAAA-MM-DD)
     * @param string $Hasta Fecha final (AAAA-MM-DD)
     * @return array ['resumen', 'formas_pago', 'dias', 'productos', 'cajeros']
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function ventas($Desde, $Hasta): array
    {
        try {
            $connection = new Conexion();
            $rango = [':Desde' => $Desde, ':Hasta' => $Hasta];
            $finalizadas = "v.Estado = 'finalizada' AND v.Fecha BETWEEN :Desde AND :Hasta";

            $resumen = self::totalesVentas($connection, $rango);

            $resumen['articulos'] = (int) self::consultar($connection, "SELECT COALESCE(SUM(d.Cantidad), 0) AS Articulos
            FROM Detalle_Venta d INNER JOIN Venta v ON v.ID_Venta = d.ID_Venta
            WHERE $finalizadas", $rango)[0]['Articulos'];

            $resumen['promedio'] = $resumen['ventas'] > 0 ? round($resumen['total'] / $resumen['ventas'], 2) : 0;

            $resumen['canceladas'] = (int) self::consultar($connection, "SELECT COUNT(*) AS Canceladas FROM Venta v
            WHERE v.Estado = 'cancelada' AND v.Fecha BETWEEN :Desde AND :Hasta", $rango)[0]['Canceladas'];

            $formasPago = self::consultar($connection, "SELECT v.Forma_Pago, COUNT(*) AS Ventas, SUM(v.Monto) AS Total
            FROM Venta v WHERE $finalizadas
            GROUP BY v.Forma_Pago ORDER BY Total DESC", $rango);

            $dias = self::consultar($connection, "SELECT v.Fecha, COUNT(*) AS Ventas, SUM(v.Monto) AS Total
            FROM Venta v WHERE $finalizadas
            GROUP BY v.Fecha ORDER BY v.Fecha", $rango);

            $productos = self::consultar($connection, "SELECT d.ID_Producto, p.Nombre_Producto, p.Unidad_Medida,
            SUM(d.Cantidad) AS Unidades, SUM(d.Cantidad * d.Precio_Unitario) AS Importe
            FROM Detalle_Venta d
            INNER JOIN Venta v ON v.ID_Venta = d.ID_Venta
            INNER JOIN Producto p ON p.ID_Producto = d.ID_Producto
            WHERE $finalizadas
            GROUP BY d.ID_Producto, p.Nombre_Producto, p.Unidad_Medida
            ORDER BY Unidades DESC, Importe DESC, p.Nombre_Producto
            LIMIT " . self::PRODUCTOS_MAS_VENDIDOS, $rango);

            // El cajero es quien cobró; las ventas anteriores a la fase 2 no lo guardaban y se toma quien abrió la caja
            $cajeros = self::consultar($connection, "SELECT COALESCE(e.Nombre, 'Sin asignar') AS Cajero,
            COUNT(*) AS Ventas, SUM(v.Monto) AS Total
            FROM Venta v
            LEFT JOIN Caja c ON c.ID_Caja = v.ID_Caja
            LEFT JOIN Empleado e ON e.ID_Usuario = COALESCE(v.ID_Usuario, c.ID_Usuario)
            WHERE $finalizadas
            GROUP BY COALESCE(v.ID_Usuario, c.ID_Usuario), e.Nombre
            ORDER BY Total DESC, Cajero", $rango);

            $connection = NULL;

            return [
                'resumen' => $resumen,
                'formas_pago' => self::tipar($formasPago, ['Ventas'], ['Total']),
                'dias' => self::tipar($dias, ['Ventas'], ['Total']),
                'productos' => self::tipar($productos, ['ID_Producto', 'Unidades'], ['Importe']),
                'cajeros' => self::tipar($cajeros, ['Ventas'], ['Total']),
            ];

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion reporte de ventas


    /**
     * Reporte de surtidos y compras: totales, proveedores, productos surtidos (con su costo promedio
     * por unidad en el periodo) y la lista de surtidos.
     *
     * @param string $Desde Fecha inicial (AAAA-MM-DD)
     * @param string $Hasta Fecha final (AAAA-MM-DD)
     * @return array ['resumen', 'proveedores', 'productos', 'surtidos']
     * @throws Exception Si falla la consulta a la base de datos
     * @see Surtir::readAllSurtidos()
     */
    public static function surtidos($Desde, $Hasta): array
    {
        try {
            $connection = new Conexion();
            $rango = [':Desde' => $Desde, ':Hasta' => $Hasta];

            $resumen = self::totalesSurtidos($connection, $rango);

            $detalle = self::consultar($connection, 'SELECT COALESCE(SUM(d.Cantidad), 0) AS Unidades,
            COUNT(DISTINCT d.ID_Producto) AS Productos
            FROM Detalle_Surtir d INNER JOIN Surtir s ON s.ID_Surtir = d.ID_Surtir
            WHERE s.Fecha BETWEEN :Desde AND :Hasta', $rango)[0];
            $resumen['unidades'] = (int) $detalle['Unidades'];
            $resumen['productos'] = (int) $detalle['Productos'];

            $proveedores = self::consultar($connection, "SELECT COALESCE(pr.Nombre_Proveedor, 'Sin proveedor') AS Proveedor,
            COUNT(*) AS Surtidos, COALESCE(SUM(u.Unidades), 0) AS Unidades, SUM(s.Monto) AS Total
            FROM Surtir s
            LEFT JOIN Proveedor pr ON pr.ID_Proveedor = s.ID_Proveedor
            LEFT JOIN (SELECT ID_Surtir, SUM(Cantidad) AS Unidades FROM Detalle_Surtir GROUP BY ID_Surtir) u
                ON u.ID_Surtir = s.ID_Surtir
            WHERE s.Fecha BETWEEN :Desde AND :Hasta
            GROUP BY s.ID_Proveedor, pr.Nombre_Proveedor
            ORDER BY Total DESC, Proveedor", $rango);

            $productos = self::tipar(self::consultar($connection, 'SELECT d.ID_Producto, p.Nombre_Producto, p.Unidad_Medida,
            SUM(d.Cantidad) AS Unidades, SUM(d.Cantidad * d.Precio_Unitario) AS Total
            FROM Detalle_Surtir d
            INNER JOIN Surtir s ON s.ID_Surtir = d.ID_Surtir
            INNER JOIN Producto p ON p.ID_Producto = d.ID_Producto
            WHERE s.Fecha BETWEEN :Desde AND :Hasta
            GROUP BY d.ID_Producto, p.Nombre_Producto, p.Unidad_Medida
            ORDER BY Total DESC, p.Nombre_Producto', $rango), ['ID_Producto', 'Unidades'], ['Total']);

            // Costo promedio por unidad en el periodo (si se compró a distintos costos)
            foreach ($productos as &$producto) {
                $producto['Costo_Promedio'] = $producto['Unidades'] > 0 ? round($producto['Total'] / $producto['Unidades'], 2) : 0;
            }
            unset($producto);

            $connection = NULL;

            return [
                'resumen' => $resumen,
                'proveedores' => self::tipar($proveedores, ['Surtidos', 'Unidades'], ['Total']),
                'productos' => $productos,
                'surtidos' => self::tipar(Surtir::readAllSurtidos($Desde, $Hasta), ['ID_Surtir', 'Productos', 'Unidades'], ['Monto']),
            ];

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion reporte de surtidos


    /**
     * Cantidad sugerida para surtir: lo necesario para llegar al doble de la cantidad mínima (al menos 1).
     * Es la misma regla que usa la página Surtir (utils/productos.js).
     *
     * @param int $Cantidad        Existencias (las negativas cuentan como 0)
     * @param int $Cantidad_Minima Cantidad mínima
     * @return int Piezas por surtir
     */
    public static function cantidadSugerida(int $Cantidad, int $Cantidad_Minima): int
    {
        return max($Cantidad_Minima * 2 - max($Cantidad, 0), 1);
    }//-- Fin funcion cantidad sugerida


    /**
     * Reporte de stock en este momento: valor del inventario, categorías, productos por surtir
     * (primero los agotados) y existencias. Solo productos activos; el valor a costo usa el último costo
     * de compra y no incluye los productos sin costo. Los importes se suman en centavos.
     *
     * @return array ['resumen', 'categorias', 'por_surtir', 'productos']
     * @throws Exception Si falla la consulta a la base de datos
     * @see Reporte::cantidadSugerida()
     * @see Producto::calcularEstado()
     */
    public static function stock(): array
    {
        try {
            $connection = new Conexion();

            $productos = self::tipar(self::consultar($connection, "SELECT p.ID_Producto, p.Nombre_Producto,
            COALESCE(c.Nombre_Categoria, 'Sin categoría') AS Categoria, p.Unidad_Medida, p.Cantidad, p.Cantidad_Minima,
            p.Precio, p.Costo, COALESCE(pr.Nombre_Proveedor, '') AS Proveedor
            FROM Producto p
            LEFT JOIN Categoria c ON c.ID_Categoria = p.ID_Categoria
            LEFT JOIN Proveedor pr ON pr.ID_Proveedor = p.ID_Proveedor
            WHERE p.Activo = 1
            ORDER BY Categoria, p.Nombre_Producto"), ['ID_Producto', 'Cantidad', 'Cantidad_Minima'], ['Precio', 'Costo']);

            $connection = NULL;

            // Los importes se suman en centavos para que no se acumulen errores de redondeo
            $resumen = ['productos' => 0, 'unidades' => 0, 'valor_venta' => 0, 'valor_costo' => 0,
                'sin_costo' => 0, 'stock_bajo' => 0, 'agotados' => 0];
            $categorias = [];
            $porSurtir = [];

            foreach ($productos as &$producto) {
                $producto['Estado'] = Producto::calcularEstado($producto['Cantidad'], $producto['Cantidad_Minima']);
                $valorVenta = aCentavos($producto['Precio']) * $producto['Cantidad'];
                $valorCosto = $producto['Costo'] === null ? null : aCentavos($producto['Costo']) * $producto['Cantidad'];
                $producto['Valor_Venta'] = $valorVenta / 100;
                $producto['Valor_Costo'] = $valorCosto === null ? null : $valorCosto / 100;

                $resumen['productos']++;
                $resumen['unidades'] += $producto['Cantidad'];
                $resumen['valor_venta'] += $valorVenta;
                $resumen['valor_costo'] += $valorCosto ?? 0;
                $resumen['sin_costo'] += $valorCosto === null ? 1 : 0;
                $resumen['stock_bajo'] += $producto['Estado'] === 'Stock bajo' ? 1 : 0;
                $resumen['agotados'] += $producto['Estado'] === 'Agotado' ? 1 : 0;

                $categoria = &$categorias[$producto['Categoria']];
                $categoria ??= ['Categoria' => $producto['Categoria'], 'Productos' => 0, 'Unidades' => 0,
                    'Valor_Venta' => 0, 'Valor_Costo' => 0, 'Con_Costo' => 0];
                $categoria['Productos']++;
                $categoria['Unidades'] += $producto['Cantidad'];
                $categoria['Valor_Venta'] += $valorVenta;
                $categoria['Valor_Costo'] += $valorCosto ?? 0;
                $categoria['Con_Costo'] += $valorCosto === null ? 0 : 1;
                unset($categoria);

                if ($producto['Estado'] !== 'Disponible') {
                    $sugerido = self::cantidadSugerida($producto['Cantidad'], $producto['Cantidad_Minima']);
                    $porSurtir[] = [
                        'ID_Producto' => $producto['ID_Producto'],
                        'Nombre_Producto' => $producto['Nombre_Producto'],
                        'Unidad_Medida' => $producto['Unidad_Medida'],
                        'Proveedor' => $producto['Proveedor'],
                        'Cantidad' => $producto['Cantidad'],
                        'Cantidad_Minima' => $producto['Cantidad_Minima'],
                        'Estado' => $producto['Estado'],
                        'Sugerido' => $sugerido,
                        'Costo' => $producto['Costo'],
                        'Costo_Estimado' => $producto['Costo'] === null ? null : aCentavos($producto['Costo']) * $sugerido / 100,
                    ];
                }
            }
            unset($producto);

            $resumen['valor_venta'] /= 100;
            $resumen['valor_costo'] /= 100;

            // Si ningún producto de la categoría tiene costo, su valor a costo no se conoce (null, no 0)
            $categorias = array_map(function ($categoria) {
                $categoria['Valor_Venta'] /= 100;
                $categoria['Valor_Costo'] = $categoria['Con_Costo'] > 0 ? $categoria['Valor_Costo'] / 100 : null;
                unset($categoria['Con_Costo']);
                return $categoria;
            }, array_values($categorias));

            // Primero los agotados y después los de menor existencia
            usort($porSurtir, fn($a, $b) => [$a['Cantidad'] > 0, $a['Cantidad'], $a['Nombre_Producto']]
                <=> [$b['Cantidad'] > 0, $b['Cantidad'], $b['Nombre_Producto']]);

            return [
                'resumen' => $resumen,
                'categorias' => $categorias,
                'por_surtir' => $porSurtir,
                'productos' => $productos,
            ];

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion reporte de stock


    /**
     * Resumen del periodo: ventas contra compras, flujo por día, utilidad estimada y cortes de caja.
     * La utilidad se estima con el último costo de compra de cada producto: es aproximada y solo cubre
     * lo vendido de productos que ya tienen costo (la cobertura dice qué porcentaje de las ventas cubre).
     *
     * @param string $Desde Fecha inicial (AAAA-MM-DD)
     * @param string $Hasta Fecha final (AAAA-MM-DD)
     * @return array ['ventas', 'compras', 'flujo', 'utilidad', 'cajas', 'dias']
     * @throws Exception Si falla la consulta a la base de datos
     */
    public static function resumen($Desde, $Hasta): array
    {
        try {
            $connection = new Conexion();
            $rango = [':Desde' => $Desde, ':Hasta' => $Hasta];
            $finalizadas = "v.Estado = 'finalizada' AND v.Fecha BETWEEN :Desde AND :Hasta";

            $ventas = self::totalesVentas($connection, $rango);
            $compras = self::totalesSurtidos($connection, $rango);

            $margen = self::consultar($connection, "SELECT COALESCE(SUM(d.Cantidad * d.Precio_Unitario), 0) AS Importe,
            COALESCE(SUM(CASE WHEN p.Costo IS NOT NULL THEN d.Cantidad * d.Precio_Unitario END), 0) AS Importe_Con_Costo,
            COALESCE(SUM(CASE WHEN p.Costo IS NOT NULL THEN d.Cantidad * p.Costo END), 0) AS Costo
            FROM Detalle_Venta d
            INNER JOIN Venta v ON v.ID_Venta = d.ID_Venta
            INNER JOIN Producto p ON p.ID_Producto = d.ID_Producto
            WHERE $finalizadas", $rango)[0];

            $conCosto = (float) $margen['Importe_Con_Costo'];
            $utilidad = (aCentavos($margen['Importe_Con_Costo']) - aCentavos($margen['Costo'])) / 100;

            $cajas = self::consultar($connection, "SELECT COUNT(*) AS Cortes,
            COALESCE(SUM(Estado_Final = 'descuadrada'), 0) AS Descuadradas,
            COALESCE(SUM(Monto_Final - Monto_Final_Sistema), 0) AS Diferencia
            FROM Caja WHERE Estado = 'cerrada' AND Fecha BETWEEN :Desde AND :Hasta", $rango)[0];

            // Ventas y compras por día: un renglón por cada día que tuvo alguna de las dos
            $dias = [];
            $ventasDia = self::consultar($connection, "SELECT v.Fecha, SUM(v.Monto) AS Total FROM Venta v
            WHERE $finalizadas GROUP BY v.Fecha", $rango);
            $comprasDia = self::consultar($connection, 'SELECT s.Fecha, SUM(s.Monto) AS Total FROM Surtir s
            WHERE s.Fecha BETWEEN :Desde AND :Hasta GROUP BY s.Fecha', $rango);

            foreach ([['Ventas', $ventasDia], ['Compras', $comprasDia]] as [$columna, $filas]) {
                foreach ($filas as $fila) {
                    $dias[$fila['Fecha']] ??= ['Fecha' => $fila['Fecha'], 'Ventas' => 0, 'Compras' => 0];
                    $dias[$fila['Fecha']][$columna] = round((float) $fila['Total'], 2);
                }
            }
            ksort($dias);
            $dias = array_map(function ($dia) {
                $dia['Flujo'] = (aCentavos($dia['Ventas']) - aCentavos($dia['Compras'])) / 100;
                return $dia;
            }, array_values($dias));

            $connection = NULL;

            return [
                'ventas' => $ventas,
                'compras' => $compras,
                'flujo' => (aCentavos($ventas['total']) - aCentavos($compras['total'])) / 100,
                'utilidad' => [
                    'estimada' => $utilidad,
                    'importe_con_costo' => round($conCosto, 2),
                    'margen' => self::porcentaje($utilidad, $conCosto),
                    'cobertura' => self::porcentaje($conCosto, (float) $margen['Importe']),
                ],
                'cajas' => [
                    'cortes' => (int) $cajas['Cortes'],
                    'descuadradas' => (int) $cajas['Descuadradas'],
                    'diferencia' => round((float) $cajas['Diferencia'], 2),
                ],
                'dias' => $dias,
            ];

        } catch (PDOException $e) {
            throw new Exception("Hubo un error: " . $e->getMessage());
        }
    }//-- Fin funcion resumen del periodo
}//-- Fin clase Reporte
