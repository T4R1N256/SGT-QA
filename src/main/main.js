/**
 * @file Proceso principal de Electron: arranca MariaDB (app instalada), PHP y el servidor del renderer,
 * aplica la regla 4 (no se cierra con la caja abierta) y genera los PDF de tickets y reportes.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import * as mariadb from './mariadb'
import { puertoLibre } from './puertos'
const { app, BrowserWindow, ipcMain, dialog, shell } = require('electron')
const { spawn, execFileSync } = require('child_process')
const path = require('node:path')
const fs = require('node:fs')
const http = require('node:http')

let phpProcess = null

// Regla 4: el sistema no se puede cerrar con la caja abierta. El renderer avisa por IPC ('caja:estado')
let cajaAbierta = false

// URL con la que se carga el renderer compilado (ver servirRenderer)
let rendererURL = null

// URL del API: PHP escucha en un puerto libre que se le pasa al renderer por el preload
let apiURL = null

// Helpers
const getPlatform = mariadb.plataforma

/**
 * Ruta del ejecutable de PHP: el del sistema en desarrollo, el incluido en la app instalada.
 *
 * @returns {string} La ruta o el nombre del ejecutable
 */
function getPhpBinary() {
  if (!app.isPackaged) return 'php'
  const platform = getPlatform()
  const binary = platform === 'win' ? 'php.exe' : 'php'
  return path.join(process.resourcesPath, 'php', platform, binary)
}

/**
 * Carpeta del backend PHP: la del repositorio en desarrollo, la de resources en la app instalada.
 *
 * @returns {string} La ruta del backend
 */
function getBackendPath() {
  if (!app.isPackaged) return path.join(__dirname, '../../backend')
  return path.join(process.resourcesPath, 'backend')
}

// ── PHP ───────────────────────────────────────────────────────────────────────

// pid y puerto del PHP en ejecución, para encontrarlo si la app se cierra mal y queda vivo
const rutaEstadoPHP = () => path.join(app.getPath('userData'), 'php.json')

/**
 * Un PHP que quedó vivo de una ejecución que terminó mal se cierra al arrancar,
 * solo si en su puerto responde el API con el mismo pid (así no se cierra otro proceso).
 *
 * @returns {Promise<void>}
 */
async function cerrarPHPHuerfano() {
  let anterior
  try {
    anterior = JSON.parse(fs.readFileSync(rutaEstadoPHP(), 'utf8'))
  } catch {
    return
  }
  try {
    const res = await fetch(`http://localhost:${anterior.puerto}/api/ping`, { signal: AbortSignal.timeout(1000) })
    const datos = await res.json()
    if (datos.pid === anterior.pid) {
      process.kill(anterior.pid)
      console.log(`[PHP] Se cerró el PHP que quedó abierto en el puerto ${anterior.puerto}`)
    }
  } catch {
    /* ya no está */
  }
}

/**
 * Levanta el servidor de PHP (php -S) con backend/index.php como router y guarda su pid y su puerto
 * en userData/php.json.
 *
 * @param {number} puerto Puerto en el que escucha PHP
 * @returns {void}
 */
function spawnPHP(puerto) {
  const phpBin = getPhpBinary()
  const backend = getBackendPath()
  const router = path.join(backend, 'index.php')

  phpProcess = spawn(phpBin, ['-S', `localhost:${puerto}`, '-t', backend, router], {
    windowsHide: true,
  })
  apiURL = `http://localhost:${puerto}/api`
  if (phpProcess.pid) fs.writeFileSync(rutaEstadoPHP(), JSON.stringify({ pid: phpProcess.pid, puerto }))

  phpProcess.on('error', (err) => console.error('[PHP] No se pudo iniciar:', err.message))
  phpProcess.stderr.on('data', (d) => console.log('[PHP]', d.toString().trim()))
  phpProcess.on('close', (code) => console.log('[PHP] cerrado con código:', code))
}

/**
 * Espera a que el API responda en /api/ping.
 *
 * @param {number} [tries=20] Intentos, cada medio segundo
 * @returns {Promise<void>}
 * @throws {Error} "El servidor PHP no respondió" si no responde o el proceso terminó
 */
async function waitPHP(tries = 20) {
  for (let i = 0; i < tries; i++) {
    // No se pudo lanzar (no hay PHP) o ya terminó: no tiene caso seguir esperando
    if (!phpProcess?.pid || phpProcess.exitCode !== null) break
    try {
      const res = await fetch(`${apiURL}/ping`)
      if (res.ok) {
        console.log('[PHP] listo en', apiURL)
        return
      }
    } catch (_) { /* todavía arrancando */ }
    await new Promise((r) => setTimeout(r, 500))
  }
  throw new Error('El servidor PHP no respondió')
}

/**
 * Ejecuta seeder.php: migraciones, admin inicial y datos de demostración. Un error se registra
 * en la consola y no detiene el arranque.
 *
 * @returns {void}
 */
