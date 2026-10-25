<?php
// Levanta el backend con `php -S` en un puerto libre, igual que Electron, apuntando a la base de datos de pruebas
// Se inicia una sola vez por ejecución de PHPUnit y se detiene al terminar

final class ServidorApi
{
    private static $proceso = null;
    private static ?string $url = null;
    private static ?string $carpetaSesiones = null;

    public static function url(): string
    {
        if (self::$url === null) {
            self::iniciar();
        }
        return self::$url;
    }

    private static function iniciar(): void
    {
        BaseDatosPrueba::recrear();

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $puerto = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$carpetaSesiones = sys_get_temp_dir() . '/sgt-test-sesiones-' . getmypid();
        @mkdir(self::$carpetaSesiones);

        $backend = realpath(__DIR__ . '/../..');
        self::$proceso = proc_open(
            [
                PHP_BINARY,
                '-d', 'session.save_path=' . self::$carpetaSesiones,
                '-S', "127.0.0.1:$puerto",
                '-t', $backend,
                $backend . '/index.php',
            ],
            [0 => ['pipe', 'r'], 1 => ['file', self::$carpetaSesiones . '.log', 'w'], 2 => ['file', self::$carpetaSesiones . '.log', 'a']],
            $pipes,
            $backend,
            array_merge(getenv(), BaseDatosPrueba::entorno())
        );

        self::$url = "http://127.0.0.1:$puerto";
        register_shutdown_function([self::class, 'detener']);

        for ($i = 0; $i < 50; $i++) {
            $respuesta = @file_get_contents(self::$url . '/api/ping');
            if ($respuesta !== false) {
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('El servidor PHP de pruebas no respondió en ' . self::$url
            . '. Log: ' . self::$carpetaSesiones . '.log');
    }

    public static function detener(): void
    {
        if (self::$proceso) {
            proc_terminate(self::$proceso);
            proc_close(self::$proceso);
            self::$proceso = null;
        }
        if (self::$carpetaSesiones && is_dir(self::$carpetaSesiones)) {
            array_map('unlink', glob(self::$carpetaSesiones . '/*') ?: []);
            @rmdir(self::$carpetaSesiones);
            @unlink(self::$carpetaSesiones . '.log');
        }
    }
}
