<?php
// Revisión del carrito en el servidor (fase T2): precios vigentes, existencias, bajas y desglose del IVA
// Usa el catálogo de prueba (SEED_TIENDA=1)

final class CarritoApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        ServidorApi::url();
        BaseDatosPrueba::crearConEsquemaBase();
        BaseDatosPrueba::ejecutarSeeder(['SEED_TIENDA' => '1', 'SEED_DEMO' => '0']);
    }

    private function id(string $nombre): int
    {
        return (int) $this->valor('SELECT ID_Producto FROM Producto WHERE Nombre_Producto = ?', [$nombre]);
    }

    private function revisar(array $productos): array
    {
        return $this->cliente()->post('/api/tienda/carrito/revisar', ['productos' => $productos]);
    }

    private function centavos(float|int $monto): int
    {
        return (int) round($monto * 100);
    }

    private function avisos(array $cuerpo, string $tipo): array
    {
        return array_values(array_filter($cuerpo['avisos'], fn($aviso) => $aviso['tipo'] === $tipo));
    }

    public function testCalculaImportesYTotalesConLosPreciosDeLaBaseDeDatos(): void
    {
        // N5/N8: Azúcar $34.00 × 3 + Arroz $32.50 × 2 = $167.00
        [$codigo, $cuerpo] = $this->revisar([
            ['id' => $this->id('Azúcar estándar 1 kg'), 'cantidad' => 3, 'precio' => 34],
            ['id' => $this->id('Arroz blanco 1 kg'), 'cantidad' => 2, 'precio' => 32.5],
        ]);

        $this->assertSame(200, $codigo);
        $this->assertSame([], $cuerpo['avisos']);
        $this->assertSame(['Azúcar estándar 1 kg', 'Arroz blanco 1 kg'], array_column($cuerpo['lineas'], 'nombre'));
        $this->assertEquals([102, 65], array_column($cuerpo['lineas'], 'importe'));
        $this->assertSame(
            ['id', 'nombre', 'unidad', 'precio', 'cantidad', 'existencia', 'disponibilidad', 'importe'],
            array_keys($cuerpo['lineas'][0])
        );

        // Subtotal = 167 ÷ 1.16 = 143.97 y el IVA es la diferencia
        $this->assertEquals(
            ['productos' => 2, 'piezas' => 5, 'subtotal' => 143.97, 'iva' => 23.03, 'tasa_iva' => 16, 'envio' => 0, 'total' => 167],
            $cuerpo['resumen']
        );
    }

    public function testElSubtotalMasElIvaDaSiempreElTotal(): void
    {
        $productos = BaseDatosPrueba::conexion()->query('SELECT ID_Producto FROM Producto WHERE Cantidad > 0 LIMIT 40')
            ->fetchAll(PDO::FETCH_COLUMN);

        [, $cuerpo] = $this->revisar(array_map(fn($id) => ['id' => (int) $id, 'cantidad' => 1], $productos));

        $resumen = $cuerpo['resumen'];
        $this->assertSame($this->centavos($resumen['total']), $this->centavos($resumen['subtotal']) + $this->centavos($resumen['iva']));
        $this->assertSame(
            $this->centavos(array_sum(array_column($cuerpo['lineas'], 'importe'))),
            $this->centavos($resumen['total'])
        );
        $this->assertEqualsWithDelta($resumen['total'] - $resumen['total'] / 1.16, $resumen['iva'], 0.01);
    }

    public function testLaTasaDeIVASeLeeDeLaConfiguracion(): void
    {
        BaseDatosPrueba::conexion()->exec('UPDATE Configuracion SET Tasa_IVA = 8 WHERE ID_Configuracion = 1');

        [, $cuerpo] = $this->revisar([['id' => $this->id('Azúcar estándar 1 kg'), 'cantidad' => 1]]);

        // 34 ÷ 1.08 = 31.48
        $this->assertEquals(8, $cuerpo['resumen']['tasa_iva']);
        $this->assertEquals(31.48, $cuerpo['resumen']['subtotal']);
        $this->assertEquals(2.52, $cuerpo['resumen']['iva']);
    }

    public function testUnaCantidadMayorALaExistenciaSeAjustaYSeAvisa(): void
    {
        // P1/P4: la mayonesa tiene 3 piezas
        [, $cuerpo] = $this->revisar([['id' => $this->id('Mayonesa 390 g'), 'cantidad' => 5]]);

        $this->assertSame(3, $cuerpo['lineas'][0]['cantidad']);
        $this->assertSame(3, $cuerpo['lineas'][0]['existencia']);
        $this->assertSame(
            'Solo hay 3 disponibles de «Mayonesa 390 g»; ajustamos tu cantidad a 3.',
            $this->avisos($cuerpo, 'ajustado')[0]['mensaje']
        );
        $this->assertEquals(127.5, $cuerpo['resumen']['total']);
    }

    public function testUnProductoAgotadoSeQuitaYSeAvisa(): void
    {
        $chocolate = $this->id('Chocolate de mesa 6 tablillas');

        [, $cuerpo] = $this->revisar([
            ['id' => $chocolate, 'cantidad' => 1],
            ['id' => $this->id('Arroz blanco 1 kg'), 'cantidad' => 1],
        ]);

        $this->assertSame(['Arroz blanco 1 kg'], array_column($cuerpo['lineas'], 'nombre'));
        $this->assertSame(
            [['id' => $chocolate, 'tipo' => 'agotado', 'mensaje' => '«Chocolate de mesa 6 tablillas» se agotó y se quitó de tu carrito.']],
            $cuerpo['avisos']
        );
    }

    public function testUnProductoDadoDeBajaOInexistenteSeQuitaYSeAvisa(): void
    {
        // P5: dado de baja después de agregarlo
        $arroz = $this->id('Arroz blanco 1 kg');
        BaseDatosPrueba::conexion()->exec("UPDATE Producto SET Activo = 0 WHERE ID_Producto = $arroz");

        [$codigo, $cuerpo] = $this->revisar([['id' => $arroz, 'cantidad' => 2], ['id' => 99999, 'cantidad' => 1]]);

        $this->assertSame(200, $codigo);
        $this->assertSame([], $cuerpo['lineas']);
        $this->assertSame([
            '«Arroz blanco 1 kg» ya no está a la venta y se quitó de tu carrito.',
            'Un producto de tu carrito ya no está a la venta y se quitó.',
        ], array_column($this->avisos($cuerpo, 'retirado'), 'mensaje'));
        $this->assertEquals(0, $cuerpo['resumen']['total']);
    }

    public function testUnPrecioQueCambioSeAvisaYSeUsaElVigente(): void
    {
        // P5: el cliente vio $30.00 y el precio actual es $34.00
        [, $cuerpo] = $this->revisar([['id' => $this->id('Azúcar estándar 1 kg'), 'cantidad' => 2, 'precio' => 30]]);

        $this->assertSame('El precio de «Azúcar estándar 1 kg» cambió de $30.00 a $34.00.', $this->avisos($cuerpo, 'precio')[0]['mensaje']);
        $this->assertEquals(68, $cuerpo['resumen']['total']);
    }

    public function testUnPrecioAlteradoEnElNavegadorNoCambiaElTotal(): void
    {
        // P11: el total siempre sale de la base de datos
        [, $cuerpo] = $this->revisar([['id' => $this->id('Azúcar estándar 1 kg'), 'cantidad' => 1, 'precio' => 0.01]]);

        $this->assertEquals(34, $cuerpo['lineas'][0]['precio']);
        $this->assertEquals(34, $cuerpo['resumen']['total']);
    }

    public function testUnCarritoVacioTieneTotalesEnCero(): void
    {
        [$codigo, $cuerpo] = $this->revisar([]);

        $this->assertSame(200, $codigo);
        $this->assertSame([], $cuerpo['lineas']);
        $this->assertEquals(['productos' => 0, 'piezas' => 0, 'subtotal' => 0, 'iva' => 0, 'tasa_iva' => 16, 'envio' => 0, 'total' => 0], $cuerpo['resumen']);
    }

    public function testRevisarNoApartaExistencias(): void
    {
        $azucar = $this->id('Azúcar estándar 1 kg');

        $this->revisar([['id' => $azucar, 'cantidad' => 10]]);

        $this->assertSame(50, $this->existencia($azucar));
    }

    /** @return array<string, array{mixed, string}> */
    public static function carritosInvalidos(): array
    {
        return [
            'sin lista' => [null, 'El carrito debe ser una lista de productos'],
            'objeto en lugar de lista' => [['a' => ['id' => 1, 'cantidad' => 1]], 'El carrito debe ser una lista de productos'],
            'línea que no es objeto' => [[5], 'Cada producto del carrito debe traer id y cantidad'],
            'sin id' => [[['cantidad' => 1]], 'El producto 1 es obligatorio'],
            'id con texto' => [[['id' => 'x', 'cantidad' => 1]], 'El producto 1 debe ser un número entero'],
            'cantidad cero' => [[['id' => 1, 'cantidad' => 0]], 'La cantidad del producto 1 debe ser mayor o igual a 1'],
            'cantidad negativa' => [[['id' => 1, 'cantidad' => -2]], 'La cantidad del producto 1 debe ser mayor o igual a 1'],
            'cantidad decimal' => [[['id' => 1, 'cantidad' => 2.5]], 'La cantidad del producto 1 debe ser un número entero'],
            'cantidad con texto' => [[['id' => 1, 'cantidad' => 'tres']], 'La cantidad del producto 1 debe ser un número entero'],
            'precio con texto' => [[['id' => 1, 'cantidad' => 1, 'precio' => 'gratis']], 'El precio del producto 1 debe ser una cantidad válida'],
            'producto repetido' => [[['id' => 1, 'cantidad' => 1], ['id' => 1, 'cantidad' => 2]], 'El producto 2 está repetido en el carrito'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('carritosInvalidos')]
    public function testRechazaCarritosInvalidos(mixed $productos, string $mensaje): void
    {
        [$codigo, $cuerpo] = $this->cliente()->post('/api/tienda/carrito/revisar', $productos === null ? [] : ['productos' => $productos]);

        $this->assertSame(400, $codigo);
        $this->assertSame($mensaje, $cuerpo['mensaje']);
    }

    public function testRechazaMasDeCienProductosDistintos(): void
    {
        [$codigo, $cuerpo] = $this->revisar(array_map(fn($id) => ['id' => $id, 'cantidad' => 1], range(1, 101)));

        $this->assertSame(400, $codigo);
        $this->assertSame('El carrito no puede tener más de 100 productos distintos', $cuerpo['mensaje']);
    }

    public function testSoloAceptaPost(): void
    {
        [$codigo] = $this->cliente()->get('/api/tienda/carrito/revisar');

        $this->assertSame(405, $codigo);
    }
}
