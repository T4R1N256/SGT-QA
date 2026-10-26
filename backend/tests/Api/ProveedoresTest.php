<?php
// Pruebas de proveedores: alta, cambios, nombres repetidos, teléfono y estado
// Usa los datos demo: 6 proveedores (el 1 es Distribuidora La Central)

final class ProveedoresTest extends ApiTestCase
{
    private function proveedores(ClienteApi $cliente, bool $todos = false): array
    {
        [$codigo, $cuerpo] = $cliente->get('/api/getAllSuppliers' . ($todos ? '?todos=1' : ''));
        $this->assertSame(200, $codigo);
        return array_column($cuerpo['proveedores'], null, 'ID_Proveedor');
    }

    private function proveedor(int $ID_Proveedor): array
    {
        return $this->fila('SELECT Nombre_Proveedor, Telefono, Estado FROM Proveedor WHERE ID_Proveedor = ?', [$ID_Proveedor]);
    }

    private function crear(array $datos): array
    {
        return $this->comoAdmin()->post('/api/createSupplier', $datos);
    }

    public function testLaListaTraeTelefonoYEstado(): void
    {
        $central = $this->proveedores($this->comoAdmin())[1];

        $this->assertSame(
            ['ID_Proveedor' => 1, 'Nombre_Proveedor' => 'Distribuidora La Central', 'Telefono' => '6561000001', 'Estado' => 'activo'],
            $central
        );
    }

    public function testLosInactivosSoloVienenConTodos(): void
    {
        $admin = $this->comoAdmin();
        $admin->patch('/api/deleteSupplier', ['ID_Proveedor' => 2]);

        $this->assertArrayNotHasKey(2, $this->proveedores($admin));
        $this->assertSame('inactivo', $this->proveedores($admin, true)[2]['Estado']);
    }

