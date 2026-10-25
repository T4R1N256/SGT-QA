<?php
// Cuentas de cliente de la tienda (fase T3): registro, inicio y cierre de sesión, y datos inválidos (P8–P10)

final class CuentasApiTest extends ApiTestCase
{
    private const DATOS = [
        'nombre' => 'Ana María',
        'apellidos' => "O'Connor Pérez-Díaz",
        'correo' => 'Ana@Ejemplo.MX',
        'telefono' => '656 123-4567',
        'password' => 'secreta123',
    ];

    private function registrar(array $cambios = [], ?ClienteApi $cliente = null): array
    {
        return ($cliente ?? $this->cliente())->post('/api/tienda/registro', array_merge(self::DATOS, $cambios));
    }

    public function testElRegistroCreaLaCuentaEIniciaSesion(): void
    {
        $cliente = $this->cliente();

        [$codigo, $cuerpo] = $this->registrar([], $cliente);
        [, $cuenta] = $cliente->get('/api/tienda/cuenta');

        $this->assertSame(201, $codigo);
        $esperado = ['nombre' => 'Ana María', 'apellidos' => "O'Connor Pérez-Díaz", 'correo' => 'ana@ejemplo.mx', 'telefono' => '6561234567'];
        $this->assertSame($esperado, array_diff_key($cuerpo['cliente'], ['id' => 0]));
        $this->assertSame($cuerpo['cliente'], $cuenta['cliente']);
    }

    public function testLaContrasenaSeGuardaConHashYNuncaSeRegresa(): void
    {
        [, $cuerpo] = $this->registrar();

        $guardada = $this->valor('SELECT Password FROM Cliente WHERE Correo = ?', ['ana@ejemplo.mx']);
        $this->assertNotSame('secreta123', $guardada);
        $this->assertTrue(password_verify('secreta123', $guardada));
        $this->assertArrayNotHasKey('password', $cuerpo['cliente']);
        $this->assertStringNotContainsString($guardada, json_encode($cuerpo));
    }

    public function testElCorreoNoSePuedeRepetir(): void
    {
        $this->registrar();

        // Mismo correo con otras mayúsculas y espacios
        [$codigo, $cuerpo] = $this->registrar(['correo' => '  ANA@ejemplo.mx ']);

        $this->assertSame(409, $codigo);
        $this->assertSame(['correo' => 'Ya existe una cuenta con este correo'], $cuerpo['errores']);
        $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM Cliente'));
    }

    public function testLosCamposVaciosOSoloConEspaciosSeMarcanUnoPorUno(): void
    {
        // P8
        [$codigo, $cuerpo] = $this->cliente()->post('/api/tienda/registro', [
            'nombre' => '   ', 'apellidos' => '', 'correo' => ' ', 'telefono' => '', 'password' => '',
        ]);

        $this->assertSame(400, $codigo);
        $this->assertSame('Revisa los datos marcados', $cuerpo['mensaje']);
        $this->assertSame([
            'nombre' => 'Escribe tu nombre',
            'apellidos' => 'Escribe tus apellidos',
            'correo' => 'Escribe tu correo',
            'telefono' => 'Escribe tu teléfono',
            'password' => 'Escribe una contraseña',
        ], $cuerpo['errores']);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Cliente'));
    }

