<?php
// Base para las pruebas del API: servidor de pruebas y usuarios con sesión iniciada

use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    // Usuarios que crea el seeder (admin) y los datos de demostración (empleado)
    protected const ADMIN = [1, 'passadmin'];
    protected const EMPLEADO = [2, 'empleado123'];

    protected function cliente(): ClienteApi
    {
        return new ClienteApi(ServidorApi::url());
    }

    protected function comoAdmin(): ClienteApi
    {
        return $this->clienteConSesion(...self::ADMIN);
    }

    protected function comoEmpleado(): ClienteApi
    {
        return $this->clienteConSesion(...self::EMPLEADO);
    }

    private function clienteConSesion(int $id, string $password): ClienteApi
    {
        $cliente = $this->cliente();
        [$codigo] = $cliente->login($id, $password);
        $this->assertSame(200, $codigo, "No se pudo iniciar sesión con el usuario $id");
        return $cliente;
    }
}
