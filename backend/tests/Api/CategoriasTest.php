<?php
// Pruebas de categorías: alta, cambios, nombres repetidos, estado y desactivar con productos activos
// Usa los datos demo: 7 categorías (la 1 es Abarrotes) con productos

final class CategoriasTest extends ApiTestCase
{
    private function categorias(ClienteApi $cliente, bool $todas = false): array
    {
        [$codigo, $cuerpo] = $cliente->get('/api/getAllCategories' . ($todas ? '?todas=1' : ''));
        $this->assertSame(200, $codigo);
        return array_column($cuerpo['categorias'], null, 'ID_Categoria');
    }

    private function estado(int $ID_Categoria): string
    {
        return $this->valor('SELECT Estado FROM Categoria WHERE ID_Categoria = ?', [$ID_Categoria]);
    }

    private function productosActivos(int $ID_Categoria): int
    {
        return (int) $this->valor('SELECT COUNT(*) FROM Producto WHERE ID_Categoria = ? AND Activo = 1', [$ID_Categoria]);
    }

    private function crearVacia(ClienteApi $admin, string $nombre = 'Temporada'): int
    {
        [$codigo] = $admin->post('/api/createCategory', ['Nombre_Categoria' => $nombre]);
        $this->assertSame(201, $codigo);
        return (int) $this->valor('SELECT ID_Categoria FROM Categoria WHERE Nombre_Categoria = ?', [$nombre]);
    }

    public function testLaListaTraeEstadoYProductosActivos(): void
    {
        $admin = $this->comoAdmin();
        // Un producto dado de baja no cuenta
        BaseDatosPrueba::conexion()->exec('UPDATE Producto SET Activo = 0 WHERE ID_Producto = (SELECT MIN(ID_Producto) FROM Producto WHERE ID_Categoria = 1)');

        $abarrotes = $this->categorias($admin)[1];

        $this->assertSame(['ID_Categoria', 'Nombre_Categoria', 'Estado', 'Productos'], array_keys($abarrotes));
        $this->assertSame('activo', $abarrotes['Estado']);
        $this->assertSame($this->productosActivos(1), $abarrotes['Productos']);
        $this->assertGreaterThan(0, $abarrotes['Productos']);
    }

    public function testLasInactivasSoloVienenConTodas(): void
    {
        $admin = $this->comoAdmin();
        $id = $this->crearVacia($admin);
        $admin->patch('/api/deleteCategory', ['ID_Categoria' => $id]);

        $this->assertArrayNotHasKey($id, $this->categorias($admin));
        $this->assertSame('inactivo', $this->categorias($admin, true)[$id]['Estado']);
        // El empleado también las lee (Inventario), pero solo las activas por defecto
        $this->assertArrayNotHasKey($id, $this->categorias($this->comoEmpleado()));
    }

    public function testCrearGuardaElNombreRecortado(): void
    {
        $admin = $this->comoAdmin();

        [$codigo, $cuerpo] = $admin->post('/api/createCategory', ['Nombre_Categoria' => '  Mascotas & más  ']);

        $this->assertSame(201, $codigo, $cuerpo['mensaje'] ?? '');
        $this->assertSame('activo', $this->valor("SELECT Estado FROM Categoria WHERE Nombre_Categoria = 'Mascotas & más'"));
    }

