<?php
// Pruebas de surtidos: compras a proveedores que reabastecen el inventario (RF05)
// Datos demo usados: proveedor 1 (Distribuidora La Central), producto 6 Harina (8 pzas, mínimo 10, stock bajo),
// producto 8 Pasta (agotada, mínimo 15), producto 1 Arroz (40 pzas)

final class SurtidoTest extends ApiTestCase
{
    private ClienteApi $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->comoAdmin();
    }

    private function surtir(array $productos, int $proveedor = 1, string $notas = ''): array
    {
        $lista = [];
        foreach ($productos as $id => [$cantidad, $costo]) {
            $lista[] = ['ID_Producto' => $id, 'Cantidad' => $cantidad, 'Costo' => $costo];
        }
        return $this->admin->post('/api/createRestock', ['ID_Proveedor' => $proveedor, 'Notas' => $notas, 'productos' => $lista]);
    }

    public function testSurtirSumaInventarioYGuardaElCosto(): void
    {
        [$codigo, $cuerpo] = $this->surtir([6 => [12, 18.40], 8 => [30, 7.15]], 1, 'Factura A-123');

        $this->assertSame(201, $codigo, $cuerpo['mensaje'] ?? '');
        $this->assertSame(20, $this->existencia(6));
        $this->assertSame(30, $this->existencia(8));
        $this->assertSame('Disponible', $this->valor('SELECT Estado FROM Producto WHERE ID_Producto = 6'));
        $this->assertSame('Disponible', $this->valor('SELECT Estado FROM Producto WHERE ID_Producto = 8'));
        $this->assertSame('18.40', $this->valor('SELECT Costo FROM Producto WHERE ID_Producto = 6'));
        // El precio de venta no cambia
        $this->assertSame('24.90', $this->valor('SELECT Precio FROM Producto WHERE ID_Producto = 6'));
    }

    public function testElSurtidoGuardaTotalProveedorUsuarioYDetalle(): void
    {
        [, $cuerpo] = $this->surtir([6 => [12, 18.40], 8 => [30, 7.15]], 1, 'Factura A-123');
        $surtido = $cuerpo['surtido'];

        // 12 × 18.40 + 30 × 7.15 = 220.80 + 214.50
        $this->assertSame('435.30', $surtido['Monto']);
        $this->assertSame('Distribuidora La Central', $surtido['Nombre_Proveedor']);
        $this->assertSame('Usuario Administrador', $surtido['Registro']);
        $this->assertSame('Factura A-123', $surtido['Notas']);
        $this->assertCount(2, $surtido['productos']);
        $this->assertSame('220.80', $surtido['productos'][0]['Importe']);
    }

    public function testSiUnProductoFallaNoSeSurteNada(): void
    {
        $this->admin->peticion('DELETE', '/api/deleteProduct', ['ID_Producto' => 8]);

        [$codigo, $cuerpo] = $this->surtir([6 => [12, 18.40], 8 => [30, 7.15]]);

        $this->assertSame(409, $codigo);
        $this->assertStringContainsString('dado de baja', $cuerpo['mensaje']);
        $this->assertSame(8, $this->existencia(6));
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Surtir'));
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Detalle_Surtir'));
    }

    /** @return array<string, array{array, int, string}> */
    public static function surtidosInvalidos(): array
    {
        $uno = fn(array $cambios) => [array_merge(['ID_Producto' => 1, 'Cantidad' => 5, 'Costo' => 20], $cambios)];
        return [
            'cantidad cero' => [['ID_Proveedor' => 1, 'productos' => $uno(['Cantidad' => 0])], 400, 'Cantidad'],
            'costo negativo' => [['ID_Proveedor' => 1, 'productos' => $uno(['Costo' => -1])], 400, 'Costo'],
            'sin costo' => [['ID_Proveedor' => 1, 'productos' => [['ID_Producto' => 1, 'Cantidad' => 5]]], 400, 'Costo'],
            'sin productos' => [['ID_Proveedor' => 1, 'productos' => []], 400, 'no tiene productos'],
            'producto repetido' => [['ID_Proveedor' => 1, 'productos' => array_merge($uno([]), $uno([]))], 400, 'repetido'],
            'sin proveedor' => [['productos' => $uno([])], 400, 'Id proveedor'],
            'proveedor inexistente' => [['ID_Proveedor' => 999, 'productos' => $uno([])], 404, 'proveedor'],
            'producto inexistente' => [['ID_Proveedor' => 1, 'productos' => $uno(['ID_Producto' => 999])], 404, 'no existe'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('surtidosInvalidos')]
    public function testDatosInvalidos(array $datos, int $codigoEsperado, string $mensaje): void
    {
        [$codigo, $cuerpo] = $this->admin->post('/api/createRestock', $datos);

        $this->assertSame($codigoEsperado, $codigo);
        $this->assertStringContainsStringIgnoringCase($mensaje, $cuerpo['mensaje']);
        $this->assertSame(40, $this->existencia(1));
    }

    public function testNoSeSurteDeUnProveedorDadoDeBaja(): void
    {
        $this->admin->patch('/api/deleteSupplier', ['ID_Proveedor' => 1]);

        [$codigo] = $this->surtir([1 => [5, 20]]);

        $this->assertSame(409, $codigo);
    }

    public function testListaYDetalleDeSurtidos(): void
    {
        $this->surtir([6 => [12, 18.40]]);
        $this->surtir([1 => [10, 25.00]], 1, 'Segundo');

        [$codigo, $lista] = $this->admin->get('/api/getAllRestocks');
        [$codigoDetalle, $detalle] = $this->admin->get('/api/getRestock?id=' . $lista['surtidos'][0]['ID_Surtir']);

        $this->assertSame(200, $codigo);
        $this->assertCount(2, $lista['surtidos']);
        // El más reciente primero
        $this->assertSame('Segundo', $lista['surtidos'][0]['Notas']);
        $this->assertSame('1', (string) $lista['surtidos'][0]['Productos']);
        $this->assertSame('10', (string) $lista['surtidos'][0]['Unidades']);
        $this->assertSame(200, $codigoDetalle);
        $this->assertSame('Arroz 1 kg', $detalle['surtido']['productos'][0]['Nombre_Producto']);
    }

    public function testFiltroPorFechas(): void
    {
        $this->surtir([1 => [1, 10]]);
        BaseDatosPrueba::conexion()->exec("UPDATE Surtir SET Fecha = '2026-01-15'");

        [, $enero] = $this->admin->get('/api/getAllRestocks?desde=2026-01-01&hasta=2026-01-31');
        [, $febrero] = $this->admin->get('/api/getAllRestocks?desde=2026-02-01&hasta=2026-02-28');
        [$invertido] = $this->admin->get('/api/getAllRestocks?desde=2026-02-01&hasta=2026-01-01');
        [$malFormato] = $this->admin->get('/api/getAllRestocks?desde=15/01/2026');

        $this->assertCount(1, $enero['surtidos']);
        $this->assertCount(0, $febrero['surtidos']);
        $this->assertSame([400, 400], [$invertido, $malFormato]);
    }

    public function testSoloElAdminSurte(): void
    {
        $empleado = $this->comoEmpleado();

        [$crear] = $empleado->post('/api/createRestock', ['ID_Proveedor' => 1, 'productos' => [['ID_Producto' => 1, 'Cantidad' => 1, 'Costo' => 1]]]);
        [$listar] = $empleado->get('/api/getAllRestocks');
        [$leer] = $empleado->get('/api/getRestock?id=1');

        $this->assertSame([403, 403, 403], [$crear, $listar, $leer]);
        $this->assertSame(40, $this->existencia(1));
    }
}
