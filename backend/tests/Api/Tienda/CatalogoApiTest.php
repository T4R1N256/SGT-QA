<?php
// API del catálogo de la tienda (fase T1): categorías, búsqueda, filtros, orden, paginación y detalle
// Usa el catálogo de prueba (SEED_TIENDA=1): 12 categorías con 15 productos cada una

final class CatalogoApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        ServidorApi::url();
        BaseDatosPrueba::crearConEsquemaBase();
        BaseDatosPrueba::ejecutarSeeder(['SEED_TIENDA' => '1', 'SEED_DEMO' => '0']);
    }

    private function productos(array $parametros = []): array
    {
        return $this->cliente()->get('/api/tienda/productos?' . http_build_query($parametros));
    }

    private function idCategoria(string $nombre): int
    {
        return (int) $this->valor('SELECT ID_Categoria FROM Categoria WHERE Nombre_Categoria = ?', [$nombre]);
    }

    private function idProducto(string $nombre): int
    {
        return (int) $this->valor('SELECT ID_Producto FROM Producto WHERE Nombre_Producto = ?', [$nombre]);
    }

    private function nombres(array $cuerpo): array
    {
        return array_column($cuerpo['productos'], 'nombre');
    }

    public function testLasCategoriasSonPublicasConSuNumeroDeProductos(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->get('/api/tienda/categorias');

        $this->assertSame(200, $codigo);
        $this->assertCount(12, $cuerpo['categorias']);
        $this->assertSame(['id', 'nombre', 'productos'], array_keys($cuerpo['categorias'][0]));
        $this->assertSame(15, $cuerpo['categorias'][0]['productos']);

        $this->assertSame([
            'Abarrotes', 'Bebidas', 'Botanas y dulces', 'Cereales y desayuno', 'Enlatados y conservas', 'Frutas y verduras',
            'Higiene personal', 'Lácteos y huevo', 'Limpieza del hogar', 'Mascotas', 'Panadería y tortillas', 'Salchichonería',
        ], array_column($cuerpo['categorias'], 'nombre'));
    }

    public function testUnaCategoriaDesactivadaYSusProductosNoSeMuestran(): void
    {
        $ID_Categoria = $this->idCategoria('Mascotas');
        BaseDatosPrueba::conexion()->exec("UPDATE Categoria SET Estado = 'inactivo' WHERE ID_Categoria = $ID_Categoria");
        $ID_Producto = (int) $this->valor('SELECT ID_Producto FROM Producto WHERE ID_Categoria = ? LIMIT 1', [$ID_Categoria]);

        [, $categorias] = $this->cliente()->get('/api/tienda/categorias');
        [, $todos] = $this->productos();
        [$codigoCategoria] = $this->productos(['categoria' => $ID_Categoria]);
        [$codigoProducto] = $this->cliente()->get("/api/tienda/producto?id=$ID_Producto");

        $this->assertNotContains('Mascotas', array_column($categorias['categorias'], 'nombre'));
        $this->assertSame(165, $todos['total']);
        $this->assertSame(404, $codigoCategoria);
        $this->assertSame(404, $codigoProducto);
    }

    public function testLaListaEstaPaginadaYOrdenadaPorNombre(): void
    {
        [$codigo, $cuerpo] = $this->productos();

        $this->assertSame(200, $codigo);
        $this->assertSame(180, $cuerpo['total']);
        $this->assertSame(1, $cuerpo['pagina']);
        $this->assertSame(15, $cuerpo['paginas']);
        $this->assertSame(12, $cuerpo['por_pagina']);
        $this->assertCount(12, $cuerpo['productos']);
        $this->assertArrayNotHasKey('categoria', $cuerpo);

        $nombres = $this->nombres($cuerpo);
        $this->assertSame($nombres, $this->ordenarComoLaBaseDeDatos($nombres));
    }

    public function testNoExponeCostosProveedoresNiCantidadMinima(): void
    {
        [, $cuerpo] = $this->productos();

        $this->assertSame(
            ['id', 'nombre', 'descripcion', 'precio', 'unidad', 'existencia', 'disponibilidad', 'categoria'],
            array_keys($cuerpo['productos'][0])
        );
        $this->assertSame(['id', 'nombre'], array_keys($cuerpo['productos'][0]['categoria']));
    }

    public function testLaBusquedaNoDistingueAcentosNiMayusculas(): void
    {
        // N3: "azucar" sin acento encuentra "Azúcar estándar 1 kg"
        foreach (['azucar', 'AZÚCAR', '  Azucar  '] as $q) {
            [$codigo, $cuerpo] = $this->productos(['q' => $q]);

            $this->assertSame(200, $codigo);
            $this->assertContains('Azúcar estándar 1 kg', $this->nombres($cuerpo), "Búsqueda: '$q'");
        }
    }

    public function testCadaPalabraDeLaBusquedaDebeAparecer(): void
    {
        [, $cuerpo] = $this->productos(['q' => 'frijol negro']);

        $this->assertSame(['Frijol negro 1 kg'], $this->nombres($cuerpo));
    }

    public function testLaBusquedaIncluyeDescripcionYCategoria(): void
    {
        // "nixtamalizado" solo aparece en la descripción de la harina de maíz
        [, $porDescripcion] = $this->productos(['q' => 'nixtamalizado']);
        [, $porCategoria] = $this->productos(['q' => 'mascotas']);

        $this->assertContains('Harina de maíz 1 kg', $this->nombres($porDescripcion));
        $this->assertGreaterThanOrEqual(15, $porCategoria['total']);
    }

    public function testLosComodinesDeLaBusquedaSeBuscanComoTexto(): void
    {
        // "%" solo encuentra los textos que lo llevan ("100 % arábica", "70 % de alcohol");
        // "_" y "\\" tampoco funcionan como comodines de LIKE
        [, $porcentaje] = $this->productos(['q' => '%']);
        $this->assertSame(['Café molido 400 g', 'Gel antibacterial 500 ml'], $this->nombres($porcentaje));

        foreach (['arroz_blanco', '\\', '%%%'] as $q) {
            [$codigo, $cuerpo] = $this->productos(['q' => $q]);

            $this->assertSame(200, $codigo);
            $this->assertSame(0, $cuerpo['total'], "Búsqueda: '$q'");
        }
    }

    public function testUnaBusquedaSinResultadosRegresaUnaListaVacia(): void
    {
        [$codigo, $cuerpo] = $this->productos(['q' => 'producto que no existe']);

        $this->assertSame(200, $codigo);
        $this->assertSame([], $cuerpo['productos']);
        $this->assertSame(0, $cuerpo['total']);
        $this->assertSame(0, $cuerpo['paginas']);
    }

    public function testFiltraPorCategoriaYPagina(): void
    {
        $ID_Categoria = $this->idCategoria('Abarrotes');

        [, $primera] = $this->productos(['categoria' => $ID_Categoria]);
        [, $segunda] = $this->productos(['categoria' => $ID_Categoria, 'pagina' => 2]);

        $this->assertSame(['id' => $ID_Categoria, 'nombre' => 'Abarrotes', 'productos' => 15], $primera['categoria']);
        $this->assertSame(15, $primera['total']);
        $this->assertSame(2, $primera['paginas']);
        $this->assertCount(12, $primera['productos']);
        $this->assertCount(3, $segunda['productos']);
        $this->assertSame([], array_intersect($this->nombres($primera), $this->nombres($segunda)));

        foreach ([...$primera['productos'], ...$segunda['productos']] as $producto) {
            $this->assertSame($ID_Categoria, $producto['categoria']['id']);
        }
    }

    public function testSoloDisponiblesOcultaLosAgotados(): void
    {
        $agotados = (int) $this->valor('SELECT COUNT(*) FROM Producto WHERE Cantidad = 0');

        [, $cuerpo] = $this->productos(['disponibles' => 1, 'pagina' => 1]);
        [, $sinFiltro] = $this->productos(['disponibles' => 0]);

        $this->assertGreaterThan(0, $agotados);
        $this->assertSame(180 - $agotados, $cuerpo['total']);
        $this->assertSame(180, $sinFiltro['total']);
        $this->assertNotContains('agotado', array_column($cuerpo['productos'], 'disponibilidad'));
    }

    public function testOrdenaPorPrecio(): void
    {
        $ID_Categoria = $this->idCategoria('Bebidas');

        [, $baratos] = $this->productos(['categoria' => $ID_Categoria, 'orden' => 'precio_asc']);
        [, $caros] = $this->productos(['categoria' => $ID_Categoria, 'orden' => 'precio_desc']);

        $ascendentes = array_column($baratos['productos'], 'precio');
        $descendentes = array_column($caros['productos'], 'precio');
        $esperados = $ascendentes;
        sort($esperados);
        $this->assertSame($esperados, $ascendentes);
        $esperados = $descendentes;
        rsort($esperados);
        $this->assertSame($esperados, $descendentes);

        $extremos = $this->fila('SELECT MIN(Precio) AS Minimo, MAX(Precio) AS Maximo FROM Producto WHERE ID_Categoria = ?', [$ID_Categoria]);
        $this->assertEquals($extremos['Minimo'], $ascendentes[0]);
        $this->assertEquals($extremos['Maximo'], $descendentes[0]);
    }

    public function testUnaPaginaDespuesDeLaUltimaNoTieneProductos(): void
    {
        foreach ([16, PHP_INT_MAX] as $pagina) {
            [$codigo, $cuerpo] = $this->productos(['pagina' => $pagina]);

            $this->assertSame(200, $codigo);
            $this->assertSame([], $cuerpo['productos']);
            $this->assertSame(180, $cuerpo['total']);
        }
    }

    public function testRechazaParametrosInvalidos(): void
    {
        $casos = [
            [['categoria' => 'abc'], 'La categoría debe ser un número entero'],
            [['categoria' => 0], 'La categoría debe ser mayor o igual a 1'],
            [['pagina' => 0], 'La página debe ser mayor o igual a 1'],
            [['pagina' => '1.5'], 'La página debe ser un número entero'],
            [['orden' => 'costo'], 'Orden debe ser: nombre, precio_asc, precio_desc'],
            [['disponibles' => 'si'], 'Disponibles debe ser: 0, 1'],
            [['q' => str_repeat('a', 101)], 'La búsqueda debe ser un texto de 100 caracteres o menos'],
            [['q' => ['arroz']], 'La búsqueda debe ser un texto de 100 caracteres o menos'],
        ];

        foreach ($casos as [$parametros, $mensaje]) {
            [$codigo, $cuerpo] = $this->productos($parametros);

            $this->assertSame(400, $codigo, json_encode($parametros));
            $this->assertSame($mensaje, $cuerpo['mensaje']);
        }
    }

    public function testUnaCategoriaInexistenteResponde404(): void
    {
        [$codigo, $cuerpo] = $this->productos(['categoria' => 99999]);

        $this->assertSame(404, $codigo);
        $this->assertSame('Categoría no encontrada', $cuerpo['mensaje']);
    }

    public function testElDetalleTraeLosDatosDelProducto(): void
    {
        // N4: nombre, descripción, precio con IVA incluido, categoría y disponibilidad
        $ID_Producto = $this->idProducto('Azúcar estándar 1 kg');

        [$codigo, $cuerpo] = $this->cliente()->get("/api/tienda/producto?id=$ID_Producto");

        $this->assertSame(200, $codigo);
        $this->assertSame([
            'id' => $ID_Producto,
            'nombre' => 'Azúcar estándar 1 kg',
            'descripcion' => 'Azúcar de caña estándar para endulzar bebidas y preparar postres.',
            'precio' => 34, // JSON no distingue 34 de 34.0
            'unidad' => 'pieza',
            'existencia' => 50,
            'disponibilidad' => 'disponible',
            'categoria' => ['id' => $this->idCategoria('Abarrotes'), 'nombre' => 'Abarrotes'],
        ], $cuerpo['producto']);
    }

    public function testLaDisponibilidadSigueLaExistenciaYLaCantidadMinima(): void
    {
        // Chocolate: 0 piezas. Mayonesa: 3 piezas con mínimo de 5. Arroz: 60 piezas con mínimo de 10
        $casos = ['Chocolate de mesa 6 tablillas' => 'agotado', 'Mayonesa 390 g' => 'pocas_piezas', 'Arroz blanco 1 kg' => 'disponible'];

        foreach ($casos as $nombre => $disponibilidad) {
            [, $cuerpo] = $this->cliente()->get('/api/tienda/producto?id=' . $this->idProducto($nombre));

            $this->assertSame($disponibilidad, $cuerpo['producto']['disponibilidad'], $nombre);
        }
    }

    public function testUnProductoInexistenteODadoDeBajaResponde404(): void
    {
        // P14
        $ID_Producto = $this->idProducto('Arroz blanco 1 kg');
        BaseDatosPrueba::conexion()->exec("UPDATE Producto SET Activo = 0 WHERE ID_Producto = $ID_Producto");

        foreach ([99999, $ID_Producto] as $id) {
            [$codigo, $cuerpo] = $this->cliente()->get("/api/tienda/producto?id=$id");

            $this->assertSame(404, $codigo);
            $this->assertSame('Producto no encontrado', $cuerpo['mensaje']);
        }

        [, $lista] = $this->productos(['q' => 'arroz blanco']);
        $this->assertSame(0, $lista['total']);
    }

    public function testElDetalleRechazaUnIdInvalido(): void
    {
        foreach (['', '?id=abc', '?id=-3'] as $consulta) {
            [$codigo] = $this->cliente()->get("/api/tienda/producto$consulta");

            $this->assertSame(400, $codigo, $consulta);
        }
    }

    public function testLosDestacadosSonUnProductoDisponibleDeCadaCategoria(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->get('/api/tienda/destacados');

        $this->assertSame(200, $codigo);
        $this->assertCount(12, $cuerpo['productos']);
        $this->assertCount(12, array_unique(array_column(array_column($cuerpo['productos'], 'categoria'), 'id')));
        $this->assertNotContains('agotado', array_column($cuerpo['productos'], 'disponibilidad'));
    }

    // Orden que da MariaDB con la intercalación de la tabla (sin distinguir mayúsculas ni acentos)
    private function ordenarComoLaBaseDeDatos(array $nombres): array
    {
        $consulta = BaseDatosPrueba::conexion()->prepare('SELECT Nombre_Producto FROM Producto
            WHERE Nombre_Producto IN (' . implode(',', array_fill(0, count($nombres), '?')) . ')
            ORDER BY Nombre_Producto, ID_Producto');
        $consulta->execute($nombres);
        return $consulta->fetchAll(PDO::FETCH_COLUMN);
    }
}
