<?php
// Pruebas de humo del API: inicio de sesión y control de acceso por rol (RF01, RNF03)

final class AutenticacionTest extends ApiTestCase
{
    public function testPingResponde(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->get('/api/ping');

        $this->assertSame(200, $codigo);
        $this->assertTrue($cuerpo['ok']);
    }

    public function testLoginAdminConCredencialesCorrectas(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->login(...self::ADMIN);

        $this->assertSame(200, $codigo);
        $this->assertSame('admin', $cuerpo['puesto']);
        $this->assertNull($cuerpo['caja']);
    }

    public function testLoginConContrasenaIncorrecta(): void
    {
        [$codigo] = $this->cliente()->login(1, 'incorrecta');

        $this->assertSame(401, $codigo);
    }

    public function testLoginConUsuarioInexistente(): void
    {
        [$codigo] = $this->cliente()->login(999, 'loquesea');

        $this->assertSame(404, $codigo);
    }

    public function testRutaProtegidaSinSesionResponde401(): void
    {
        [$codigo] = $this->cliente()->get('/api/getUsers');

        $this->assertSame(401, $codigo);
    }

    public function testRutaDeAdminConEmpleadoResponde403(): void
    {
        [$codigo] = $this->comoEmpleado()->get('/api/getUsers');

        $this->assertSame(403, $codigo);
    }

    public function testRutaDeAdminConAdminResponde200(): void
    {
        [$codigo, $cuerpo] = $this->comoAdmin()->get('/api/getUsers');

        $this->assertSame(200, $codigo);
        $this->assertCount(2, $cuerpo['usuarios']);
    }

    public function testLogoutCierraLaSesion(): void
    {
        $admin = $this->comoAdmin();
        $admin->post('/api/logout');

        [$codigo] = $admin->get('/api/getUsers');
        $this->assertSame(401, $codigo);
    }
}
