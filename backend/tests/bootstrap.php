<?php
// Arranque de las pruebas
// Las pruebas Unit no tocan la base de datos
// Las pruebas Api usan una base de datos de pruebas (DB_NAME, por defecto sgtdb_test) que se crea desde cero
// y un servidor `php -S` con el mismo router que usa la app; ver tests/Support

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/Support/BaseDatosPrueba.php';
require_once __DIR__ . '/Support/ServidorApi.php';
require_once __DIR__ . '/Support/ClienteApi.php';
require_once __DIR__ . '/Support/ApiTestCase.php';
