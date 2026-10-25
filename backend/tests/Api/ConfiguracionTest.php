<?php
// Pruebas de la configuración de la tienda (datos del ticket)

final class ConfiguracionTest extends ApiTestCase
{
    public function testLaConfiguracionEsPublica(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->get('/api/getConfig');

        $this->assertSame(200, $codigo);
        $this->assertSame('Abarrotes "La bamba"', $cuerpo['configuracion']['Nombre_Tienda']);
    }

    public function testElAdminActualizaSoloLosCamposEnviados(): void
    {
        [$codigo, $cuerpo] = $this->comoAdmin()->patch('/api/updateConfig', [
            'Nombre_Tienda' => 'Abarrotes Doña Lupe & Hijos',
            'RFC' => 'lupe800101ab1',
        ]);

        $this->assertSame(200, $codigo, $cuerpo['mensaje'] ?? '');
        $this->assertSame('Abarrotes Doña Lupe & Hijos', $cuerpo['configuracion']['Nombre_Tienda']);
        $this->assertSame('LUPE800101AB1', $cuerpo['configuracion']['RFC']);
        $this->assertSame('¡Gracias por su compra!', $cuerpo['configuracion']['Mensaje_Ticket']);
    }

    public function testSePuedeBorrarUnCampoOpcional(): void
    {
        $admin = $this->comoAdmin();
        $admin->patch('/api/updateConfig', ['Direccion' => 'Av. Juárez 100']);

        [, $cuerpo] = $admin->patch('/api/updateConfig', ['Direccion' => '']);

        $this->assertSame('', $cuerpo['configuracion']['Direccion']);
    }

    /** @return array<string, array{array}> */
    public static function configuracionesInvalidas(): array
    {
        return [
            'nombre vacío' => [['Nombre_Tienda' => '  ']],
            'RFC inválido' => [['RFC' => 'ABC123']],
            'teléfono muy largo' => [['Telefono' => str_repeat('1', 21)]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('configuracionesInvalidas')]
    public function testDatosInvalidosResponden400(array $datos): void
    {
        [$codigo] = $this->comoAdmin()->patch('/api/updateConfig', $datos);

        $this->assertSame(400, $codigo);
        $this->assertSame('Abarrotes "La bamba"', $this->valor('SELECT Nombre_Tienda FROM Configuracion'));
    }

    public function testElEmpleadoNoPuedeModificarla(): void
    {
        [$codigo] = $this->comoEmpleado()->patch('/api/updateConfig', ['Nombre_Tienda' => 'Otra']);

        $this->assertSame(403, $codigo);
    }
}
