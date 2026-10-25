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

function getPhpBinary() {
  if (!app.isPackaged) return 'php'
  const platform = getPlatform()
  const binary = platform === 'win' ? 'php.exe' : 'php'
  return path.join(process.resourcesPath, 'php', platform, binary)
}

function getBackendPath() {
  if (!app.isPackaged) return path.join(__dirname, '../../backend')
  return path.join(process.resourcesPath, 'backend')
}

// ── PHP ───────────────────────────────────────────────────────────────────────

// pid y puerto del PHP en ejecución, para encontrarlo si la app se cierra mal y queda vivo
const rutaEstadoPHP = () => path.join(app.getPath('userData'), 'php.json')

// Un PHP que quedó vivo de una ejecución que terminó mal se cierra al arrancar,
// solo si en su puerto responde el API con el mismo pid (así no se cierra otro proceso)
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