    /** @return array<string, array{string}> */
    public static function telefonosValidos(): array
    {
        return [
            '10 dígitos' => ['6561234567'],
            '12 dígitos' => ['526561234567'],
            'vacío' => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('telefonosValidos')]
    public function testSeCreaConUnTelefonoValidoOSinTelefono(string $telefono): void
    {
        [$codigo, $cuerpo] = $this->crear(['Nombre_Proveedor' => 'Abarrotera del Sur', 'Telefono' => $telefono]);

        $this->assertSame(201, $codigo, $cuerpo['mensaje'] ?? '');
        $this->assertSame(
            ['Nombre_Proveedor' => 'Abarrotera del Sur', 'Telefono' => $telefono, 'Estado' => 'activo'],
            $this->fila("SELECT Nombre_Proveedor, Telefono, Estado FROM Proveedor WHERE Nombre_Proveedor = 'Abarrotera del Sur'")
        );
    }

    public function testSeCreaSinMandarTelefono(): void
    {
        [$codigo] = $this->crear(['Nombre_Proveedor' => 'Abarrotera del Sur']);

        $this->assertSame(201, $codigo);
        $this->assertSame('', $this->valor("SELECT Telefono FROM Proveedor WHERE Nombre_Proveedor = 'Abarrotera del Sur'"));
    }

    public function testUnTelefonoQueLlegaComoNumeroSeGuardaComoTexto(): void
    {
        [$codigo] = $this->crear(['Nombre_Proveedor' => 'Abarrotera del Sur', 'Telefono' => 6561234567]);

        $this->assertSame(201, $codigo);
        $this->assertSame('6561234567', $this->valor("SELECT Telefono FROM Proveedor WHERE Nombre_Proveedor = 'Abarrotera del Sur'"));
    }

    /** @return array<string, array{string}> */
    public static function telefonosInvalidos(): array
    {
        return [
            'letras' => ['hola'],
            '9 dígitos' => ['656123456'],
            '11 dígitos' => ['65612345678'],
            '13 dígitos' => ['5265612345678'],
            'con guiones' => ['656-123-4567'],
            'con espacios' => ['656 123 4567'],
            'con +' => ['+526561234567'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('telefonosInvalidos')]
    public function testElTelefonoDebeTener10O12Digitos(string $telefono): void
    {
        $antes = (int) $this->valor('SELECT COUNT(*) FROM Proveedor');

        [$codigo, $cuerpo] = $this->crear(['Nombre_Proveedor' => 'Abarrotera del Sur', 'Telefono' => $telefono]);

        $this->assertSame(400, $codigo);
        $this->assertSame('El teléfono debe tener 10 o 12 dígitos numéricos', $cuerpo['mensaje']);
        $this->assertSame($antes, (int) $this->valor('SELECT COUNT(*) FROM Proveedor'));
    }

    public function testAlModificarTambienSeValidaElTelefono(): void
    {
        [$codigo, $cuerpo] = $this->comoAdmin()->patch('/api/modifySupplier', ['ID_Proveedor' => 1, 'Telefono' => 'hola']);

        $this->assertSame(400, $codigo);
        $this->assertSame('El teléfono debe tener 10 o 12 dígitos numéricos', $cuerpo['mensaje']);
        $this->assertSame('6561000001', $this->proveedor(1)['Telefono']);
    }

    public function testSePuedeBorrarElTelefono(): void
    {
        [$codigo] = $this->comoAdmin()->patch('/api/modifySupplier', ['ID_Proveedor' => 1, 'Telefono' => '']);

        $this->assertSame(200, $codigo);
        $this->assertSame('', $this->proveedor(1)['Telefono']);
    }

    /** @return array<string, array{string}> */
    public static function nombresRepetidos(): array
    {
        return [
            'igual' => ['Distribuidora La Central'],
            'con otras mayúsculas' => ['distribuidora la central'],
            'sin acentos' => ['Lacteos del Valle'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nombresRepetidos')]
    public function testNoSeCreaUnProveedorConUnNombreQueYaExiste(string $nombre): void
    {
        $antes = (int) $this->valor('SELECT COUNT(*) FROM Proveedor');

        [$codigo, $cuerpo] = $this->crear(['Nombre_Proveedor' => $nombre, 'Telefono' => '6561234567']);

        $this->assertSame(409, $codigo);
        $this->assertMatchesRegularExpression('/^Ya existe un proveedor llamado «.+»$/u', $cuerpo['mensaje']);
        $this->assertSame('Nombre_Proveedor', $cuerpo['campo']);
        $this->assertSame($antes, (int) $this->valor('SELECT COUNT(*) FROM Proveedor'));
    }

    public function testElNombreDeUnInactivoTambienEstaOcupadoYSeSugiereActivarlo(): void
    {
        $admin = $this->comoAdmin();
        $admin->patch('/api/deleteSupplier', ['ID_Proveedor' => 2]);

        [$codigo, $cuerpo] = $admin->post('/api/createSupplier', ['Nombre_Proveedor' => 'Refrescos del Norte']);

        $this->assertSame(409, $codigo);
        $this->assertSame('Ya existe un proveedor llamado «Refrescos del Norte». Está inactivo: puedes volver a activarlo', $cuerpo['mensaje']);
    }

    public function testNoSePuedeRenombrarConElNombreDeOtro(): void
    {
        [$codigo, $cuerpo] = $this->comoAdmin()->patch('/api/modifySupplier', ['ID_Proveedor' => 2, 'Nombre_Proveedor' => 'Distribuidora La Central']);

        $this->assertSame(409, $codigo);
        $this->assertSame('Ya existe un proveedor llamado «Distribuidora La Central»', $cuerpo['mensaje']);
        $this->assertSame('Refrescos del Norte', $this->proveedor(2)['Nombre_Proveedor']);
    }

    public function testUnRepetidoDeVersionesAnterioresNoImpideCambiarElTelefono(): void
    {
        BaseDatosPrueba::conexion()->exec("INSERT INTO Proveedor (Nombre_Proveedor, Telefono, Estado) VALUES ('Distribuidora La Central', '', 'activo')");
        $repetido = (int) $this->valor('SELECT MAX(ID_Proveedor) FROM Proveedor');

        [$codigo, $cuerpo] = $this->comoAdmin()->patch('/api/modifySupplier', [
            'ID_Proveedor' => $repetido,
            'Nombre_Proveedor' => 'Distribuidora La Central',
            'Telefono' => '6567654321',
        ]);

        $this->assertSame(200, $codigo, $cuerpo['mensaje'] ?? '');
        $this->assertSame('6567654321', $this->proveedor($repetido)['Telefono']);
    }

    public function testSeDesactivaYSeVuelveAActivar(): void
    {
        $admin = $this->comoAdmin();

        [$desactivar, $cuerpo] = $admin->patch('/api/deleteSupplier', ['ID_Proveedor' => 2]);
        $this->assertSame(200, $desactivar);
        $this->assertSame('Proveedor desactivado correctamente', $cuerpo['mensaje']);
        $this->assertSame('inactivo', $this->proveedor(2)['Estado']);

        [$activar] = $admin->patch('/api/modifySupplier', ['ID_Proveedor' => 2, 'Estado' => 'activo']);
        $this->assertSame(200, $activar);
        $this->assertSame('activo', $this->proveedor(2)['Estado']);
        $this->assertArrayHasKey(2, $this->proveedores($admin));
    }

    /** @return array<string, array{string, array, int, string}> */
    public static function datosInvalidos(): array
    {
        return [
            'crear sin nombre' => ['post', ['/api/createSupplier', ['Nombre_Proveedor' => ' ']], 400, 'Nombre proveedor es obligatorio'],
            'crear con 256 caracteres' => ['post', ['/api/createSupplier', ['Nombre_Proveedor' => str_repeat('a', 256)]], 400, 'Nombre proveedor no puede tener más de 255 caracteres'],
            'estado inválido' => ['patch', ['/api/modifySupplier', ['ID_Proveedor' => 1, 'Estado' => 'borrado']], 400, 'Estado debe ser: activo, inactivo'],
            'modificar uno que no existe' => ['patch', ['/api/modifySupplier', ['ID_Proveedor' => 999, 'Nombre_Proveedor' => 'X']], 404, 'No se encontró el proveedor'],
            'desactivar uno que no existe' => ['patch', ['/api/deleteSupplier', ['ID_Proveedor' => 999]], 404, 'No se encontró el proveedor'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datosInvalidos')]
    public function testDatosInvalidos(string $metodo, array $peticion, int $esperado, string $mensaje): void
    {
        [$codigo, $cuerpo] = $this->comoAdmin()->$metodo(...$peticion);

        $this->assertSame($esperado, $codigo);
        $this->assertSame($mensaje, $cuerpo['mensaje']);
    }

    /** @return array<string, array{string, string, array}> */
    public static function cambios(): array
    {
        return [
            'crear' => ['post', '/api/createSupplier', ['Nombre_Proveedor' => 'Nuevo']],
            'modificar' => ['patch', '/api/modifySupplier', ['ID_Proveedor' => 1, 'Nombre_Proveedor' => 'Otro']],
            'desactivar' => ['patch', '/api/deleteSupplier', ['ID_Proveedor' => 1]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cambios')]
    public function testSoloElAdminCambiaProveedores(string $metodo, string $ruta, array $datos): void
    {
        [$sinSesion] = $this->cliente()->$metodo($ruta, $datos);
        [$empleado] = $this->comoEmpleado()->$metodo($ruta, $datos);

        $this->assertSame(401, $sinSesion);
        $this->assertSame(403, $empleado);
        $this->assertSame(
            ['Nombre_Proveedor' => 'Distribuidora La Central', 'Telefono' => '6561000001', 'Estado' => 'activo'],
            $this->proveedor(1)
        );
    }
}
