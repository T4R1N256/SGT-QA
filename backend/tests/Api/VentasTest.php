<?php
// Pruebas de ventas (RF02, RF04, RF05, RF12 · reglas 1, 2, 6 y 9)
// Productos demo usados: 1 Arroz $32.50 (40 pzas), 3 Aceite $45.90 (18), 5 Sal $14.50 (12, mínimo 5), 8 Pasta (0)

final class VentasTest extends ApiTestCase
{
    private ClienteApi $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cajero = $this->comoEmpleado();
        $this->abrirCaja($this->cajero);
    }

    public function testVentaDescuentaInventarioYGuardaElTotalExacto(): void
    {
        [$codigo, $cuerpo] = $this->vender($this->cajero, [1 => 2, 3 => 1]);

        $this->assertSame(200, $codigo, $cuerpo['mensaje'] ?? '');
        $this->assertSame(38, $this->existencia(1));
        $this->assertSame(17, $this->existencia(3));

        $venta = $this->fila('SELECT * FROM Venta WHERE ID_Venta = ?', [$cuerpo['ID_Venta']]);
        $this->assertSame('finalizada', $venta['Estado']);
        $this->assertSame('110.90', $venta['Monto']);
        $this->assertSame('efectivo', $venta['Forma_Pago']);
        $this->assertSame(2, (int) $this->valor('SELECT COUNT(*) FROM Detalle_Venta WHERE ID_Venta = ?', [$cuerpo['ID_Venta']]));
    }

    public function testFechaYHoraSeGuardanAlCompletar(): void
    {
        [, $cuerpo] = $this->vender($this->cajero, [1 => 1]);

        $venta = $this->fila('SELECT Fecha, Hora FROM Venta WHERE ID_Venta = ?', [$cuerpo['ID_Venta']]);
        $this->assertNotSame('00:00:00', $venta['Hora']);
        // El backend trabaja con la hora de America/Mazatlan
        $this->assertSame((new DateTime('now', new DateTimeZone('America/Mazatlan')))->format('Y-m-d'), $venta['Fecha']);
    }

    public function testNoSeVendeMasQueLaExistencia(): void
    {
        [$codigo, $cuerpo] = $this->vender($this->cajero, [5 => 13]);

        $this->assertSame(409, $codigo);
        $this->assertStringContainsString('quedan 12', $cuerpo['mensaje']);
        $this->assertSame(12, $this->existencia(5));
    }

    public function testSiUnProductoFallaNoSeGuardaNadaDeLaVenta(): void
    {
        [$codigo] = $this->vender($this->cajero, [1 => 2, 8 => 1]);

        $this->assertSame(409, $codigo);
        $this->assertSame(40, $this->existencia(1));
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM Venta WHERE Estado = 'finalizada'"));
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Detalle_Venta'));
    }

    public function testUnTotalDistintoALosPreciosSeRechaza(): void
    {
        [$codigo] = $this->vender($this->cajero, [1 => 2], 'efectivo', 1.00);

        $this->assertSame(409, $codigo);
        $this->assertSame(40, $this->existencia(1));
    }

    public function testElMismoProductoRepetidoSeSuma(): void
    {
        [$codigo] = $this->cajero->patch('/api/completeSale', [
            'Monto' => 65.00,
            'Forma_Pago' => 'tarjeta',
            'productos' => [['ID_Producto' => 1, 'Cantidad' => 1], ['ID_Producto' => 1, 'Cantidad' => 1]],
        ]);

        $this->assertSame(200, $codigo);
        $this->assertSame(38, $this->existencia(1));
    }

    public function testElEstadoDelProductoSeActualiza(): void
    {
        $this->vender($this->cajero, [5 => 7]);
        $this->assertSame('Stock bajo', $this->valor('SELECT Estado FROM Producto WHERE ID_Producto = 5'));

        $this->vender($this->cajero, [5 => 5]);
        $this->assertSame('Sin stock', $this->valor('SELECT Estado FROM Producto WHERE ID_Producto = 5'));
    }

    /** @return array<string, array{array}> */
    public static function ventasInvalidas(): array
    {
        return [
            'cantidad cero' => [['Monto' => 0, 'Forma_Pago' => 'efectivo', 'productos' => [['ID_Producto' => 1, 'Cantidad' => 0]]]],
            'cantidad negativa' => [['Monto' => -32.5, 'Forma_Pago' => 'efectivo', 'productos' => [['ID_Producto' => 1, 'Cantidad' => -1]]]],
            'forma de pago desconocida' => [['Monto' => 32.5, 'Forma_Pago' => 'vales', 'productos' => [['ID_Producto' => 1, 'Cantidad' => 1]]]],
            'sin productos' => [['Monto' => 0, 'Forma_Pago' => 'efectivo', 'productos' => []]],
            'sin monto' => [['Forma_Pago' => 'efectivo', 'productos' => [['ID_Producto' => 1, 'Cantidad' => 1]]]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ventasInvalidas')]
    public function testDatosInvalidosResponden400(array $datos): void
    {
        [$codigo, $cuerpo] = $this->cajero->patch('/api/completeSale', $datos);

        $this->assertSame(400, $codigo);
        $this->assertFalse($cuerpo['ok']);
        $this->assertSame(40, $this->existencia(1));
    }

    public function testProductoInexistenteResponde404(): void
    {
        [$codigo] = $this->vender($this->cajero, [999 => 1], 'efectivo', 10.00);

        $this->assertSame(404, $codigo);
    }

    public function testSinCajaAbiertaNoHayVentas(): void
    {
        $this->cajero->patch('/api/closeCashRegister', ['Monto_Final' => 0]);

        [$codigoAbrir] = $this->cajero->post('/api/openSale');
        [$codigoCompletar] = $this->vender($this->cajero, [1 => 1]);

        $this->assertSame(409, $codigoAbrir);
        $this->assertSame(409, $codigoCompletar);
        $this->assertSame(40, $this->existencia(1));
    }

    public function testUsaLaVentaAbiertaConOpenSale(): void
    {
        [, $abierta] = $this->cajero->post('/api/openSale');
        [, $completada] = $this->vender($this->cajero, [1 => 1]);

        $this->assertSame($abierta['ID_Venta'], $completada['ID_Venta']);
        $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM Venta'));
    }

    public function testCompletarSinOpenSaleCreaLaVenta(): void
    {
        // Por ejemplo, si la sesión PHP se reinició con un carrito en pantalla
        [$codigo, $cuerpo] = $this->vender($this->cajero, [1 => 1]);

        $this->assertSame(200, $codigo);
        $this->assertSame('finalizada', $this->valor('SELECT Estado FROM Venta WHERE ID_Venta = ?', [$cuerpo['ID_Venta']]));
    }

    public function testAbrirOtraVentaCancelaLaAbandonada(): void
    {
        [, $primera] = $this->cajero->post('/api/openSale');
        [, $segunda] = $this->cajero->post('/api/openSale');

        $this->assertSame('cancelada', $this->valor('SELECT Estado FROM Venta WHERE ID_Venta = ?', [$primera['ID_Venta']]));
        $this->assertSame('enproceso', $this->valor('SELECT Estado FROM Venta WHERE ID_Venta = ?', [$segunda['ID_Venta']]));
    }

    public function testCancelarVenta(): void
    {
        [, $venta] = $this->cajero->post('/api/openSale');

        [$codigo] = $this->cajero->patch('/api/cancelSale');
        [$codigoOtraVez] = $this->cajero->patch('/api/cancelSale');

        $this->assertSame(200, $codigo);
        $this->assertSame(400, $codigoOtraVez);
        $this->assertSame('cancelada', $this->valor('SELECT Estado FROM Venta WHERE ID_Venta = ?', [$venta['ID_Venta']]));
        $this->assertSame(40, $this->existencia(1));
    }
}
