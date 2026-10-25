<?php
// Pruebas de los reportes (fase 5, RF11; regla 5: solo admin)
// Escenario:
//  - El admin surte con Distribuidora La Central: 12 Harina a $18.40 y 30 Pasta a $7.15 = $435.30
//    y con Refrescos del Norte: 24 Refresco de cola a $12.00 y 2 Refresco de naranja a $11.50 = $311.00
//  - El empleado abre caja con $100 y vende: Arroz $32.50 (efectivo), 2 Refresco de cola + 1 Harina $62.90
//    (transferencia) y 3 Pasta $33.00 (efectivo); abandona una venta (cancelada) y cierra contando $165.00
//    cuando se esperaban $165.50 → caja descuadrada por -$0.50
// Ventas: 3, $128.40, 7 artículos. Compras: 2 surtidos, $746.30

final class ReportesTest extends ApiTestCase
{
    private ClienteApi $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->comoAdmin();

        $this->surtir(1, [6 => [12, 18.40], 8 => [30, 7.15]]);
        $this->surtir(2, [11 => [24, 12.00], 12 => [2, 11.50]]);

        $cajero = $this->comoEmpleado();
        $this->abrirCaja($cajero, 100);
        $this->vender($cajero, [1 => 1], 'efectivo');                // 32.50
        $this->vender($cajero, [11 => 2, 6 => 1], 'transferencia');  // 38.00 + 24.90 = 62.90
        $this->vender($cajero, [8 => 3], 'efectivo');                // 33.00
        $cajero->post('/api/openSale');
        $cajero->patch('/api/cancelSale');
        $cajero->patch('/api/closeCashRegister', ['Monto_Final' => 165.00]);
    }

    private function surtir(int $proveedor, array $productos): void
    {
        $lista = [];
        foreach ($productos as $id => [$cantidad, $costo]) {
            $lista[] = ['ID_Producto' => $id, 'Cantidad' => $cantidad, 'Costo' => $costo];
        }
        [$codigo, $cuerpo] = $this->admin->post('/api/createRestock', ['ID_Proveedor' => $proveedor, 'productos' => $lista]);
        $this->assertSame(201, $codigo, $cuerpo['mensaje'] ?? '');
    }

    private function hoy(): string
    {
        return (new DateTime('now', new DateTimeZone('America/Mazatlan')))->format('Y-m-d');
    }

    private function periodo(): string
    {
        return '?desde=2026-01-01&hasta=' . $this->hoy();
    }

    // Lleva al 10 de enero la venta del Arroz y el surtido de Refrescos del Norte
    private function moverAEnero(): void
    {
        BaseDatosPrueba::conexion()->exec("UPDATE Venta SET Fecha = '2026-01-10' WHERE Monto = 32.50");
        BaseDatosPrueba::conexion()->exec("UPDATE Surtir SET Fecha = '2026-01-10' WHERE ID_Proveedor = 2");
    }


    public function testReporteDeVentas(): void
    {
        [$codigo, $cuerpo] = $this->admin->get('/api/getSalesReport' . $this->periodo());

        $this->assertSame(200, $codigo);
        $this->assertSame('2026-01-01', $cuerpo['desde']);
        $this->assertEquals(
            ['ventas' => 3, 'total' => 128.4, 'articulos' => 7, 'promedio' => 42.8, 'canceladas' => 1],
            $cuerpo['resumen']
        );
        $this->assertEquals([
            ['Forma_Pago' => 'efectivo', 'Ventas' => 2, 'Total' => 65.5],
            ['Forma_Pago' => 'transferencia', 'Ventas' => 1, 'Total' => 62.9],
        ], $cuerpo['formas_pago']);
        $this->assertEquals([['Cajero' => 'Empleado Demo', 'Ventas' => 3, 'Total' => 128.4]], $cuerpo['cajeros']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cuerpo['generado']);
    }

    public function testProductosMasVendidosPorUnidades(): void
    {
        [, $cuerpo] = $this->admin->get('/api/getSalesReport' . $this->periodo());

        $productos = array_map(fn($p) => [$p['Nombre_Producto'], $p['Unidades'], $p['Importe']], $cuerpo['productos']);
        // Con las mismas unidades, primero el de mayor importe
        $this->assertEquals([
            ['Pasta para sopa 200 g', 3, 33.0],
            ['Refresco de cola 600 ml', 2, 38.0],
            ['Arroz 1 kg', 1, 32.5],
            ['Harina de maíz 1 kg', 1, 24.9],
        ], $productos);
    }

    public function testVentasPorDia(): void
    {
        $this->moverAEnero();

        [, $periodo] = $this->admin->get('/api/getSalesReport' . $this->periodo());
        [, $hoy] = $this->admin->get('/api/getSalesReport?desde=' . $this->hoy() . '&hasta=' . $this->hoy());

        $this->assertEquals([
            ['Fecha' => '2026-01-10', 'Ventas' => 1, 'Total' => 32.5],
            ['Fecha' => $this->hoy(), 'Ventas' => 2, 'Total' => 95.9],
        ], $periodo['dias']);
        $this->assertSame(2, $hoy['resumen']['ventas']);
        $this->assertEquals(95.9, $hoy['resumen']['total']);
    }

    public function testReporteDeSurtidos(): void
    {
        [$codigo, $cuerpo] = $this->admin->get('/api/getRestockReport' . $this->periodo());

        $this->assertSame(200, $codigo);
        $this->assertEquals(['surtidos' => 2, 'total' => 746.3, 'unidades' => 68, 'productos' => 4], $cuerpo['resumen']);
        $this->assertEquals([
            ['Proveedor' => 'Distribuidora La Central', 'Surtidos' => 1, 'Unidades' => 42, 'Total' => 435.3],
            ['Proveedor' => 'Refrescos del Norte', 'Surtidos' => 1, 'Unidades' => 26, 'Total' => 311.0],
        ], $cuerpo['proveedores']);
        $this->assertCount(2, $cuerpo['surtidos']);

        // Productos surtidos, del mayor gasto al menor, con su costo promedio por unidad
        $this->assertSame('Refresco de cola 600 ml', $cuerpo['productos'][0]['Nombre_Producto']);
        $this->assertSame(24, $cuerpo['productos'][0]['Unidades']);
        $this->assertEquals(288.0, $cuerpo['productos'][0]['Total']);
        $this->assertEquals(12.0, $cuerpo['productos'][0]['Costo_Promedio']);
        $this->assertSame('Refresco de naranja 600 ml', $cuerpo['productos'][3]['Nombre_Producto']);
    }

    public function testCostoPromedioConDistintosCostos(): void
    {
        $this->surtir(1, [6 => [8, 20.00]]); // Harina: 12 a 18.40 y 8 a 20.00 → 380.80 / 20

        [, $cuerpo] = $this->admin->get('/api/getRestockReport' . $this->periodo());
        $harina = array_values(array_filter($cuerpo['productos'], fn($p) => $p['ID_Producto'] === 6))[0];

        $this->assertSame(20, $harina['Unidades']);
        $this->assertEquals(380.8, $harina['Total']);
        $this->assertEquals(19.04, $harina['Costo_Promedio']);
    }

    public function testReporteDeStock(): void
    {
        [$codigo, $cuerpo] = $this->admin->get('/api/getStockReport');
        $resumen = $cuerpo['resumen'];

        $this->assertSame(200, $codigo);
        $this->assertSame(32, $resumen['productos']);
        $this->assertSame((int) $this->valor('SELECT SUM(Cantidad) FROM Producto'), $resumen['unidades']);
        $this->assertEquals((float) $this->valor('SELECT SUM(Cantidad * Precio) FROM Producto'), $resumen['valor_venta']);
        // A costo solo los que ya tienen costo: Harina 19 × 18.40 + Pasta 27 × 7.15 + Cola 70 × 12.00 + Naranja 22 × 11.50
        $this->assertEquals(1635.65, $resumen['valor_costo']);
        $this->assertSame(28, $resumen['sin_costo']);
        // Harina y Pasta salieron de stock bajo y de agotados con el surtido
        $this->assertSame(5, $resumen['stock_bajo']);
        $this->assertSame(3, $resumen['agotados']);
        $this->assertCount(32, $cuerpo['productos']);
    }

    public function testStockPorCategoria(): void
    {
        [, $cuerpo] = $this->admin->get('/api/getStockReport');
        $bebidas = array_values(array_filter($cuerpo['categorias'], fn($c) => $c['Categoria'] === 'Bebidas'))[0];

        $this->assertSame(4, $bebidas['Productos']);
        $this->assertSame(70 + 22 + 36 + 0, $bebidas['Unidades']);
        $this->assertEquals(840 + 253, $bebidas['Valor_Costo']);
        $this->assertSame(32, array_sum(array_column($cuerpo['categorias'], 'Productos')));

        // Ningún producto de Limpieza tiene costo: su valor a costo no se conoce
        $limpieza = array_values(array_filter($cuerpo['categorias'], fn($c) => $c['Categoria'] === 'Limpieza'))[0];
        $this->assertNull($limpieza['Valor_Costo']);
    }

    public function testProductosPorSurtirConCantidadSugerida(): void
    {
        [, $cuerpo] = $this->admin->get('/api/getStockReport');
        $porSurtir = $cuerpo['por_surtir'];

        // Primero los agotados, después los de menor existencia
        $this->assertSame(
            ['Chicles menta 10 piezas', 'Jugo de manzana 1 L', 'Pasta dental 100 ml', 'Cloro 1 L',
                'Cacahuates enchilados 100 g', 'Tortillas de harina 10 piezas', 'Leche deslactosada 1 L',
                'Refresco de naranja 600 ml'],
            array_column($porSurtir, 'Nombre_Producto')
        );
        $this->assertSame('Agotado', $porSurtir[0]['Estado']);
        $this->assertSame(20, $porSurtir[0]['Sugerido']); // mínimo 10 → llegar a 20
        $this->assertNull($porSurtir[0]['Costo_Estimado']);

        // Refresco de naranja: 22 de mínimo 24 → sugerido 48 - 22 = 26 a su último costo de $11.50
        $naranja = $porSurtir[7];
        $this->assertSame('Stock bajo', $naranja['Estado']);
        $this->assertSame(26, $naranja['Sugerido']);
        $this->assertEquals(299.0, $naranja['Costo_Estimado']);
        $this->assertSame('Refrescos del Norte', $naranja['Proveedor']);
    }

    public function testElStockNoIncluyeProductosDadosDeBajaPeroLasVentasSi(): void
    {
        $this->admin->peticion('DELETE', '/api/deleteProduct', ['ID_Producto' => 8]);

        [, $stock] = $this->admin->get('/api/getStockReport');
        [, $ventas] = $this->admin->get('/api/getSalesReport' . $this->periodo());

        $this->assertSame(31, $stock['resumen']['productos']);
        $this->assertNotContains(8, array_column($stock['productos'], 'ID_Producto'));
        $this->assertContains(8, array_column($ventas['productos'], 'ID_Producto'));
    }

    public function testResumenDelPeriodo(): void
    {
        [$codigo, $cuerpo] = $this->admin->get('/api/getSummaryReport' . $this->periodo());

        $this->assertSame(200, $codigo);
        $this->assertEquals(['ventas' => 3, 'total' => 128.4], $cuerpo['ventas']);
        $this->assertEquals(['surtidos' => 2, 'total' => 746.3], $cuerpo['compras']);
        $this->assertEquals(-617.9, $cuerpo['flujo']);
        $this->assertEquals(['cortes' => 1, 'descuadradas' => 1, 'diferencia' => -0.5], $cuerpo['cajas']);
    }

    public function testUtilidadEstimadaConElUltimoCosto(): void
    {
        [, $cuerpo] = $this->admin->get('/api/getSummaryReport' . $this->periodo());

        // Con costo: Cola 2 × (19.00 - 12.00) + Harina 24.90 - 18.40 + Pasta 3 × (11.00 - 7.15) = 32.05 sobre 95.90
        // El Arroz ($32.50) no tiene costo y no cuenta
        $this->assertEquals([
            'estimada' => 32.05,
            'importe_con_costo' => 95.9,
            'margen' => 33.4,
            'cobertura' => 74.7,
        ], $cuerpo['utilidad']);
    }

    public function testResumenPorDia(): void
    {
        $this->moverAEnero();

        [, $cuerpo] = $this->admin->get('/api/getSummaryReport' . $this->periodo());

        $this->assertEquals([
            ['Fecha' => '2026-01-10', 'Ventas' => 32.5, 'Compras' => 311.0, 'Flujo' => -278.5],
            ['Fecha' => $this->hoy(), 'Ventas' => 95.9, 'Compras' => 435.3, 'Flujo' => -339.4],
        ], $cuerpo['dias']);
    }

    public function testPeriodoSinMovimientos(): void
    {
        [, $ventas] = $this->admin->get('/api/getSalesReport?desde=2020-01-01&hasta=2020-01-31');
        [, $resumen] = $this->admin->get('/api/getSummaryReport?desde=2020-01-01&hasta=2020-01-31');

        $this->assertEquals(['ventas' => 0, 'total' => 0, 'articulos' => 0, 'promedio' => 0, 'canceladas' => 0], $ventas['resumen']);
        $this->assertSame([], $ventas['productos']);
        $this->assertNull($resumen['utilidad']['margen']);
        $this->assertNull($resumen['utilidad']['cobertura']);
        $this->assertSame([], $resumen['dias']);
    }

    public function testPorDefectoLosUltimos30Dias(): void
    {
        [, $cuerpo] = $this->admin->get('/api/getRestockReport');

        $hasta = new DateTime($this->hoy());
        $this->assertSame($hasta->format('Y-m-d'), $cuerpo['hasta']);
        $this->assertSame($hasta->modify('-30 days')->format('Y-m-d'), $cuerpo['desde']);
    }

    public function testRangoDeFechasInvalido(): void
    {
        [$alReves, $cuerpo] = $this->admin->get('/api/getSalesReport?desde=2026-02-01&hasta=2026-01-01');
        [$malFormato] = $this->admin->get('/api/getSummaryReport?desde=01/02/2026');

        $this->assertSame(400, $alReves);
        $this->assertStringContainsString('posterior', $cuerpo['mensaje']);
        $this->assertSame(400, $malFormato);
    }

    public function testSoloSeConsultanConGet(): void
    {
        [$codigo] = $this->admin->post('/api/getStockReport');

        $this->assertSame(405, $codigo);
    }

    public function testLosReportesSonSoloParaElAdministrador(): void
    {
        $empleado = $this->comoEmpleado();
        $anonimo = $this->cliente();

        foreach (['getSalesReport', 'getRestockReport', 'getStockReport', 'getSummaryReport'] as $ruta) {
            [$codigoEmpleado] = $empleado->get("/api/$ruta");
            [$codigoAnonimo] = $anonimo->get("/api/$ruta");
            $this->assertSame(403, $codigoEmpleado, $ruta);
            $this->assertSame(401, $codigoAnonimo, $ruta);
        }
    }
}
