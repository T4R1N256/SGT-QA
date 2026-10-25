<?php
// Pruebas del arranque: migraciones, usuario admin y datos de demostración

final class SeederTest extends ApiTestCase
{
    public function testCreaLaTablaDeMigraciones(): void
    {
        $tabla = BaseDatosPrueba::conexion()->query("SHOW TABLES LIKE 'Migracion'")->fetchColumn();

        $this->assertSame('Migracion', $tabla);
    }

    public function testElDineroSeGuardaComoDecimalExacto(): void
    {
        $tipos = BaseDatosPrueba::conexion()->query("SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME), COLUMN_TYPE
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
            AND COLUMN_NAME IN ('Precio', 'Monto', 'Precio_Unitario', 'Monto_Inicial', 'Monto_Final', 'Monto_Final_Sistema')")
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assertCount(8, $tipos);
        foreach ($tipos as $columna => $tipo) {
            $this->assertSame('decimal(10,2)', $tipo, $columna);
        }
    }

    public function testLaBaseDeDatosRechazaExistenciasNegativas(): void
    {
        $this->expectException(PDOException::class);
        BaseDatosPrueba::conexion()->exec('UPDATE Producto SET Cantidad = -1 WHERE ID_Producto = 1');
    }

    public function testCargaLosDatosDeDemostracion(): void
    {
        $connection = BaseDatosPrueba::conexion();
        $estados = $connection->query('SELECT Estado, COUNT(*) AS Total FROM Producto GROUP BY Estado')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assertSame(32, array_sum($estados));
        $this->assertArrayHasKey('Disponible', $estados);
        $this->assertArrayHasKey('Stock bajo', $estados);
        $this->assertArrayHasKey('Agotado', $estados);
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
