import { defineConfig, devices } from '@playwright/test'

// Pruebas de punta a punta, en dos proyectos:
//
// tienda: la tienda en línea en el navegador (fase T5), contra la tienda levantada con Docker
//   cp .env.example .env && docker compose up --build -d
//   npm run test:e2e:tienda
//   TIENDA_URL (tienda) y SGT_API (backend, para preparar datos como admin) permiten apuntar a otra instancia
//
// sgt: la app de escritorio compilada (Electron), con el PHP del sistema y una MariaDB de pruebas
//   npm run test:e2e:sgt      (compila la app y corre las pruebas; en Linux sin pantalla: xvfb-run -a)
//   Usa la misma base de datos que PHPUnit (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS; ver backend/phpunit.xml)
export default defineConfig({
  // Las pruebas comparten la base de datos (y la app de escritorio el puerto 8000): una a la vez
  workers: 1,
  fullyParallel: false,
  retries: 0,
  timeout: 60_000,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    locale: 'es-MX',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'tienda',
      testDir: 'e2e/tienda',
      use: {
        ...devices['Desktop Chrome'],
        baseURL: process.env.TIENDA_URL ?? 'http://localhost:8080',
        viewport: { width: 1280, height: 900 },
      },
    },
    {
      name: 'sgt',
      testDir: 'e2e/sgt',
      // Cada prueba abre la app con una base de datos nueva
      timeout: 120_000,
    },
  ],
})
