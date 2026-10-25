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
        $this->assertSame('Sin stock', $this->ultimoProducto()['Estado']);
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

    public function testModificarProductoInexistenteResponde404(): void
    {
        [$codigo] = $this->comoAdmin()->patch('/api/modifyProduct', ['ID_Producto' => 999, 'Cantidad' => 5]);

        $this->assertSame(404, $codigo);
    }

    public function testElEmpleadoNoModificaProductos(): void
    {
        [$codigo] = $this->comoEmpleado()->patch('/api/modifyProduct', ['ID_Producto' => 1, 'Cantidad' => 100]);

        $this->assertSame(403, $codigo);
        $this->assertSame(40, $this->existencia(1));
    }
}
