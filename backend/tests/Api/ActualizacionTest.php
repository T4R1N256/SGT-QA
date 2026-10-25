<?php
// Actualización de una instalación existente (fase 6): la base de datos de la versión original,
// con sus datos, se actualiza al arrancar (seeder.php aplica las migraciones) sin perder nada
// Datos en tests/fixtures/datos_version_original.sql

final class ActualizacionTest extends ApiTestCase
{
    private const TABLAS = ['Usuario', 'Contacto_Empleado', 'Empleado', 'Categoria', 'Proveedor', 'Producto',
        'Caja', 'Venta', 'Detalle_Venta', 'Surtir', 'Detalle_Surtir'];

    private array $conteosAntes = [];
    private string $salidaSeeder = '';

    protected function setUp(): void
    {
        ServidorApi::url();

        $connection = BaseDatosPrueba::crearConEsquemaBase();
        BaseDatosPrueba::ejecutarArchivo($connection, __DIR__ . '/../fixtures/datos_version_original.sql');
        $this->conteosAntes = $this->conteos();

        // El arranque de la versión nueva: migraciones, usuario admin y datos demo (no se cargan: ya hay productos)
        $this->salidaSeeder = BaseDatosPrueba::ejecutarSeeder();
    }

    private function conteos(): array
    {
        $conteos = [];
        foreach (self::TABLAS as $tabla) {
            $conteos[$tabla] = (int) $this->valor("SELECT COUNT(*) FROM $tabla");
        }
        return $conteos;
    }

    public function testSeAplicanTodasLasMigracionesSinPerderRegistros(): void
    {
        $migraciones = array_map(fn($a) => basename($a, '.sql'), glob(__DIR__ . '/../../config/migrations/[0-9]*.sql'));
        $aplicadas = BaseDatosPrueba::conexion()->query('SELECT Version FROM Migracion ORDER BY Version')->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame($migraciones, $aplicadas);
        $this->assertSame($this->conteosAntes, $this->conteos());
        $this->assertStringContainsString('El usuario admin ya existe', $this->salidaSeeder);
        $this->assertStringContainsString('no se cargaron datos de demostración', $this->salidaSeeder);
    }

    public function testElDineroQuedaExactoAlPasarADecimal(): void
    {
        // En FLOAT, 10.10 era 10.1000003814...
        $this->assertSame('10.10', $this->valor('SELECT Precio FROM Producto WHERE ID_Producto = 1'));
        $this->assertSame('58.80', $this->valor('SELECT Monto FROM Venta WHERE ID_Venta = 1'));
        $this->assertSame('10.10', $this->valor('SELECT Precio_Unitario FROM Detalle_Venta WHERE ID_Detalle_Venta = 1'));
        $this->assertSame('191.80', $this->valor('SELECT Monto_Final_Sistema FROM Caja WHERE ID_Caja = 1'));
        $this->assertSame('252.50', $this->valor('SELECT Monto FROM Surtir WHERE ID_Surtir = 1'));
    }

    public function testLosTextosEscapadosRecuperanSuTextoOriginal(): void
    {
        $this->assertSame('Galletas "Marías"', $this->valor('SELECT Nombre_Producto FROM Producto WHERE ID_Producto = 1'));
        $this->assertSame('Lácteos & huevo', $this->valor('SELECT Nombre_Categoria FROM Categoria WHERE ID_Categoria = 2'));
        $this->assertSame('Pan & Cía', $this->valor('SELECT Nombre_Proveedor FROM Proveedor WHERE ID_Proveedor = 1'));
        $this->assertSame("María O'Connor", $this->valor('SELECT Nombre FROM Empleado WHERE ID_Empleado = 2'));
        $this->assertSame('Av. Juárez & 16 de Septiembre', $this->valor('SELECT Calle FROM Contacto_Empleado WHERE ID_Contacto_Empleado = 2'));
    }

    public function testExistenciasNegativasYSinStockQuedanAgotados(): void
    {
        $productos = BaseDatosPrueba::conexion()
            ->query('SELECT ID_Producto, CONCAT(Cantidad, " ", Estado, " ", Activo) FROM Producto ORDER BY ID_Producto')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assertSame([
            1 => '30 Disponible 1',
            2 => '4 Stock bajo 1',
            3 => '0 Agotado 1',
            4 => '0 Agotado 1', // tenía -2
        ], $productos);
    }

    public function testLasVentasAnterioresSeCompletanConSuCajero(): void
    {
        // La venta que quedó en proceso en una caja cerrada nunca se cobró
        $this->assertSame('cancelada', $this->valor('SELECT Estado FROM Venta WHERE ID_Venta = 4'));
        // El cajero de las ventas anteriores es quien abrió la caja
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Venta WHERE ID_Usuario IS NULL'));
        $this->assertSame('2', (string) $this->valor('SELECT ID_Usuario FROM Venta WHERE ID_Venta = 1'));
        // Los surtidos anteriores quedan sin usuario ni notas
        $this->assertSame('', $this->valor('SELECT Notas FROM Surtir WHERE ID_Surtir = 1'));
    }

    public function testLosUsuariosAnterioresInicianSesionConSuContrasena(): void
    {
        [$admin] = $this->cliente()->login(1, 'passadmin');
        [$cajera] = $this->cliente()->login(2, 'cajera2024');

        $this->assertSame(200, $admin);
        $this->assertSame(200, $cajera);
    }

    public function testHistorialYReportesFuncionanConLosDatosAnteriores(): void
    {
        $admin = $this->comoAdmin();

        [$codigo, $historial] = $admin->get('/api/getSalesHistory?desde=2026-03-01&hasta=2026-03-31');
        $this->assertSame(200, $codigo);
        $this->assertEquals(['ventas' => 3, 'total' => 133.8], $historial['resumen']);
        $this->assertSame("María O'Connor", $historial['ventas'][0]['Cajero']);

        [, $reporte] = $admin->get('/api/getSalesReport?desde=2026-03-01&hasta=2026-03-31');
        $this->assertEquals(133.8, $reporte['resumen']['total']);
        $this->assertSame(1, $reporte['resumen']['canceladas']);

        [, $cajas] = $admin->get('/api/getCashRegisters?desde=2026-03-01&hasta=2026-03-31');
        $this->assertCount(1, $cajas['cajas']);
        $this->assertSame('cuadrada', $cajas['cajas'][0]['Estado_Final']);
    }

    public function testLaTiendaTieneConfiguracionParaElTicket(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->get('/api/getConfig');

        $this->assertSame(200, $codigo);
        $this->assertSame('Abarrotes "La bamba"', $cuerpo['configuracion']['Nombre_Tienda']);
    }

    public function testArrancarOtraVezNoCambiaNada(): void
    {
        $antes = $this->conteos();

        $salida = BaseDatosPrueba::ejecutarSeeder();

        $this->assertSame($antes, $this->conteos());
        $this->assertStringNotContainsString('Migraciones aplicadas', $salida);
    }
}
