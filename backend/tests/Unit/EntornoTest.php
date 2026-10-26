<?php
// Variables de entorno del backend (config/database.php): las del proceso le ganan al .env
// Así la app de escritorio y las pruebas usan su base de datos aunque haya un .env de Docker en la carpeta

use PHPUnit\Framework\TestCase;

final class EntornoTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // database.php ajusta error_reporting según APP_ENV: se deja como estaba para el resto de las pruebas
        $nivel = error_reporting();
        $mostrar = ini_get('display_errors');
        require_once __DIR__ . '/../../config/database.php';
        error_reporting($nivel);
        ini_set('display_errors', $mostrar);
    }

    protected function tearDown(): void
    {
        putenv('SGT_PRUEBA_ENTORNO');
        unset($_ENV['SGT_PRUEBA_ENTORNO']);
    }

    public function testLaVariableDelProcesoLeGanaAlEnv(): void
    {
        $_ENV['SGT_PRUEBA_ENTORNO'] = 'db';
        putenv('SGT_PRUEBA_ENTORNO=127.0.0.1');

        $this->assertSame('127.0.0.1', variableEntorno('SGT_PRUEBA_ENTORNO'));
    }

    public function testSinVariableDelProcesoSeUsaLaDelEnv(): void
    {
        $_ENV['SGT_PRUEBA_ENTORNO'] = 'db';

        $this->assertSame('db', variableEntorno('SGT_PRUEBA_ENTORNO'));
    }

    public function testVaciaONoDefinidaUsaElValorPorDefecto(): void
    {
        $this->assertSame('3306', variableEntorno('SGT_PRUEBA_ENTORNO', '3306'));

        putenv('SGT_PRUEBA_ENTORNO=');
        $_ENV['SGT_PRUEBA_ENTORNO'] = '';
        $this->assertSame('3306', variableEntorno('SGT_PRUEBA_ENTORNO', '3306'));
    }
}
