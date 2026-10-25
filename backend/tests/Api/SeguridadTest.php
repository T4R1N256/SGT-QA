<?php
// Pruebas de control de acceso y datos personales (RNF03, RNF04, RL01 · RNF08)

final class SeguridadTest extends ApiTestCase
{
    /** @return array<string, array{string}> */
    public static function rutasDeLectura(): array
    {
        return [
            'getProduct' => ['/api/getProduct?id=1'],
            'getAllProducts' => ['/api/getAllProducts'],
            'getCategory' => ['/api/getCategory?id=1'],
            'getAllCategories' => ['/api/getAllCategories'],
            'getSupplier' => ['/api/getSupplier?id=1'],
            'getAllSuppliers' => ['/api/getAllSuppliers'],
            'getCashRegister' => ['/api/getCashRegister'],
            'getAllVentas' => ['/api/getAllVentas'],
            'getVenta' => ['/api/getVenta?id=1'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rutasDeLectura')]
    public function testLasRutasDeLecturaExigenSesion(string $ruta): void
    {
        [$codigo] = $this->cliente()->get($ruta);

        $this->assertSame(401, $codigo);
    }

    public function testElEmpleadoNoVeDatosDeOtroEmpleado(): void
    {
        [$codigo] = $this->comoEmpleado()->get('/api/getUserInfo?id=1');

        $this->assertSame(403, $codigo);
    }

    public function testElEmpleadoVeSusPropiosDatos(): void
    {
        $empleado = $this->comoEmpleado();

        [$sinId, $cuerpo] = $empleado->get('/api/getUserInfo');
        [$conId] = $empleado->get('/api/getUserInfo?id=2');

        $this->assertSame([200, 200], [$sinId, $conId]);
        $this->assertSame('Empleado Demo', $cuerpo['usuarioinfo']['Nombre']);
    }

    public function testElAdminVeDatosDeCualquierEmpleado(): void
    {
        [$codigo] = $this->comoAdmin()->get('/api/getUserInfo?id=2');

        $this->assertSame(200, $codigo);
    }

    public function testLaValidacionDeCorreoPorInternetYaNoExiste(): void
    {
        [$codigo] = $this->comoAdmin()->get('/api/checkEmail?correo=ana@tienda.mx');

        $this->assertSame(404, $codigo);
    }

    public function testLoginConIdNoNumericoResponde400(): void
    {
        [$codigo] = $this->cliente()->login(0, 'x');
        [$codigoTexto] = $this->cliente()->post('/api/login', ['ID_Usuario' => 'admin', 'Password' => 'passadmin']);

        $this->assertSame([400, 400], [$codigo, $codigoTexto]);
    }

    public function testContrasenaGuardadaEscapadaSigueFuncionandoYSeCorrige(): void
    {
        // Antes de la fase 1, "a&b<c" se hasheaba como "a&amp;b&lt;c"
        $password = 'a&b<c';
        BaseDatosPrueba::conexion()
            ->prepare('UPDATE Usuario SET Password = ? WHERE ID_Usuario = 2')
            ->execute([password_hash(htmlspecialchars($password), PASSWORD_DEFAULT)]);

        [$codigo] = $this->cliente()->login(2, $password);

        $this->assertSame(200, $codigo);
        $this->assertTrue(password_verify($password, $this->valor('SELECT Password FROM Usuario WHERE ID_Usuario = 2')));
    }

    public function testLasContrasenasNuevasNoSeEscapan(): void
    {
        $password = 'nueva&segura"1';
        [$codigo] = $this->comoAdmin()->post('/api/modifyPasswordUser', ['ID_Usuario' => 2, 'Password' => $password]);

        $this->assertSame(200, $codigo);
        $this->assertTrue(password_verify($password, $this->valor('SELECT Password FROM Usuario WHERE ID_Usuario = 2')));
        [$login] = $this->cliente()->login(2, $password);
        $this->assertSame(200, $login);
    }

    public function testPuestoDesconocidoResponde400(): void
    {
        [$codigo] = $this->comoAdmin()->post('/api/createUser', [
            'Nombre' => 'Nuevo',
            'Puesto' => 'superusuario',
            'Password' => 'secreta',
        ]);

        $this->assertSame(400, $codigo);
    }
}
