<?php
// Pruebas del mecanismo de migraciones (config/migrate.php)
// Usan SQLite en memoria, así que no necesitan MariaDB

use PHPUnit\Framework\TestCase;

final class MigracionesTest extends TestCase
{
    private PDO $connection;
    private string $directorio;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->directorio = sys_get_temp_dir() . '/sgt-migraciones-' . uniqid();
        mkdir($this->directorio);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directorio . '/*') ?: []);
        rmdir($this->directorio);
    }

    private function migracion(string $nombre, string $sql): void
    {
        file_put_contents("{$this->directorio}/$nombre", $sql);
    }

    private function versionesRegistradas(): array
    {
        return $this->connection->query('SELECT Version FROM Migracion ORDER BY Version')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testSinMigracionesSoloCreaLaTablaDeControl(): void
    {
        $this->assertSame([], aplicarMigraciones($this->connection, $this->directorio));
        $this->assertSame([], $this->versionesRegistradas());
    }

    public function testAplicaEnOrdenPorNombreYLasRegistra(): void
    {
        $this->migracion('002_agregar_columna.sql', 'ALTER TABLE Prueba ADD COLUMN Nombre TEXT;');
        $this->migracion('001_crear_tabla.sql', 'CREATE TABLE Prueba (Id INTEGER PRIMARY KEY);');

        $aplicadas = aplicarMigraciones($this->connection, $this->directorio);

        $this->assertSame(['001_crear_tabla', '002_agregar_columna'], $aplicadas);
        $this->assertSame(['001_crear_tabla', '002_agregar_columna'], $this->versionesRegistradas());
        $this->connection->exec("INSERT INTO Prueba (Id, Nombre) VALUES (1, 'ok')");
    }

    public function testNoVuelveAAplicarMigracionesRegistradas(): void
    {
        $this->migracion('001_crear_tabla.sql', 'CREATE TABLE Prueba (Id INTEGER PRIMARY KEY);');
        aplicarMigraciones($this->connection, $this->directorio);

        // Si se volviera a ejecutar, CREATE TABLE fallaría por tabla duplicada
        $this->assertSame([], aplicarMigraciones($this->connection, $this->directorio));
    }

    public function testIgnoraArchivosQueNoSonMigraciones(): void
    {
        $this->migracion('README.md', 'no es SQL');
        $this->migracion('borrador.sql', 'CREATE TABLE Borrador (Id INTEGER);');

        $this->assertSame([], aplicarMigraciones($this->connection, $this->directorio));
    }

    public function testUnaMigracionFallidaNoSeRegistraYDetieneLasSiguientes(): void
    {
        $this->migracion('001_crear_tabla.sql', 'CREATE TABLE Prueba (Id INTEGER PRIMARY KEY);');
        $this->migracion('002_rota.sql', 'ALTER TABLE NoExiste ADD COLUMN X TEXT;');
        $this->migracion('003_otra.sql', 'CREATE TABLE Otra (Id INTEGER);');

        try {
            aplicarMigraciones($this->connection, $this->directorio);
            $this->fail('Se esperaba una excepción');
        } catch (Exception $e) {
            $this->assertStringContainsString('002_rota', $e->getMessage());
        }

        $this->assertSame(['001_crear_tabla'], $this->versionesRegistradas());
    }

    public function testSeparaSentenciasIgnorandoComentarios(): void
    {
        $sql = "-- Comentario inicial\n"
            . "CREATE TABLE A (Id INTEGER);\n"
            . "\n"
            . "  -- otro comentario\n"
            . "INSERT INTO A (Id)\n  VALUES (1);   \n"
            . "INSERT INTO A (Id) VALUES (2)";

        $this->assertSame(
            ['CREATE TABLE A (Id INTEGER)', "INSERT INTO A (Id)\n  VALUES (1)", 'INSERT INTO A (Id) VALUES (2)'],
            separarSentencias($sql)
        );
    }
}
