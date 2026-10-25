<?php
// Pruebas del pago y de los datos del ticket (RF04 · regla 8)
// Productos demo usados: 1 Arroz $32.50, 3 Aceite $45.90

final class TicketTest extends ApiTestCase
{
    private ClienteApi $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cajero = $this->comoEmpleado();
        $this->abrirCaja($this->cajero);
    }

    public function testGuardaPagoRecibidoCambioYCajero(): void
    {
        [$codigo, $cuerpo] = $this->vender($this->cajero, [3 => 1, 1 => 1], 'efectivo', null, 100);

        $this->assertSame(200, $codigo, $cuerpo['mensaje'] ?? '');
        $venta = $this->fila('SELECT Monto, Pago_Recibido, Cambio, ID_Usuario FROM Venta WHERE ID_Venta = ?', [$cuerpo['ID_Venta']]);
        $this->assertSame(['78.40', '100.00', '21.60', 2], [$venta['Monto'], $venta['Pago_Recibido'], $venta['Cambio'], (int) $venta['ID_Usuario']]);
    }

    public function testPagoInsuficienteEnEfectivoSeRechaza(): void
    {
        [$codigo, $cuerpo] = $this->vender($this->cajero, [3 => 1, 1 => 1], 'efectivo', null, 50);

        $this->assertSame(400, $codigo);
        $this->assertStringContainsString('no alcanza', $cuerpo['mensaje']);
        $this->assertSame(40, $this->existencia(1));
    }

    public function testConTarjetaElPagoEsElTotalExacto(): void
    {
        [, $cuerpo] = $this->cajero->patch('/api/completeSale', [
            'Monto' => 32.50,
            'Forma_Pago' => 'tarjeta',
            'productos' => [['ID_Producto' => 1, 'Cantidad' => 1]],
        ]);

        $venta = $this->fila('SELECT Pago_Recibido, Cambio FROM Venta WHERE ID_Venta = ?', [$cuerpo['ID_Venta']]);
        $this->assertSame(['32.50', '0.00'], [$venta['Pago_Recibido'], $venta['Cambio']]);
    }

    public function testLaRespuestaTraeLosDatosDelTicket(): void
    {
        [, $cuerpo] = $this->vender($this->cajero, [3 => 1, 1 => 2], 'efectivo', null, 200);
        $ticket = $cuerpo['venta'];

        $this->assertSame($cuerpo['ID_Venta'], $ticket['ID_Venta']);
        $this->assertSame('Empleado Demo', $ticket['Cajero']);
        $this->assertSame('110.90', $ticket['Monto']);
        $this->assertSame('89.10', $ticket['Cambio']);
        $this->assertSame('efectivo', $ticket['Forma_Pago']);
        $this->assertCount(2, $ticket['productos']);
        $this->assertSame('Aceite vegetal 1 L', $ticket['productos'][0]['Nombre_Producto']);
        $this->assertSame('65.00', $ticket['productos'][1]['Importe']);
        $this->assertSame('Abarrotes "La bamba"', $ticket['tienda']['Nombre_Tienda']);
        $this->assertSame('¡Gracias por su compra!', $ticket['tienda']['Mensaje_Ticket']);
    }

    public function testSePuedeReimprimirUnaVenta(): void
    {
        [, $venta] = $this->vender($this->cajero, [1 => 1]);

        [$codigo, $cuerpo] = $this->cajero->get('/api/getVenta?id=' . $venta['ID_Venta']);

        $this->assertSame(200, $codigo);
        $this->assertEquals($venta['venta'], $cuerpo['venta']);
    }

    public function testNoHayTicketDeVentasInexistentesOCanceladas(): void
    {
        [, $abierta] = $this->cajero->post('/api/openSale');
        $this->cajero->patch('/api/cancelSale');

        [$cancelada] = $this->cajero->get('/api/getVenta?id=' . $abierta['ID_Venta']);
        [$inexistente] = $this->cajero->get('/api/getVenta?id=999');

        $this->assertSame([404, 404], [$cancelada, $inexistente]);
    }

    public function testElTicketSeConservaSiElProductoSeDaDeBaja(): void
    {
        [, $venta] = $this->vender($this->cajero, [1 => 1]);
        $this->comoAdmin()->peticion('DELETE', '/api/deleteProduct', ['ID_Producto' => 1]);

        [$codigo, $cuerpo] = $this->cajero->get('/api/getVenta?id=' . $venta['ID_Venta']);

        $this->assertSame(200, $codigo);
        $this->assertSame('Arroz 1 kg', $cuerpo['venta']['productos'][0]['Nombre_Producto']);
    }
}