function runSeeders() {
  const phpBin = getPhpBinary()
  const configDir = path.join(getBackendPath(), 'config')
  try {
    execFileSync(phpBin, [path.join(configDir, 'seeder.php')], { stdio: 'inherit' })
  } catch (e) {
    console.error('[Seeder] Error:', e.message)
  }
}

// ── Renderer compilado ───────────────────────────────────────────────────────

// Sirve el renderer compilado por http://localhost:<puerto libre>, igual que Vite en desarrollo.
// Cargado con file://, Chromium rechaza las respuestas del API con credenciales (CORS) y no envía
// la cookie de sesión, así que la app compilada no podía iniciar sesión.
// Con http://localhost el API (localhost:<puerto de PHP>) es del mismo sitio y la cookie funciona.
const TIPOS_MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
}

/**
 * Levanta el servidor HTTP del renderer compilado en un puerto libre (ver la explicación de arriba).
 * Solo sirve archivos dentro de la carpeta del renderer.
 *
 * @returns {Promise<string>} La URL del servidor
 */
function servirRenderer() {
  const raiz = path.join(__dirname, '../renderer')

  const server = http.createServer((req, res) => {
    let ruta
    try {
      ruta = decodeURIComponent(new URL(req.url, 'http://localhost').pathname)
    } catch {
      res.writeHead(400).end()
      return
    }
    const archivo = path.join(raiz, ruta === '/' ? 'index.html' : ruta)

    // Solo archivos dentro de la carpeta del renderer
    if (path.relative(raiz, archivo).startsWith('..')) {
      res.writeHead(403).end()
      return
    }

    fs.readFile(archivo, (err, contenido) => {
      if (err) {
        res.writeHead(404).end()
        return
      }
      res.writeHead(200, { 'Content-Type': TIPOS_MIME[path.extname(archivo)] ?? 'application/octet-stream' })
      res.end(contenido)
    })
  })

  return new Promise((resolve, reject) => {
    server.on('error', reject)
    server.listen(0, 'localhost', () => resolve(`http://localhost:${server.address().port}`))
  })
}

// ── PDF (tickets y reportes) ─────────────────────────────────────────────────

// El renderer arma el HTML del documento y el proceso principal lo convierte a PDF con Chromium
// (printToPDF), sin dependencias externas y sin internet. Se guarda en Documentos/SGT/<carpeta>.
const CARPETAS_PDF = ['Tickets', 'Reportes']
const ANCHO_TICKET_MM = 80
const PX_POR_PULGADA = 96

/**
 * Convierte a PDF el HTML que armó el renderer. El ticket mide 80 mm de ancho y su alto se ajusta
 * al contenido; los reportes son tamaño carta con el número de página al pie. Se guarda como
 * Documentos/SGT/<carpeta>/<nombre>.pdf y se abre con el visor del sistema sin esperarlo.
 *
 * @param {Object} opciones
 * @param {string} opciones.html Documento completo
 * @param {'Tickets'|'Reportes'} opciones.carpeta Carpeta dentro de Documentos/SGT
 * @param {string} opciones.nombre Nombre del archivo sin extensión (letras, números, punto, guion)
 * @param {'ticket'|'carta'} [opciones.formato='ticket'] Tamaño de la hoja
 * @param {boolean} [opciones.abrir=true] Si se abre al terminar
 * @returns {Promise<string>} La ruta del PDF
 * @throws {Error} Si el documento está vacío o es muy grande, la carpeta no está permitida o el nombre no es válido
 */
