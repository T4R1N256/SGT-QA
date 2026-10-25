<?php
// Crea la base de datos de pruebas desde cero: esquema base, migraciones y seeder (admin y datos demo)

final class BaseDatosPrueba
{
    // Variables de entorno con las que se conecta el backend durante las pruebas
    public static function entorno(): array
    {
        return [
            'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
            'DB_PORT' => getenv('DB_PORT') ?: '3306',
            'DB_NAME' => getenv('DB_NAME') ?: 'sgtdb_test',
            'DB_USER' => getenv('DB_USER') ?: 'sgt_test',
            'DB_PASS' => getenv('DB_PASS') ?: 'sgt_test',
            'APP_ENV' => 'testing',
            'SEED_DEMO' => '1',
        ];
    }

    // Conexión a la base de datos de pruebas
    public static function conexion(): PDO
    {
        $e = self::entorno();
        return new PDO(
            "mysql:host={$e['DB_HOST']};port={$e['DB_PORT']};dbname={$e['DB_NAME']};charset=utf8mb4",
            $e['DB_USER'],
            $e['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    // Borra y vuelve a crear la base de datos, aplica database.sql y ejecuta seeder.php
    public static function recrear(): void
    {
        $e = self::entorno();
        $servidor = new PDO(
            "mysql:host={$e['DB_HOST']};port={$e['DB_PORT']};charset=utf8mb4",
            $e['DB_USER'],
            $e['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $servidor->exec("DROP DATABASE IF EXISTS `{$e['DB_NAME']}`");
        $servidor->exec("CREATE DATABASE `{$e['DB_NAME']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $connection = self::conexion();
        foreach (separarSentencias(file_get_contents(__DIR__ . '/../../config/database.sql')) as $sentencia) {
            $connection->exec($sentencia);
        }

        self::ejecutarSeeder();
    }

    // Ejecuta seeder.php como lo hacen Docker y Electron en cada arranque
    public static function ejecutarSeeder(): string
    {
        $proceso = proc_open(
            [PHP_BINARY, __DIR__ . '/../../config/seeder.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            // APP_ENV=development para que los errores fatales aparezcan en la salida
            array_merge(getenv(), self::entorno(), ['APP_ENV' => 'development'])
        );
        $salida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $codigo = proc_close($proceso);

        if ($codigo !== 0 || str_contains($salida, 'Error')) {
            throw new RuntimeException("El seeder falló:\n$salida");
        }
        return $salida;
    }
}
