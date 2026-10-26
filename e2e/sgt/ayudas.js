// Ayudas de las pruebas de la app de escritorio (Electron)
// Cada prueba abre la app compilada (npm run build) con una base de datos nueva y su propia carpeta personal:
// el seeder carga el admin y los datos demo, y los tickets y reportes en PDF se guardan en esa carpeta
import { test as base, expect, request, _electron as electron } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

export { expect }

const RAIZ = path.resolve(import.meta.dirname, '../..')

// La app compilada sin empaquetar levanta el PHP del sistema en el 8000 (src/main/main.js)
export const API = 'http://localhost:8000'

// La misma base de datos que las pruebas del backend (backend/phpunit.xml)
const BASE_DE_DATOS = {
  DB_HOST: process.env.DB_HOST ?? '127.0.0.1',
  DB_PORT: process.env.DB_PORT ?? '3306',
  DB_NAME: process.env.DB_NAME ?? 'sgtdb_test',
  DB_USER: process.env.DB_USER ?? 'sgt_test',
  DB_PASS: process.env.DB_PASS ?? 'sgt_test',
}

// El backend calcula "hoy" en la zona de America/Mazatlan (config/database.php): la app y las pruebas usan la misma
export const ZONA = 'America/Mazatlan'
export const hoy = () => new Intl.DateTimeFormat('en-CA', { timeZone: ZONA }).format(new Date())

export const ADMIN = ['1', 'passadmin']
export const EMPLEADO = ['2', 'empleado123']

const esperar = (ms) => new Promise((r) => setTimeout(r, ms))

// Si la app no terminó bien (por ejemplo, con la caja abierta), su PHP puede seguir en el 8000:
// se cierra solo si es el PHP del API (responde /api/ping con su pid)
async function cerrarPHP() {
  try {
    const res = await fetch(`${API}/api/ping`, { signal: AbortSignal.timeout(1000) })
    process.kill((await res.json()).pid)
  } catch {
    /* no hay PHP en el 8000 */
  }
}

export const test = base.extend({
  // Carpeta personal de la prueba: Documentos/SGT con los PDF y los datos de Electron
  // Playwright pide desestructurar el primer argumento aunque la fixture no use otras
  // eslint-disable-next-line no-empty-pattern
  carpeta: async ({}, use) => {
    const carpeta = fs.mkdtempSync(path.join(os.tmpdir(), 'sgt-e2e-'))
    await use(carpeta)
    fs.rmSync(carpeta, { recursive: true, force: true })
  },

  app: async ({ carpeta }, use) => {
    await cerrarPHP()
    execFileSync('php', [path.join(import.meta.dirname, 'base-de-datos.php')], {
      env: { ...process.env, ...BASE_DE_DATOS },
    })
    // Los datos demo y no el catálogo de la tienda, aunque haya un .env de Docker (SEED_TIENDA=1)
    const env = {
      ...process.env,
      ...BASE_DE_DATOS,
      SEED_DEMO: '1',
      SEED_TIENDA: '0',
      HOME: carpeta,
      TZ: ZONA,
    }
    delete env.XDG_CONFIG_HOME
    delete env.ELECTRON_RENDERER_URL
    const app = await electron.launch({
      // Sin sandbox, como el smoke test de build.yml: los runners de Linux no permiten el de Chromium
      args: ['--no-sandbox', path.join(RAIZ, 'out/main/main.js')],
      cwd: RAIZ,
      env,
    })
    await use(app)
    // Con la caja abierta la ventana no se cierra (regla 4) y Electron atiende SIGTERM como un cierre normal,
    // que la misma regla bloquea: si no terminó, se mata el proceso
    await Promise.race([app.close().catch(() => {}), esperar(5000)])
    try {
      app.process().kill('SIGKILL')
    } catch {
      /* ya terminó: Playwright ya soltó el proceso */
    }
    await cerrarPHP()
  },

  // Ventana de la app en el Login. La prueba falla si la página tuvo errores de JavaScript
  page: async ({ app }, use) => {
    const page = await app.firstWindow()
    await page.setViewportSize({ width: 1400, height: 900 })
    const errores = []
    page.on('pageerror', (e) => errores.push(e.message))
    await page.getByPlaceholder('ID de usuario').waitFor()
    await use(page)
    expect(errores, 'errores de JavaScript en la app').toEqual([])
  },

  // API de SGT como admin, para preparar datos y revisar lo que guardó la app
  api: async ({ app }, use) => {
    void app
    const api = await request.newContext({ baseURL: API })
    const res = await api.post('/api/login', { data: { ID_Usuario: 1, Password: 'passadmin' } })
    expect(res.ok(), 'iniciar sesión en el API como admin').toBeTruthy()
    await use(api)
    await api.dispose()
  },
})