async function generarPDF({ html, carpeta, nombre, formato = 'ticket', abrir = true } = {}) {
  if (typeof html !== 'string' || html.length === 0 || html.length > 5_000_000) {
    throw new Error('El documento está vacío o es demasiado grande')
  }
  if (!CARPETAS_PDF.includes(carpeta)) throw new Error(`Carpeta no permitida: ${carpeta}`)
  if (!/^[\w.-]{1,100}$/.test(nombre ?? '')) throw new Error('Nombre de archivo no válido')

  const esTicket = formato === 'ticket'
  const anchoPx = Math.round((ANCHO_TICKET_MM / 25.4) * PX_POR_PULGADA)

  const ventana = new BrowserWindow({
    show: false,
    width: esTicket ? anchoPx : 816,
    // Ventana baja: scrollHeight nunca es menor que la ventana, así el alto medido es el del contenido
    height: esTicket ? 50 : 600,
    webPreferences: { sandbox: true, contextIsolation: true, nodeIntegration: false },
  })

  try {
    await ventana.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(html)}`)

    let pageSize = 'Letter'
    if (esTicket) {
      // El ticket mide 80 mm de ancho y su alto se ajusta al contenido
      const altoPx = await ventana.webContents.executeJavaScript('document.documentElement.scrollHeight')
      pageSize = { width: ANCHO_TICKET_MM / 25.4, height: Math.max(altoPx / PX_POR_PULGADA + 0.1, 2) }
    }

    // Márgenes en pulgadas; los reportes (carta) llevan el número de página al pie
    const pdf = await ventana.webContents.printToPDF({
      pageSize,
      printBackground: true,
      margins: esTicket ? { top: 0, bottom: 0, left: 0, right: 0 } : { top: 0.5, bottom: 0.6, left: 0.5, right: 0.5 },
      displayHeaderFooter: !esTicket,
      headerTemplate: '<span></span>',
      footerTemplate:
        '<div style="width:100%;font-size:8px;color:#555;text-align:center;font-family:Arial,sans-serif;">' +
        'Página <span class="pageNumber"></span> de <span class="totalPages"></span></div>',
    })

    const directorio = path.join(app.getPath('documents'), 'SGT', carpeta)
    fs.mkdirSync(directorio, { recursive: true })
    const ruta = path.join(directorio, `${nombre}.pdf`)
    fs.writeFileSync(ruta, pdf)

    // Se abre con el visor de PDF del sistema para imprimirlo. No se espera: si no hay visor
    // (o tarda en abrir) el archivo ya quedó guardado y la venta no debe quedarse esperando
    if (abrir) {
      shell.openPath(ruta).then((error) => error && console.error('[PDF] No se pudo abrir:', error))
    }

    return ruta
  } finally {
    ventana.destroy()
  }
}

// ── Create window ─────────────────────────────────────────────────────────────

/**
 * Crea la ventana principal. Regla 4: con la caja abierta no se deja cerrar y se avisa al usuario.
 *
 * @returns {void}
 */
function createWindow() {
  const win = new BrowserWindow({
    width: 1200,
    height: 768,
    webPreferences: {
      preload: path.join(__dirname, '../preload/preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      additionalArguments: [`--sgt-api=${apiURL}`],
    },
  })

  // Vite en desarrollo, o el servidor local del renderer compilado
  win.loadURL(process.env['ELECTRON_RENDERER_URL'] || rendererURL)

  win.on('close', (event) => {
    if (!cajaAbierta) return
    event.preventDefault()
    dialog.showMessageBox(win, {
      type: 'warning',
      title: 'Caja abierta',
      message: 'No puedes cerrar el sistema con la caja abierta.',
      detail: 'Haz el corte desde el módulo Caja y vuelve a intentarlo.',
      buttons: ['Entendido'],
    })
  })
}

// App lifecycle

app.whenReady().then(async () => {
  try {
    if (app.isPackaged) {
      const bd = await mariadb.iniciar({ esquema: path.join(getBackendPath(), 'config', 'database.sql') })
      Object.assign(process.env, {
        DB_HOST: bd.host,
        DB_PORT: String(bd.puerto),
        DB_NAME: bd.baseDatos,
        DB_USER: bd.usuario,
        DB_PASS: bd.contrasena,
        APP_ENV: 'production',
      })
    }

    if (!process.env['ELECTRON_RENDERER_URL']) {
      rendererURL = await servirRenderer()
      // El backend solo acepta peticiones con credenciales de este origen (ver CORS en index.php)
      process.env.APP_ORIGIN = rendererURL
    }

    await cerrarPHPHuerfano()
    // En la app instalada PHP usa un puerto libre. En desarrollo se queda en el 8000, como dice el README
    // (con Docker, el renderer usa el backend del contenedor por VITE_API_URL)
    spawnPHP(app.isPackaged ? await puertoLibre() : 8000)
    try {
      await waitPHP()
    } catch (e) {
      // En desarrollo con Docker no hace falta el PHP del sistema: el renderer usa el backend del contenedor
      if (app.isPackaged) throw e
      console.warn('[PHP] No arrancó el PHP local; la app usará el backend de VITE_API_URL (Docker)')
    }

    runSeeders()
  } catch (e) {
    console.error('[Arranque]', e)
    dialog.showErrorBox(
      'No se pudo iniciar SGT',
      `${e.message}\n\nCierra la aplicación y vuelve a abrirla. Si el problema continúa, reinicia el equipo.`
    )
    app.quit()
    return
  }

  ipcMain.handle('ping', () => 'pong')
  ipcMain.on('caja:estado', (_event, abierta) => {
    cajaAbierta = Boolean(abierta)
  })
  ipcMain.handle('pdf:generar', async (_event, opciones) => {
    try {
      return { ok: true, ruta: await generarPDF(opciones) }
    } catch (e) {
      console.error('[PDF]', e)
      return { ok: false, mensaje: e.message }
    }
  })

  createWindow()

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow()
  })
})

app.on('will-quit', () => {
  if (phpProcess) {
    phpProcess.kill()
    fs.rmSync(rutaEstadoPHP(), { force: true })
  }
  if (app.isPackaged) mariadb.apagar()
})

app.on('window-all-closed', () => {
  if (getPlatform() !== 'mac') app.quit()
})
