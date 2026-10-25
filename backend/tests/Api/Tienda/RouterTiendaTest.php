<?php
// Base de la tienda en línea (fase T0): router /api/tienda/, sesión propia y configuración pública

final class RouterTiendaTest extends ApiTestCase
{
    public function testLaConfiguracionEsPublicaYTraeLaTasaDeIVA(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->get('/api/tienda/config');

        $this->assertSame(200, $codigo);
        $this->assertTrue($cuerpo['ok']);
        $this->assertSame('Abarrotes "La bamba"', $cuerpo['tienda']['nombre']);
        $this->assertEquals(16, $cuerpo['tienda']['tasa_iva']);
    }

    public function testLaTasaDeIVASeLeeDeLaConfiguracion(): void
    {
        BaseDatosPrueba::conexion()->exec('UPDATE Configuracion SET Tasa_IVA = 8 WHERE ID_Configuracion = 1');

        [, $cuerpo] = $this->cliente()->get('/api/tienda/config');

        $this->assertEquals(8, $cuerpo['tienda']['tasa_iva']);
    }

    public function testRutaInexistenteResponde404(): void
    {
        [$codigo, $cuerpo] = $this->cliente()->get('/api/tienda/no-existe');

        $this->assertSame(404, $codigo);
        $this->assertFalse($cuerpo['ok']);
        $this->assertSame('Ruta no encontrada', $cuerpo['mensaje']);
    }

    public function testMetodoIncorrectoResponde405(): void
    {
        [$codigo] = $this->cliente()->post('/api/tienda/config');

        $this->assertSame(405, $codigo);
    }

    public function testLaTiendaUsaSuPropiaCookieDeSesion(): void
    {
        $tienda = $this->cliente();
        $tienda->get('/api/tienda/config');
        $empleado = $this->comoEmpleado();

        $this->assertContains('SGT_TIENDA', $tienda->nombresCookies());
        $this->assertNotContains('SGT_TIENDA', $empleado->nombresCookies());
    }

    public function testLaSesionDeLaTiendaNoDaAccesoASGT(): void
    {
        $tienda = $this->cliente();
        $tienda->get('/api/tienda/config');

        [$codigo] = $tienda->get('/api/getAllProducts');

        $this->assertSame(401, $codigo);
    }

    public function testUnaPeticionDelMismoOrigenSeAceptaAunqueTraigaOrigin(): void
    {
        // Como el navegador en un POST a su propio sitio: la tienda detrás de nginx o del proxy de Vite
        $cliente = $this->cliente();
        $cliente->encabezados = ['Origin: ' . ServidorApi::url()];

        [$codigo] = $cliente->get('/api/tienda/config');

        $this->assertSame(200, $codigo);
    }

    public function testUnOrigenAjenoSeRechaza(): void
    {
        $cliente = $this->cliente();
        $cliente->encabezados = ['Origin: http://sitio-ajeno.example'];

        [$codigo] = $cliente->get('/api/tienda/config');

        $this->assertSame(403, $codigo);
    }
}