// ---- Pasos comunes en la app ----

export async function iniciarSesion(page, [id, password]) {
  await page.getByPlaceholder('ID de usuario').fill(id)
  await page.getByPlaceholder('Contraseña').fill(password)
  await page.getByRole('button', { name: /Iniciar Sesión/i }).click()
  await page.waitForURL(/#\/dashboard/)
}

export async function cerrarSesion(page) {
  await page.getByRole('button', { name: /Cerrar sesión/ }).click()
  await page.getByPlaceholder('ID de usuario').waitFor()
}

export const ir = (page, ruta) => page.evaluate((r) => (location.hash = r), ruta)

export const aceptar = (page) => page.getByRole('button', { name: 'Aceptar' }).click()

export async function abrirCaja(page, monto = 0) {
  await ir(page, '#/caja')
  // El cambio de página no es inmediato: otras páginas también tienen campos "0.00"
  const abrir = page.getByRole('button', { name: /Abrir caja/i })
  await abrir.waitFor()
  await page.getByPlaceholder('0.00').fill(String(monto))
  await abrir.click()
  await page.getByText('Efectivo esperado').first().waitFor()
}

// Corte de caja con el efectivo que se cuenta. La pantalla prellena ese campo con el efectivo esperado
// cuando termina de cargarlo: se escribe para no depender de esa carga, y el corte debe salir cuadrado
export async function cerrarCaja(page, contado) {
  await ir(page, '#/caja')
  await page.getByRole('button', { name: /Cerrar caja/i }).click()
  await modal(page).getByPlaceholder('0.00').fill(String(contado))
  await page.getByRole('button', { name: /Confirmar cierre/i }).click()
  await page.getByText('Corte de caja: cuadrada').waitFor()
  await aceptar(page)
}

// Agrega un producto al carrito de Ventas: busca y Enter agrega el primero de la lista
export async function agregar(page, texto, producto) {
  const buscar = page.getByPlaceholder(/Buscar producto/)
  await buscar.fill(texto)
  await page.locator('li', { hasText: producto }).first().waitFor()
  await buscar.press('Enter')
}

// Cobra la venta: en efectivo con el pago dado o con tarjeta. Regresa el aviso (lleva la ruta del ticket)
export async function cobrar(page, { pago, tarjeta = false } = {}) {
  if (tarjeta) await page.getByRole('button', { name: /tarjeta/i }).click()
  else if (pago !== undefined) await page.getByPlaceholder('0.00').fill(String(pago))
  await page.getByRole('button', { name: /Cobrar/i }).click()
  await page.getByText('Venta completada').waitFor()
  const aviso = await page.locator('text=Ticket:').first().textContent()
  await aceptar(page)
  return aviso
}

// Modal abierto (dialog) y fila de una tabla por el texto exacto de una de sus celdas
export const modal = (page) => page.locator('dialog[open]')
export const fila = (page, texto) =>
  page.locator('li').filter({ has: page.locator('span', { hasText: new RegExp(`^${texto}$`) }) })
export const sinModal = (page) =>
  page.waitForFunction(() => !document.querySelector('dialog[open]'))

// ---- PDF ----

// Electron guarda en Documentos/SGT; si el sistema no tiene la carpeta Documentos, en <carpeta personal>/SGT
export function archivoPDF(carpeta, subcarpeta, nombre) {
  return [path.join(carpeta, 'Documents', 'SGT'), path.join(carpeta, 'SGT')]
    .map((sgt) => path.join(sgt, subcarpeta, `${nombre}.pdf`))
    .find((archivo) => fs.existsSync(archivo))
}

export const textoPDF = (archivo) => execFileSync('pdftotext', ['-layout', archivo, '-']).toString()
export const paginasPDF = (archivo) =>
  Number(/Pages:\s+(\d+)/.exec(execFileSync('pdfinfo', [archivo]).toString())[1])

// ---- API ----

export async function producto(api, nombre) {
  const { productos } = await (await api.get('/api/getAllProducts')).json()
  const encontrado = productos.find((p) => p.Nombre_Producto === nombre)
  expect(encontrado, `el producto ${nombre}`).toBeTruthy()
  return encontrado
}
