<?php
/**
 * Migraciones del esquema de la base de datos.
 * database.sql es el esquema base; los cambios posteriores van en migrations/NNN_descripcion.sql.
 * Cada archivo se aplica una sola vez, en orden por nombre, y queda registrado en la tabla Migracion.
 * Se ejecuta desde seeder.php en cada arranque (Docker y Electron), o directo: php config/migrate.php
 *
 * @author Jesus Garrido
 * @version 1.0.0
 * @since 1.0.0
 */


/**
 * Aplica las migraciones pendientes de un directorio, en orden por nombre.
 * MariaDB confirma cada sentencia DDL, así que no hay rollback: si una sentencia falla, la migración
 * no se registra y se reintenta en el siguiente arranque.
 *
 * @param PDO    $connection Conexión a la base de datos
 * @param string $directorio Carpeta con los archivos NNN_descripcion.sql
 * @return array Versiones aplicadas en esta ejecución (vacío si no había pendientes)
 * @throws Exception "La migración NNN falló: ..." si una sentencia falla
 * @see separarSentencias()
 */
function aplicarMigraciones(PDO $connection, string $directorio): array
{
    $connection->exec('CREATE TABLE IF NOT EXISTS Migracion (
        Version VARCHAR(100) NOT NULL PRIMARY KEY,
        Aplicada_En DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $aplicadas = $connection->query('SELECT Version FROM Migracion')->fetchAll(PDO::FETCH_COLUMN);

    $archivos = glob($directorio . '/[0-9][0-9][0-9]_*.sql') ?: [];
    sort($archivos, SORT_STRING);

    $nuevas = [];
    foreach ($archivos as $archivo) {
        $version = basename($archivo, '.sql');
        if (in_array($version, $aplicadas, true)) {
            continue;
        }

        // MariaDB confirma implícitamente cada sentencia DDL, así que no hay rollback:
        // si una sentencia falla, la migración no se registra y se reintenta en el siguiente arranque
        foreach (separarSentencias(file_get_contents($archivo)) as $sentencia) {
            try {
                $connection->exec($sentencia);
            } catch (PDOException $e) {
                throw new Exception("La migración $version falló: " . $e->getMessage());
            }
        }

        $sql = $connection->prepare('INSERT INTO Migracion (Version) VALUES (:Version)');
        $sql->bindValue(':Version', $version);
        $sql->execute();
        $nuevas[] = $version;
    }

    return $nuevas;
}//-- Fin funcion aplicar migraciones


/**
 * Separa un archivo SQL en sentencias.
 * Ignora los comentarios de línea (--) y separa por ';' al final de la línea.
 *
 * @param string $sql Contenido del archivo
 * @return array Las sentencias, sin vacías
 */
function separarSentencias(string $sql): array
{
    $lineas = array_filter(
        preg_split('/\r?\n/', $sql),
        fn($linea) => !preg_match('/^\s*--/', $linea)
    );

    $sentencias = preg_split('/;\s*(?:\n|$)/', implode("\n", $lineas));

    return array_values(array_filter(array_map('trim', $sentencias), fn($s) => $s !== ''));
}//-- Fin funcion separar sentencias


// Ejecución directa desde la terminal
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/database.php';

    try {
        $nuevas = aplicarMigraciones(new Conexion(), __DIR__ . '/migrations');
        echo $nuevas ? 'Migraciones aplicadas: ' . implode(', ', $nuevas) . "\n" : "No hay migraciones pendientes\n";
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage() . "\n";
        exit(1);
    }
}
