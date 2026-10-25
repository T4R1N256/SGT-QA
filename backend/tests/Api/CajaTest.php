<?php
// Pruebas de caja (RF09, RF10 · bugs B1, B4, B6, B8, B11)
// Productos demo usados: 1 Arroz $32.50, 3 Aceite $45.90, 11 Refresco $19.00

final class CajaTest extends ApiTestCase
{
    private function cerrar(ClienteApi $cliente, float $monto): array
    {
        return $cliente->patch('/api/closeCashRegister', ['Monto_Final' => $monto]);
    }

    public function testSePuedeAbrirYCerrarConCero(): void
    {
        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 0);

        [$codigo, $cuerpo] = $this->cerrar($cajero, 0);

        $this->assertSame(200, $codigo, $cuerpo['mensaje'] ?? '');
        $this->assertSame('cuadrada', $cuerpo['corte']['Estado_Final']);
    }

    public function testNoSePuedenAbrirDosCajas(): void
    {
        $this->abrirCaja($this->comoEmpleado(), 100);

        [$codigo] = $this->comoAdmin()->post('/api/openCashRegister', ['Monto_Inicial' => 50]);

        $this->assertSame(409, $codigo);
    }

    public function testQuienAbreEsElUsuarioDeLaSesion(): void
    {
        $this->comoEmpleado()->post('/api/openCashRegister', ['Monto_Inicial' => 100, 'ID_Usuario' => 1]);

        $this->assertSame(2, (int) $this->valor("SELECT ID_Usuario FROM Caja WHERE Estado = 'abierta'"));
    }

    public function testCuadraConCentavos(): void
    {
        // Con FLOAT, $45.90 se guardaba como 45.900001525 y esta caja salía descuadrada
        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 100.10);
        $this->vender($cajero, [3 => 1, 1 => 1]);

        [, $cuerpo] = $this->cerrar($cajero, 178.50);

        $this->assertSame('cuadrada', $cuerpo['corte']['Estado_Final']);
        $this->assertEquals(0, $cuerpo['corte']['Diferencia']);
        $this->assertSame('178.50', $this->valor('SELECT Monto_Final_Sistema FROM Caja'));
    }

    public function testElCuadreSoloCuentaEfectivo(): void
    {
        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 0);
        $this->vender($cajero, [11 => 1], 'efectivo');
        $this->vender($cajero, [1 => 1], 'tarjeta');
        $this->vender($cajero, [3 => 1], 'transferencia');

        [, $totales] = $cajero->get('/api/getMontoFinal');
        $this->assertEquals(19.00, $totales['totales']['efectivo']);
        $this->assertEquals(32.50, $totales['totales']['tarjeta']);
        $this->assertEquals(45.90, $totales['totales']['transferencia']);
        $this->assertEquals(97.40, $totales['totales']['total']);
        $this->assertSame(3, $totales['totales']['ventas']);
        $this->assertEquals(19.00, $totales['Efectivo_Esperado']);

        [, $cuerpo] = $this->cerrar($cajero, 19.00);
        $this->assertSame('cuadrada', $cuerpo['corte']['Estado_Final']);
    }

    public function testDescuadradaConDiferencia(): void
    {
        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 500);
        $this->vender($cajero, [1 => 2]);

        [, $cuerpo] = $this->cerrar($cajero, 560.00);

        $this->assertSame('descuadrada', $cuerpo['corte']['Estado_Final']);
        $this->assertEquals(-5.00, $cuerpo['corte']['Diferencia']);
        $this->assertEquals(565.00, $cuerpo['corte']['Efectivo_Esperado']);
    }

    public function testRegistraQuienCerro(): void
    {
        $this->abrirCaja($this->comoEmpleado(), 0);

        $this->cerrar($this->comoAdmin(), 0);

        $caja = $this->fila('SELECT ID_Usuario, ID_Usuario_Cierre, Estado FROM Caja');
        $this->assertSame([2, 1, 'cerrada'], [(int) $caja['ID_Usuario'], (int) $caja['ID_Usuario_Cierre'], $caja['Estado']]);
    }

    public function testCerrarCancelaLaVentaEnProceso(): void
    {
        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 0);
        [, $venta] = $cajero->post('/api/openSale');

        $this->cerrar($cajero, 0);

        $this->assertSame('cancelada', $this->valor('SELECT Estado FROM Venta WHERE ID_Venta = ?', [$venta['ID_Venta']]));
    }

    public function testLaCajaAbiertaSigueDisponibleEnOtraSesion(): void
    {
        // B8: antes la caja vivía en $_SESSION y una sesión nueva no podía vender
        $this->abrirCaja($this->comoEmpleado(), 0);
        $otraSesion = $this->comoEmpleado();

        [$codigoVenta] = $this->vender($otraSesion, [1 => 1]);
        [$codigoVentas, $cuerpo] = $otraSesion->get('/api/getAllVentas');

        $this->assertSame(200, $codigoVenta);
        $this->assertSame(200, $codigoVentas);
        $this->assertCount(1, $cuerpo['ventas']);
        $this->assertSame('efectivo', $cuerpo['ventas'][0]['Forma_Pago']);
    }

    public function testElLoginInformaLaCajaAbierta(): void
    {
        $this->abrirCaja($this->comoEmpleado(), 250);

        [, $cuerpo] = $this->cliente()->login(...self::ADMIN);

        $this->assertEquals(250, $cuerpo['caja']['Monto_Inicial']);
    }

    public function testElUltimoCorteEsLaCajaCerradaMasReciente(): void
    {
        $cajero = $this->comoEmpleado();
        [$sinCortes] = $cajero->get('/api/getCashRegister');
        $this->assertSame(404, $sinCortes);

        $this->abrirCaja($cajero, 100);
        $this->cerrar($cajero, 100);
        $this->abrirCaja($cajero, 200);
        $this->cerrar($cajero, 150);
        $this->abrirCaja($cajero, 300);

        [$codigo, $cuerpo] = $cajero->get('/api/getCashRegister');

        $this->assertSame(200, $codigo);
        $this->assertSame('200.00', $cuerpo['caja']['Monto_Inicial']);
        $this->assertSame('descuadrada', $cuerpo['caja']['Estado_Final']);
        $this->assertSame('Empleado Demo', $cuerpo['caja']['Nombre']);
    }

    public function testSinCajaAbiertaNoHayTotales(): void
    {
        $cajero = $this->comoEmpleado();

        [$codigoVentas] = $cajero->get('/api/getAllVentas');
        [$codigoTotales] = $cajero->get('/api/getMontoFinal');
        [$codigoCerrar] = $this->cerrar($cajero, 0);

        $this->assertSame([409, 409, 409], [$codigoVentas, $codigoTotales, $codigoCerrar]);
    }

    public function testMontosInvalidosResponden400(): void
    {
        $cajero = $this->comoEmpleado();

        [$negativo] = $cajero->post('/api/openCashRegister', ['Monto_Inicial' => -1]);
        [$texto] = $cajero->post('/api/openCashRegister', ['Monto_Inicial' => 'mil']);
        [$falta] = $cajero->post('/api/openCashRegister', []);

        $this->assertSame([400, 400, 400], [$negativo, $texto, $falta]);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Caja'));
    }
}
