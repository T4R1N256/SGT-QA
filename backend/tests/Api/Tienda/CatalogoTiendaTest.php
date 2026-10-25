<?php
// Catálogo de prueba de la tienda (SEED_TIENDA=1): amplio, completo y con existencias variadas (R19)

final class CatalogoTiendaTest extends ApiTestCase
{
    private string $salida = '';

    protected function setUp(): void
    {
        ServidorApi::url();
        BaseDatosPrueba::crearConEsquemaBase();
        $this->salida = BaseDatosPrueba::ejecutarSeeder(['SEED_TIENDA' => '1', 'SEED_DEMO' => '0']);
    }

    public function testCargaDoceCategoriasConQuinceProductosCadaUna(): void
    {
        $porCategoria = BaseDatosPrueba::conexion()->query('SELECT c.Nombre_Categoria, COUNT(*) FROM Producto p
            INNER JOIN Categoria c ON c.ID_Categoria = p.ID_Categoria GROUP BY c.ID_Categoria')->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assertCount(12, $porCategoria);
        $this->assertSame([15], array_values(array_unique(array_map('intval', $porCategoria))));
        $this->assertStringContainsString('12 categorías y 180 productos', $this->salida);
    }

    public function testCadaProductoTieneDescripcionPrecioYProveedor(): void
    {
        $incompletos = (int) $this->valor("SELECT COUNT(*) FROM Producto
            WHERE Descripcion = '' OR Precio <= 0 OR ID_Proveedor IS NULL OR Activo <> 1");
        $repetidos = (int) $this->valor('SELECT COUNT(*) - COUNT(DISTINCT Nombre_Producto) FROM Producto');

        $this->assertSame(0, $incompletos);
        $this->assertSame(0, $repetidos);
    }

    public function testHayAgotadosYPocasPiezasEnCadaCategoria(): void
    {
        $estados = BaseDatosPrueba::conexion()->query("SELECT ID_Categoria,
            SUM(Estado = 'Agotado') AS Agotados, SUM(Estado = 'Stock bajo') AS Bajos
            FROM Producto GROUP BY ID_Categoria")->fetchAll();

        foreach ($estados as $categoria) {
            $this->assertGreaterThanOrEqual(1, (int) $categoria['Agotados'], "Categoría {$categoria['ID_Categoria']}");
            $this->assertGreaterThanOrEqual(1, (int) $categoria['Bajos'], "Categoría {$categoria['ID_Categoria']}");
        }
        // El estado guardado coincide con la existencia
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM Producto WHERE (Cantidad = 0) <> (Estado = 'Agotado')"));
    }

    public function testCreaUnClienteDePruebaQuePuedeIngresar(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->post('/api/tienda/ingresar', ['correo' => 'cliente@ejemplo.mx', 'password' => 'prueba123']);

        $this->assertSame(200, $codigo);
        $this->assertSame('Cliente', $cuerpo['cliente']['nombre']);
        $this->assertStringContainsString('Cliente de prueba de la tienda: cliente@ejemplo.mx / prueba123', $this->salida);
    }

    public function testNoSeVuelveACargarNiSeMezclaConLosDatosDemo(): void
    {
        $salida = BaseDatosPrueba::ejecutarSeeder(['SEED_TIENDA' => '1', 'SEED_DEMO' => '1']);

        $this->assertSame(180, (int) $this->valor('SELECT COUNT(*) FROM Producto'));
        $this->assertStringContainsString('no se cargó el catálogo de la tienda', $salida);
        $this->assertStringContainsString('no se cargaron datos de demostración', $salida);
    }
}
