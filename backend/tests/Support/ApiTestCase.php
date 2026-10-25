<?php
// Base para las pruebas del API: servidor de pruebas, usuarios con sesión iniciada y ayudas para caja y ventas
// Cada prueba empieza con la base de datos recién creada (esquema, migraciones, admin y datos demo)

use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    // Usuarios que crea el seeder (admin) y los datos de demostración (empleado)
    protected const ADMIN = [1, 'passadmin'];
    protected const EMPLEADO = [2, 'empleado123'];

    protected function setUp(): void
    {
        ServidorApi::url();
        BaseDatosPrueba::recrear();
    }

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

    // Primer valor de una consulta a la base de datos de pruebas
    protected function valor(string $sql, array $parametros = []): mixed
    {
        $consulta = BaseDatosPrueba::conexion()->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchColumn();
    }

    // Primera fila de una consulta a la base de datos de pruebas
    protected function fila(string $sql, array $parametros = []): array|false
    {
        $consulta = BaseDatosPrueba::conexion()->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetch();
    }

    protected function existencia(int $ID_Producto): int
    {
        return (int) $this->valor('SELECT Cantidad FROM Producto WHERE ID_Producto = ?', [$ID_Producto]);
    }

    protected function abrirCaja(ClienteApi $cliente, float $monto = 0): void
    {
        [$codigo, $cuerpo] = $cliente->post('/api/openCashRegister', ['Monto_Inicial' => $monto]);
        $this->assertSame(201, $codigo, $cuerpo['mensaje'] ?? '');
    }

    // Completa una venta. $productos es [ID_Producto => Cantidad]
    // Si no se da el monto, se calcula con los precios de la base de datos (lo que haría la pantalla de Ventas)
    protected function vender(ClienteApi $cliente, array $productos, string $formaPago = 'efectivo', ?float $monto = null): array
    {
        if ($monto === null) {
            $centavos = 0;
            foreach ($productos as $id => $cantidad) {
                $precio = $this->valor('SELECT Precio FROM Producto WHERE ID_Producto = ?', [$id]);
                $centavos += (int) round($precio * 100) * $cantidad;
            }
            $monto = $centavos / 100;
        }

        $lista = [];
        foreach ($productos as $id => $cantidad) {
            $lista[] = ['ID_Producto' => $id, 'Cantidad' => $cantidad];
        }

        return $cliente->patch('/api/completeSale', [
            'Monto' => $monto,
            'Forma_Pago' => $formaPago,
            'productos' => $lista,
        ]);
    }
}