    /** @return array<string, array{array, string, string}> */
    public static function datosInvalidos(): array
    {
        return [
            // P9: formatos
            'correo sin dominio' => [['correo' => 'ana@'], 'correo', 'Escribe un correo válido, como nombre@ejemplo.com'],
            'correo sin arroba' => [['correo' => 'ana.ejemplo.mx'], 'correo', 'Escribe un correo válido, como nombre@ejemplo.com'],
            'teléfono corto' => [['telefono' => '12345'], 'telefono', 'El teléfono debe tener 10 dígitos'],
            'teléfono con letras' => [['telefono' => '656ABC4567'], 'telefono', 'El teléfono debe tener 10 dígitos'],
            'contraseña corta' => [['password' => 'abc123'], 'password', 'La contraseña debe tener al menos 8 caracteres'],
            'contraseña sin números' => [['password' => 'abcdefghij'], 'password', 'La contraseña debe tener letras y números'],
            'contraseña sin letras' => [['password' => '1234567890'], 'password', 'La contraseña debe tener letras y números'],
            'contraseña de más de 72' => [['password' => str_repeat('a1', 37)], 'password', 'La contraseña no puede tener más de 72 caracteres'],
            // P10: HTML, comillas y longitud
            'nombre con HTML' => [['nombre' => '<script>alert(1)</script>'], 'nombre', 'El nombre solo puede tener letras, espacios, apóstrofos y guiones'],
            'nombre con comillas dobles' => [['nombre' => 'Ana "La Jefa"'], 'nombre', 'El nombre solo puede tener letras, espacios, apóstrofos y guiones'],
            'apellidos con números' => [['apellidos' => 'Pérez 2'], 'apellidos', 'Los apellidos solo pueden tener letras, espacios, apóstrofos y guiones'],
            'nombre de más de 100' => [['nombre' => str_repeat('a', 101)], 'nombre', 'El nombre no puede tener más de 100 caracteres'],
            'correo de más de 254' => [['correo' => str_repeat('a', 250) . '@ej.mx'], 'correo', 'El correo no puede tener más de 254 caracteres'],
            'dato que no es texto' => [['nombre' => ['Ana']], 'nombre', 'Dato no válido'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datosInvalidos')]
    public function testRechazaDatosInvalidosConUnMensajePorCampo(array $cambios, string $campo, string $mensaje): void
    {
        [$codigo, $cuerpo] = $this->registrar($cambios);

        $this->assertSame(400, $codigo);
        $this->assertSame([$campo => $mensaje], $cuerpo['errores']);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM Cliente'));
    }

    public function testIngresarConCorreoYContrasena(): void
    {
        $this->registrar();
        $cliente = $this->cliente();

        [$codigo, $cuerpo] = $cliente->post('/api/tienda/ingresar', ['correo' => ' ANA@ejemplo.mx ', 'password' => 'secreta123']);
        [, $cuenta] = $cliente->get('/api/tienda/cuenta');

        $this->assertSame(200, $codigo);
        $this->assertSame('ana@ejemplo.mx', $cuerpo['cliente']['correo']);
        $this->assertSame($cuerpo['cliente'], $cuenta['cliente']);
    }

    public function testUnaContrasenaIncorrectaYUnCorreoSinCuentaRespondenIgual(): void
    {
        $this->registrar();

        [$codigo1, $cuerpo1] = $this->cliente()->post('/api/tienda/ingresar', ['correo' => 'ana@ejemplo.mx', 'password' => 'otra12345']);
        [$codigo2, $cuerpo2] = $this->cliente()->post('/api/tienda/ingresar', ['correo' => 'nadie@ejemplo.mx', 'password' => 'secreta123']);

        $this->assertSame([401, 'Correo o contraseña incorrectos'], [$codigo1, $cuerpo1['mensaje']]);
        $this->assertSame([401, 'Correo o contraseña incorrectos'], [$codigo2, $cuerpo2['mensaje']]);
    }

    public function testIngresarSinDatosMarcaLosCampos(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->post('/api/tienda/ingresar', ['correo' => '  ']);

        $this->assertSame(400, $codigo);
        $this->assertSame(['correo' => 'Escribe tu correo', 'password' => 'Escribe tu contraseña'], $cuerpo['errores']);
    }

    public function testAlIngresarCambiaElIdDeSesion(): void
    {
        // Evita la fijación de sesión: la cookie que había antes de ingresar deja de servir
        $this->registrar();
        $cliente = $this->cliente();
        $cliente->get('/api/tienda/cuenta');
        $antes = $cliente->cookies()['SGT_TIENDA'];

        $cliente->post('/api/tienda/ingresar', ['correo' => 'ana@ejemplo.mx', 'password' => 'secreta123']);

        $this->assertNotSame($antes, $cliente->cookies()['SGT_TIENDA']);
    }

    public function testSalirCierraLaSesion(): void
    {
        $cliente = $this->cliente();
        $this->registrar([], $cliente);

        [$codigo] = $cliente->post('/api/tienda/salir');
        [, $cuenta] = $cliente->get('/api/tienda/cuenta');

        $this->assertSame(200, $codigo);
        $this->assertNull($cuenta['cliente']);
    }

    public function testSinSesionLaCuentaEsNull(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->get('/api/tienda/cuenta');

        $this->assertSame(200, $codigo);
        $this->assertNull($cuerpo['cliente']);
    }

    public function testUnClienteDesactivadoNoPuedeEntrarNiConservaSuSesion(): void
    {
        $cliente = $this->cliente();
        $this->registrar([], $cliente);
        BaseDatosPrueba::conexion()->exec("UPDATE Cliente SET Activo = 0 WHERE Correo = 'ana@ejemplo.mx'");

        [, $cuenta] = $cliente->get('/api/tienda/cuenta');
        [$codigo] = $this->cliente()->post('/api/tienda/ingresar', ['correo' => 'ana@ejemplo.mx', 'password' => 'secreta123']);

        $this->assertNull($cuenta['cliente']);
        $this->assertSame(401, $codigo);
    }

    public function testLasSesionesDeClienteYDeEmpleadoNoSeMezclan(): void
    {
        // T-S7: un empleado no es cliente de la tienda y un cliente no entra a SGT
        $cliente = $this->cliente();
        $this->registrar([], $cliente);

        [, $cuentaEmpleado] = $this->comoAdmin()->get('/api/tienda/cuenta');
        [$codigoSGT] = $cliente->get('/api/getAllProducts');

        $this->assertNull($cuentaEmpleado['cliente']);
        $this->assertSame(401, $codigoSGT);
    }
}
