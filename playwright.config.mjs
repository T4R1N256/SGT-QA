import { defineConfig, devices } from '@playwright/test'

// Pruebas en el navegador de la tienda en línea (fase T5). Corren contra la tienda levantada con Docker:
//   cp .env.example .env && docker compose up --build -d
//   npm run test:e2e:tienda
// TIENDA_URL (tienda) y SGT_API (backend, para preparar datos como admin) permiten apuntar a otra instancia
export default defineConfig({
  testDir: 'e2e/tienda',
  // Las pruebas comparten la base de datos de la tienda: una a la vez
  workers: 1,
  fullyParallel: false,
  retries: 0,
  timeout: 60_000,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: process.env.TIENDA_URL ?? 'http://localhost:8080',
    locale: 'es-MX',
    viewport: { width: 1280, height: 900 },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
    },
  ],
})