    /** @return array<string, array{string}> */
    public static function nombresRepetidos(): array
    {
        return [
            'igual' => ['Abarrotes'],
            'con otras mayúsculas' => ['ABARROTES'],
            'sin acentos' => ['Lacteos y huevo'],
            'con espacios' => ['  Bebidas  '],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nombresRepetidos')]
    public function testNoSeCreaUnaCategoriaConUnNombreQueYaExiste(string $nombre): void
    {
        $antes = (int) $this->valor('SELECT COUNT(*) FROM Categoria');

        [$codigo, $cuerpo] = $this->comoAdmin()->post('/api/createCategory', ['Nombre_Categoria' => $nombre]);

        $this->assertSame(409, $codigo);
        $this->assertMatchesRegularExpression('/^Ya existe una categoría llamada «.+»$/u', $cuerpo['mensaje']);
        $this->assertSame('Nombre_Categoria', $cuerpo['campo']);
        $this->assertSame($antes, (int) $this->valor('SELECT COUNT(*) FROM Categoria'));
    }

    public function testElNombreDeUnaInactivaTambienEstaOcupadoYSeSugiereActivarla(): void
    {
        $admin = $this->comoAdmin();
        $id = $this->crearVacia($admin, 'Temporada');
        $admin->patch('/api/deleteCategory', ['ID_Categoria' => $id]);

        [$codigo, $cuerpo] = $admin->post('/api/createCategory', ['Nombre_Categoria' => 'temporada']);

        $this->assertSame(409, $codigo);
        $this->assertSame('Ya existe una categoría llamada «Temporada». Está inactiva: puedes volver a activarla', $cuerpo['mensaje']);
    }

    public function testNoSePuedeRenombrarConElNombreDeOtra(): void
    {
        [$codigo, $cuerpo] = $this->comoAdmin()->patch('/api/modifyCategory', ['ID_Categoria' => 2, 'Nombre_Categoria' => 'abarrotes']);

        $this->assertSame(409, $codigo);
        $this->assertSame('Ya existe una categoría llamada «Abarrotes»', $cuerpo['mensaje']);
        $this->assertSame('Bebidas', $this->valor('SELECT Nombre_Categoria FROM Categoria WHERE ID_Categoria = 2'));
    }

    public function testSePuedeCambiarLaEscrituraDeSuPropioNombre(): void
    {
        [$codigo] = $this->comoAdmin()->patch('/api/modifyCategory', ['ID_Categoria' => 1, 'Nombre_Categoria' => 'ABARROTES']);

        $this->assertSame(200, $codigo);
        $this->assertSame('ABARROTES', $this->valor('SELECT Nombre_Categoria FROM Categoria WHERE ID_Categoria = 1'));
    }

    public function testUnRepetidoDeVersionesAnterioresNoImpideCambiarElEstado(): void
    {
        // Antes se aceptaban nombres repetidos
        BaseDatosPrueba::conexion()->exec("INSERT INTO Categoria (Nombre_Categoria, Estado) VALUES ('Abarrotes', 'activo')");
        $repetida = (int) $this->valor('SELECT MAX(ID_Categoria) FROM Categoria');

        [$codigo, $cuerpo] = $this->comoAdmin()->patch('/api/modifyCategory', [
            'ID_Categoria' => $repetida,
            'Nombre_Categoria' => 'Abarrotes',
            'Estado' => 'inactivo',
        ]);

        $this->assertSame(200, $codigo, $cuerpo['mensaje'] ?? '');
        $this->assertSame('inactivo', $this->estado($repetida));
    }

    public function testDesactivarUnaCategoriaConProductosActivosSeBloqueaSinConfirmacion(): void
    {
        $admin = $this->comoAdmin();
        $productos = $this->productosActivos(1);

        [$codigo, $cuerpo] = $admin->patch('/api/deleteCategory', ['ID_Categoria' => 1]);

        $this->assertSame(409, $codigo);
        $this->assertTrue($cuerpo['requiere_confirmacion']);
        $this->assertSame($productos, $cuerpo['productos']);
        $this->assertSame(
            "«Abarrotes» tiene $productos productos activos. Si la desactivas, la tienda en línea deja de mostrarlos. Confirma para desactivarla.",
            $cuerpo['mensaje']
        );
        $this->assertSame('activo', $this->estado(1));
    }

    /** @return array<string, array{mixed}> */
    public static function confirmacionesQueNoValen(): array
    {
        return [
            'falso' => [false],
            'texto' => ['true'],
            'uno' => [1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('confirmacionesQueNoValen')]
    public function testSoloTrueConfirma(mixed $confirmar): void
    {
        [$codigo] = $this->comoAdmin()->patch('/api/deleteCategory', ['ID_Categoria' => 1, 'Confirmar' => $confirmar]);

        $this->assertSame(409, $codigo);
        $this->assertSame('activo', $this->estado(1));
    }

    public function testConConfirmacionSeDesactivaYLaTiendaDejaDeMostrarla(): void
    {
        $admin = $this->comoAdmin();
        $tienda = $this->cliente();
        [, $antes] = $tienda->get('/api/tienda/categorias');
        $this->assertContains('Abarrotes', array_column($antes['categorias'], 'nombre'));

        [$codigo, $cuerpo] = $admin->patch('/api/deleteCategory', ['ID_Categoria' => 1, 'Confirmar' => true]);

        $this->assertSame(200, $codigo);
        $this->assertSame('Categoría desactivada correctamente', $cuerpo['mensaje']);
        $this->assertSame('inactivo', $this->estado(1));
        [, $despues] = $tienda->get('/api/tienda/categorias');
        $this->assertNotContains('Abarrotes', array_column($despues['categorias'], 'nombre'));
        // Los productos no se tocan: siguen activos en SGT
        $this->assertGreaterThan(0, $this->productosActivos(1));
    }

    public function testCambiarElEstadoAInactivoDesdeEditarTambienPideConfirmacion(): void
    {
        $admin = $this->comoAdmin();

        [$sin] = $admin->patch('/api/modifyCategory', ['ID_Categoria' => 1, 'Estado' => 'inactivo']);
        [$con] = $admin->patch('/api/modifyCategory', ['ID_Categoria' => 1, 'Estado' => 'inactivo', 'Confirmar' => true]);

        $this->assertSame(409, $sin);
        $this->assertSame(200, $con);
        $this->assertSame('inactivo', $this->estado(1));
    }

    public function testUnaCategoriaSinProductosActivosSeDesactivaSinConfirmacion(): void
    {
        $admin = $this->comoAdmin();
        $vacia = $this->crearVacia($admin);
        // Con todos sus productos dados de baja tampoco pide confirmación
        BaseDatosPrueba::conexion()->exec('UPDATE Producto SET Activo = 0 WHERE ID_Categoria = 2');

        [$codigoVacia] = $admin->patch('/api/deleteCategory', ['ID_Categoria' => $vacia]);
        [$codigoBajas] = $admin->patch('/api/deleteCategory', ['ID_Categoria' => 2]);

        $this->assertSame(200, $codigoVacia);
        $this->assertSame(200, $codigoBajas);
        $this->assertSame('inactivo', $this->estado($vacia));
        $this->assertSame('inactivo', $this->estado(2));
    }

    public function testDesactivarUnaYaInactivaNoPideConfirmacion(): void
    {
        $admin = $this->comoAdmin();
        $admin->patch('/api/deleteCategory', ['ID_Categoria' => 1, 'Confirmar' => true]);

        [$codigo] = $admin->patch('/api/deleteCategory', ['ID_Categoria' => 1]);

        $this->assertSame(200, $codigo);
    }

    public function testSeVuelveAActivarYLaTiendaLaMuestraOtraVez(): void
    {
        $admin = $this->comoAdmin();
        $admin->patch('/api/deleteCategory', ['ID_Categoria' => 1, 'Confirmar' => true]);

        [$codigo] = $admin->patch('/api/modifyCategory', ['ID_Categoria' => 1, 'Estado' => 'activo']);

        $this->assertSame(200, $codigo);
        $this->assertSame('activo', $this->estado(1));
        $this->assertArrayHasKey(1, $this->categorias($admin));
        [, $tienda] = $this->cliente()->get('/api/tienda/categorias');
        $this->assertContains('Abarrotes', array_column($tienda['categorias'], 'nombre'));
    }

    /** @return array<string, array{string, array, int, string}> */
    public static function datosInvalidos(): array
    {
        return [
            'crear sin nombre' => ['post', ['/api/createCategory', ['Nombre_Categoria' => '   ']], 400, 'Nombre categoria es obligatorio'],
            'crear con 51 caracteres' => ['post', ['/api/createCategory', ['Nombre_Categoria' => str_repeat('a', 51)]], 400, 'Nombre categoria no puede tener más de 50 caracteres'],
            'estado inválido' => ['patch', ['/api/modifyCategory', ['ID_Categoria' => 1, 'Estado' => 'borrado']], 400, 'Estado debe ser: activo, inactivo'],
            'modificar una que no existe' => ['patch', ['/api/modifyCategory', ['ID_Categoria' => 999, 'Nombre_Categoria' => 'X']], 404, 'No se encontró la categoria'],
            'desactivar una que no existe' => ['patch', ['/api/deleteCategory', ['ID_Categoria' => 999]], 404, 'No se encontró la categoria'],
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
            'crear' => ['post', '/api/createCategory', ['Nombre_Categoria' => 'Nueva']],
            'modificar' => ['patch', '/api/modifyCategory', ['ID_Categoria' => 1, 'Nombre_Categoria' => 'Otra']],
            'desactivar' => ['patch', '/api/deleteCategory', ['ID_Categoria' => 1, 'Confirmar' => true]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cambios')]
    public function testSoloElAdminCambiaCategorias(string $metodo, string $ruta, array $datos): void
    {
        [$sinSesion] = $this->cliente()->$metodo($ruta, $datos);
        [$empleado] = $this->comoEmpleado()->$metodo($ruta, $datos);

        $this->assertSame(401, $sinSesion);
        $this->assertSame(403, $empleado);
        $this->assertSame('Abarrotes', $this->valor('SELECT Nombre_Categoria FROM Categoria WHERE ID_Categoria = 1'));
        $this->assertSame('activo', $this->estado(1));
    }
}
