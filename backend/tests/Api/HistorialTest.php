<?php
// Pruebas del historial de ventas y de cortes de caja (fase 4, solo admin)
// Escenario: el empleado abre caja con $100, vende 3 veces (2 en efectivo, 1 con tarjeta),
// abandona una venta (queda cancelada) y cierra contando $160.40 → la caja queda cuadrada
// Productos demo usados: 1 Arroz $32.50, 3 Aceite $45.90, 11 Refresco $19.00

final class HistorialTest extends ApiTestCase
{
    private ClienteApi $admin;
    private int $ID_Caja;

    protected function setUp(): void
    {
        parent::setUp();

        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 100);
        $this->vender($cajero, [1 => 1], 'efectivo');          // 32.50
        $this->vender($cajero, [3 => 1], 'tarjeta');           // 45.90
        $cajero->post('/api/openSale');
        $cajero->patch('/api/cancelSale');                      // cancelada
        $this->vender($cajero, [11 => 1, 1 => 1], 'efectivo'); // 51.50
        // 100 + 32.50 + 51.50 = 184.00 de efectivo esperado
        $cajero->patch('/api/closeCashRegister', ['Monto_Final' => 184.00]);

        $this->ID_Caja = (int) $this->valor('SELECT ID_Caja FROM Caja');
        $this->admin = $this->comoAdmin();
    }

    private function hoy(): string
    {
        return (new DateTime('now', new DateTimeZone('America/Mazatlan')))->format('Y-m-d');
    }

    public function testHistorialDeVentasFinalizadas(): void
    {
        [$codigo, $cuerpo] = $this->admin->get('/api/getSalesHistory');

        $this->assertSame(200, $codigo);
        $this->assertCount(3, $cuerpo['ventas']);
        $this->assertEquals(['ventas' => 3, 'total' => 129.9], $cuerpo['resumen']);
        $this->assertSame(1, $cuerpo['paginas']);
        // La más reciente primero
        $this->assertSame('51.50', $cuerpo['ventas'][0]['Monto']);
        $this->assertSame('2', (string) $cuerpo['ventas'][0]['Articulos']);
        $this->assertSame('Empleado Demo', $cuerpo['ventas'][0]['Cajero']);
        $this->assertSame($this->hoy(), $cuerpo['ventas'][0]['Fecha']);
    }

    public function testFiltroPorFormaDePago(): void
    {
        [, $efectivo] = $this->admin->get('/api/getSalesHistory?forma_pago=efectivo');
        [, $tarjeta] = $this->admin->get('/api/getSalesHistory?forma_pago=tarjeta');

        $this->assertEquals(['ventas' => 2, 'total' => 84.0], $efectivo['resumen']);
        $this->assertEquals(['ventas' => 1, 'total' => 45.9], $tarjeta['resumen']);
    }

    public function testFiltroPorEstado(): void
    {
        [, $canceladas] = $this->admin->get('/api/getSalesHistory?estado=cancelada');
        [, $todas] = $this->admin->get('/api/getSalesHistory?estado=todas');

        $this->assertCount(1, $canceladas['ventas']);
        $this->assertSame('cancelada', $canceladas['ventas'][0]['Estado']);
        // Una cancelada no suma al total y su cajero es quien abrió la caja
        $this->assertEquals(['ventas' => 1, 'total' => 0.0], $canceladas['resumen']);
        $this->assertSame('Empleado Demo', $canceladas['ventas'][0]['Cajero']);
        $this->assertSame(4, $todas['resumen']['ventas']);
    }

    public function testFiltroPorFechas(): void
    {
        BaseDatosPrueba::conexion()->exec("UPDATE Venta SET Fecha = '2026-01-15' WHERE Monto = 45.90");

        [, $enero] = $this->admin->get('/api/getSalesHistory?desde=2026-01-01&hasta=2026-01-31');
        [, $hoy] = $this->admin->get('/api/getSalesHistory?desde=' . $this->hoy() . '&hasta=' . $this->hoy());

        $this->assertEquals(['ventas' => 1, 'total' => 45.9], $enero['resumen']);
        $this->assertSame(2, $hoy['resumen']['ventas']);
    }

    public function testPaginacionDeVeinticincoEnVeinticinco(): void
    {
        $insertar = BaseDatosPrueba::conexion()->prepare("INSERT INTO Venta (Fecha, Hora, Monto, Estado, Forma_Pago, ID_Caja)
            VALUES (?, ?, 10, 'finalizada', 'efectivo', ?)");
        for ($i = 0; $i < 30; $i++) {
            $insertar->execute([$this->hoy(), sprintf('00:%02d:00', $i), $this->ID_Caja]);
        }

        [, $primera] = $this->admin->get('/api/getSalesHistory');
        [, $segunda] = $this->admin->get('/api/getSalesHistory?pagina=2');

        $this->assertSame(33, $primera['resumen']['ventas']);
        $this->assertSame(2, $primera['paginas']);
        $this->assertCount(25, $primera['ventas']);
        $this->assertCount(8, $segunda['ventas']);
        $this->assertSame([], array_intersect(
            array_column($primera['ventas'], 'ID_Venta'),
            array_column($segunda['ventas'], 'ID_Venta')
        ));
    }

    /** @return array<string, array{string}> */
    public static function filtrosInvalidos(): array
    {
        return [
            'forma de pago desconocida' => ['?forma_pago=vales'],
            'estado desconocido' => ['?estado=enproceso'],
            'página cero' => ['?pagina=0'],
            'fechas invertidas' => ['?desde=2026-02-01&hasta=2026-01-01'],
            'fecha mal escrita' => ['?desde=2026-13-01'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('filtrosInvalidos')]
    public function testFiltrosInvalidosResponden400(string $consulta): void
    {
        [$codigo] = $this->admin->get('/api/getSalesHistory' . $consulta);

        $this->assertSame(400, $codigo);
    }

    public function testHistorialDeCortes(): void
    {
        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 50);

        [$codigo, $cuerpo] = $this->admin->get('/api/getCashRegisters');

        $this->assertSame(200, $codigo);
        $this->assertCount(2, $cuerpo['cajas']);
        // La abierta (más reciente) primero
        $this->assertSame('abierta', $cuerpo['cajas'][0]['Estado']);
        $cerrada = $cuerpo['cajas'][1];
        $this->assertSame('cuadrada', $cerrada['Estado_Final']);
        $this->assertSame('184.00', $cerrada['Monto_Final_Sistema']);
        $this->assertSame('0.00', $cerrada['Diferencia']);
        $this->assertSame('Empleado Demo', $cerrada['Abrio']);
        $this->assertSame('Empleado Demo', $cerrada['Cerro']);
        $this->assertSame('3', (string) $cerrada['Ventas']);
        $this->assertSame('129.90', $cerrada['Total_Ventas']);
    }

    public function testDetalleDeUnCorte(): void
    {
        [$codigo, $cuerpo] = $this->admin->get('/api/getCashRegisterDetail?id=' . $this->ID_Caja);

        $this->assertSame(200, $codigo);
        $this->assertEquals(['efectivo' => 84, 'tarjeta' => 45.9, 'transferencia' => 0, 'total' => 129.9, 'ventas' => 3], $cuerpo['totales']);
        $this->assertEquals(184, $cuerpo['Efectivo_Esperado']);
        $this->assertSame(1, $cuerpo['canceladas']);
        $this->assertCount(3, $cuerpo['ventas']);
    }

    public function testDetalleDeUnaCajaAbiertaCalculaElEfectivoEsperado(): void
    {
        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 50);
        $this->vender($cajero, [11 => 2], 'efectivo');
        $abierta = (int) $this->valor("SELECT ID_Caja FROM Caja WHERE Estado = 'abierta'");

        [, $cuerpo] = $this->admin->get('/api/getCashRegisterDetail?id=' . $abierta);

        $this->assertEquals(88, $cuerpo['Efectivo_Esperado']);
        $this->assertNull($cuerpo['caja']['Monto_Final']);
    }

    public function testDetalleDeCajaInexistente(): void
    {
        [$codigo] = $this->admin->get('/api/getCashRegisterDetail?id=999');

        $this->assertSame(404, $codigo);
    }

    public function testSoloElAdminVeElHistorial(): void
    {
        $empleado = $this->comoEmpleado();

        [$ventas] = $empleado->get('/api/getSalesHistory');
        [$cajas] = $empleado->get('/api/getCashRegisters');
        [$detalle] = $empleado->get('/api/getCashRegisterDetail?id=' . $this->ID_Caja);

        $this->assertSame([403, 403, 403], [$ventas, $cajas, $detalle]);
    }
}
