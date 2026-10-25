<?php
// Gestión de usuarios (RF13, solo admin): alta, modificación, contraseña y baja lógica

final class UsuariosTest extends ApiTestCase
{
    private ClienteApi $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->comoAdmin();
    }

    // Crea una empleada y regresa su ID de usuario
    private function crearEmpleada(string $password = 'nueva123'): int
    {
        [$codigo, $cuerpo] = $this->admin->post('/api/createUser', [
            'Password' => $password,
            'Nombre' => 'Ana Pérez',
            'Puesto' => 'empleado',
            'Telefono' => '6561230000',
            'Correo' => 'ana@tienda.mx',
        ]);
        $this->assertSame(201, $codigo, $cuerpo['mensaje'] ?? '');
        return (int) $this->valor('SELECT MAX(ID_Usuario) FROM Usuario');
    }

    public function testElAdminCreaUnEmpleadoQueIniciaSesionSinPermisosDeAdmin(): void
    {
        $id = $this->crearEmpleada();

        $empleada = $this->cliente();
        [$login] = $empleada->login($id, 'nueva123');
        [$rutaAdmin] = $empleada->get('/api/getUsers');
        [, $usuarios] = $this->admin->get('/api/getUsers');

        $this->assertSame(200, $login);
        $this->assertSame(403, $rutaAdmin);
        $this->assertStringContainsString('Ana Pérez', json_encode($usuarios, JSON_UNESCAPED_UNICODE));
    }

    public function testModificarLosDatosDelEmpleado(): void
    {
        $id = $this->crearEmpleada();

        [$codigo] = $this->admin->patch('/api/modifyInfoUser', ['ID_Usuario' => $id, 'Nombre' => 'Ana Pérez Ruiz', 'Telefono' => '6560001111']);

        $this->assertSame(200, $codigo);
        $this->assertSame('Ana Pérez Ruiz', $this->valor('SELECT Nombre FROM Empleado WHERE ID_Usuario = ?', [$id]));
        $this->assertSame('6560001111', $this->valor('SELECT c.Telefono FROM Contacto_Empleado c
            INNER JOIN Empleado e ON e.ID_Contacto_Empleado = c.ID_Contacto_Empleado WHERE e.ID_Usuario = ?', [$id]));
    }

    public function testCambiarLaContrasena(): void
    {
        $id = $this->crearEmpleada();

        [$codigo] = $this->admin->post('/api/modifyPasswordUser', ['ID_Usuario' => $id, 'Password' => 'otra456']);
        [$anterior] = $this->cliente()->login($id, 'nueva123');
        [$nueva] = $this->cliente()->login($id, 'otra456');

        $this->assertSame(200, $codigo);
        $this->assertSame(401, $anterior);
        $this->assertSame(200, $nueva);
    }

    public function testLaBajaEsLogicaImpideIniciarSesionYSePuedeReactivar(): void
    {
        $id = $this->crearEmpleada();

        [$baja] = $this->admin->patch('/api/deleteUser', ['ID_Usuario' => $id]);
        [$loginDadaDeBaja, $cuerpo] = $this->cliente()->login($id, 'nueva123');

        $this->assertSame(200, $baja);
        $this->assertSame(403, $loginDadaDeBaja);
        $this->assertSame('Usuario no autorizado', $cuerpo['mensaje']);
        // El registro se conserva para el historial de ventas y cajas
        $this->assertSame('no autorizado', $this->valor('SELECT Estado FROM Usuario WHERE ID_Usuario = ?', [$id]));

        [$reactivar] = $this->admin->patch('/api/modifyEstadoUser', ['ID_Usuario' => $id, 'Estado' => 'autorizado']);
        [$loginReactivada] = $this->cliente()->login($id, 'nueva123');

        $this->assertSame(200, $reactivar);
        $this->assertSame(200, $loginReactivada);
    }

    public function testElEmpleadoNoGestionaUsuarios(): void
    {
        $empleado = $this->comoEmpleado();

        [$crear] = $empleado->post('/api/createUser', ['Password' => 'x1', 'Nombre' => 'Intruso', 'Puesto' => 'admin']);
        [$baja] = $empleado->patch('/api/deleteUser', ['ID_Usuario' => 1]);

        $this->assertSame(403, $crear);
        $this->assertSame(403, $baja);
        $this->assertSame('autorizado', $this->valor('SELECT Estado FROM Usuario WHERE ID_Usuario = 1'));
    }
}
