<?php
// Pruebas de productos (RF06, RF07 · regla 6 · bugs B5 y B9)

final class ProductosTest extends ApiTestCase
{
    private function datosProducto(array $cambios = []): array
    {
        return array_merge([
            'Nombre_Producto' => 'Producto de prueba',
            'Precio' => 12.5,
            'Cantidad' => 10,
            'Unidad_Medida' => 'pieza',
            'Cantidad_Minima' => 3,
            'ID_Categoria' => 1,
            'ID_Proveedor' => 1,
        ], $cambios);
    }

    private function ultimoProducto(): array
    {
        return $this->fila('SELECT * FROM Producto ORDER BY ID_Producto DESC LIMIT 1');
    }

    public function testSePuedeCrearConCeroExistencias(): void
    {
        [$codigo] = $this->comoAdmin()->post('/api/createProduct', $this->datosProducto(['Cantidad' => 0, 'Cantidad_Minima' => 0]));

        $this->assertSame(201, $codigo);
        $this->assertSame('Agotado', $this->ultimoProducto()['Estado']);
    }

    public function testElTextoSeGuardaTalCual(): void
    {
        $nombre = 'Pan & Cía <especial> "grande"';
        $this->comoAdmin()->post('/api/createProduct', $this->datosProducto(['Nombre_Producto' => $nombre]));

        $this->assertSame($nombre, $this->ultimoProducto()['Nombre_Producto']);
    }

    /** @return array<string, array{array, int}> */
    public static function productosInvalidos(): array
    {
        return [
            'cantidad negativa' => [['Cantidad' => -1], 400],
            'precio cero' => [['Precio' => 0], 400],
            'precio con texto' => [['Precio' => 'caro'], 400],
            'mínimo negativo' => [['Cantidad_Minima' => -2], 400],
            'sin nombre' => [['Nombre_Producto' => '   '], 400],
            'cantidad con decimales' => [['Cantidad' => 2.5], 400],
            'descripción de más de 500 caracteres' => [['Descripcion' => str_repeat('a', 501)], 400],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('productosInvalidos')]
    public function testDatosInvalidosResponden400(array $cambios, int $esperado): void
    {
        $antes = (int) $this->valor('SELECT COUNT(*) FROM Producto');

        [$codigo, $cuerpo] = $this->comoAdmin()->post('/api/createProduct', $this->datosProducto($cambios));

        $this->assertSame($esperado, $codigo);
        $this->assertNotEmpty($cuerpo['mensaje']);
        $this->assertSame($antes, (int) $this->valor('SELECT COUNT(*) FROM Producto'));
    }

    public function testModificarRecalculaElEstado(): void
    {
        [$codigo] = $this->comoAdmin()->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Cantidad' => 5]);

        $this->assertSame(200, $codigo);
        $this->assertSame('Stock bajo', $this->valor('SELECT Estado FROM Producto WHERE ID_Producto = 1'));
    }

    public function testGuardarSinCambiosNoRespondeNoEncontrado(): void
    {
        $admin = $this->comoAdmin();
        $admin->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Precio' => 40]);

        [$codigo] = $admin->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Precio' => 40]);

        $this->assertSame(200, $codigo);
    }

    public function testModificarConCantidadNegativaResponde400(): void
    {
        [$codigo] = $this->comoAdmin()->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Cantidad' => -5]);

        $this->assertSame(400, $codigo);
        $this->assertSame(40, $this->existencia(1));
    }

    public function testLaDescripcionEsOpcionalAlCrear(): void
    {
        // La descripción es la que ve el cliente en la tienda en línea (fase T1)
        $admin = $this->comoAdmin();

        $admin->post('/api/createProduct', $this->datosProducto());
        $this->assertSame('', $this->ultimoProducto()['Descripcion']);

        $admin->post('/api/createProduct', $this->datosProducto(['Descripcion' => '  Bolsa de 1 kg <nueva>  ']));
        $this->assertSame('Bolsa de 1 kg <nueva>', $this->ultimoProducto()['Descripcion']);
    }

    public function testModificarLaDescripcionLaCambiaLaConservaOLaBorra(): void
    {
        $admin = $this->comoAdmin();
        $descripcion = fn() => $this->valor('SELECT Descripcion FROM Producto WHERE ID_Producto = 1');

        $admin->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Descripcion' => 'Grano largo']);
        $this->assertSame('Grano largo', $descripcion());

        $admin->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Precio' => 40]);
        $this->assertSame('Grano largo', $descripcion());

        $admin->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Descripcion' => '']);
        $this->assertSame('', $descripcion());
    }

    public function testLaListaYElDetalleTraenLaDescripcion(): void
    {
        $admin = $this->comoAdmin();
        $admin->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Descripcion' => 'Grano largo']);

        [, $lista] = $admin->get('/api/getAllProducts');
        [, $detalle] = $admin->get('/api/getProduct?id=1');

        $producto = array_values(array_filter($lista['productos'], fn($p) => (int) $p['ID_Producto'] === 1))[0];
        $this->assertSame('Grano largo', $producto['Descripcion']);
        $this->assertSame('Grano largo', $detalle['producto']['Descripcion']);
    }

    public function testModificarProductoInexistenteResponde404(): void
    {
        [$codigo] = $this->comoAdmin()->patch('/api/modifyProduct', ['ID_Producto' => 999, 'Cantidad' => 5]);

        $this->assertSame(404, $codigo);
    }

    public function testEliminarEsUnaBajaLogica(): void
    {
        $admin = $this->comoAdmin();

        [$codigo] = $admin->peticion('DELETE', '/api/deleteProduct', ['ID_Producto' => 1]);
        [, $lista] = $admin->get('/api/getAllProducts');
        [$leer] = $admin->get('/api/getProduct?id=1');
        [$otraVez] = $admin->peticion('DELETE', '/api/deleteProduct', ['ID_Producto' => 1]);

        $this->assertSame(200, $codigo);
        $this->assertNotContains(1, array_map('intval', array_column($lista['productos'], 'ID_Producto')));
        $this->assertSame(404, $leer);
        $this->assertSame(404, $otraVez);
        // El registro se conserva para el historial
        $this->assertSame(0, (int) $this->valor('SELECT Activo FROM Producto WHERE ID_Producto = 1'));
    }

    public function testUnProductoDadoDeBajaNoSeVende(): void
    {
        $this->comoAdmin()->peticion('DELETE', '/api/deleteProduct', ['ID_Producto' => 1]);
        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero);

        [$codigo, $cuerpo] = $this->vender($cajero, [1 => 1]);

        $this->assertSame(409, $codigo);
        $this->assertStringContainsString('ya no está a la venta', $cuerpo['mensaje']);
    }

    public function testElEmpleadoNoEliminaProductos(): void
    {
        [$codigo] = $this->comoEmpleado()->peticion('DELETE', '/api/deleteProduct', ['ID_Producto' => 1]);

        $this->assertSame(403, $codigo);
    }

    public function testElEmpleadoNoModificaProductos(): void
    {
        [$codigo] = $this->comoEmpleado()->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Cantidad' => 100]);

        $this->assertSame(403, $codigo);
        $this->assertSame(40, $this->existencia(1));
    }
}
