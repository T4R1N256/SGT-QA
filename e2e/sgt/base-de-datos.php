<?php
// Base de datos nueva para una prueba de la app de escritorio: solo el esquema base (database.sql).
// Al arrancar, la app aplica las migraciones y el seeder carga el admin y los datos demo (SEED_DEMO=1).
// Usa las mismas variables que las pruebas del backend: DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS

require __DIR__ . '/../../backend/config/migrate.php';
require __DIR__ . '/../../backend/tests/Support/BaseDatosPrueba.php';

BaseDatosPrueba::crearConEsquemaBase();
