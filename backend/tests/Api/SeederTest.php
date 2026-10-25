<?php
// Pruebas del arranque: migraciones, usuario admin y datos de demostración

final class SeederTest extends ApiTestCase
{
    protected function setUp(): void
    {
        // Asegura que la base de datos de pruebas ya existe
        ServidorApi::url();
    }

    public function testCreaLaTablaDeMigraciones(): void
    {
        $tabla = BaseDatosPrueba::conexion()->query("SHOW TABLES LIKE 'Migracion'")->fetchColumn();

        $this->assertSame('Migracion', $tabla);
    }

    public function testCargaLosDatosDeDemostracion(): void
    {
        $connection = BaseDatosPrueba::conexion();
        $estados = $connection->query('SELECT Estado, COUNT(*) AS Total FROM Producto GROUP BY Estado')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assertSame(32, array_sum($estados));
        $this->assertArrayHasKey('Disponible', $estados);
        $this->assertArrayHasKey('Stock bajo', $estados);
        $this->assertArrayHasKey('Sin stock', $estados);
    }

    public function testVolverAEjecutarElSeederNoDuplicaDatos(): void
    {
        $connection = BaseDatosPrueba::conexion();
        $antes = $this->conteos($connection);

        $salida = BaseDatosPrueba::ejecutarSeeder();

        $this->assertSame($antes, $this->conteos($connection));
        $this->assertStringContainsString('El usuario admin ya existe', $salida);
        $this->assertStringContainsString('no se cargaron datos de demostración', $salida);
    }

    private function conteos(PDO $connection): array
    {
        $conteos = [];
        foreach (['Usuario', 'Empleado', 'Categoria', 'Proveedor', 'Producto', 'Migracion'] as $tabla) {
            $conteos[$tabla] = (int) $connection->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
        }
        return $conteos;
    }
}
