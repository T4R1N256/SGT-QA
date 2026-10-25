
const { app, BrowserWindow, ipcMain, dialog } = require('electron')
const { spawn, execFileSync } = require('child_process')
const path = require('node:path')
const fs = require('node:fs')
const http = require('node:http')

let phpProcess = null
let mysqlProcess = null

// Regla 4: el sistema no se puede cerrar con la caja abierta. El renderer avisa por IPC ('caja:estado')
let cajaAbierta = false

// URL con la que se carga el renderer compilado (ver servirRenderer)
let rendererURL = null

// Local MySQL credentials — only used in the packaged app (127.0.0.1 only, never exposed)
const LOCAL_DB_PORT = '3307'
const LOCAL_DB_NAME = 'sgtdb'
const LOCAL_DB_USER = 'sgt_root'
const LOCAL_DB_PASS = 'pass.2$asdf7'

// Helpers
function getPlatform() {
  return process.platform === 'win32' ? 'win' : process.platform === 'darwin' ? 'mac' : 'linux'
}

function getPhpBinary() {
  if (!app.isPackaged) return 'php'
  const platform = getPlatform()
  const binary = platform === 'win' ? 'php.exe' : 'php'
  return path.join(process.resourcesPath, 'php', platform, binary)
}

function getMysqldBinary() {
  const platform = getPlatform()
  const binary = platform === 'win' ? 'mariadbd.exe' : 'mariadbd'
  return path.join(process.resourcesPath, 'mysql', platform, 'bin', binary)
}

function getMysqlClientBinary() {
  const platform = getPlatform()
  const binary = platform === 'win' ? 'mariadb.exe' : 'mariadb'
  return path.join(process.resourcesPath, 'mysql', platform, 'bin', binary)
}

function getMysqlBaseDir() {
  return path.join(process.resourcesPath, 'mysql', getPlatform())
}

// Linux/macOS: mysqld is dynamically linked — point the linker at our bundled libs
function getMysqlEnv() {
  const env = { ...process.env }
  const libDir = path.join(getMysqlBaseDir(), 'lib')
  const platform = getPlatform()
  if (platform === 'linux') {
    env.LD_LIBRARY_PATH = libDir + (process.env.LD_LIBRARY_PATH ? `:${process.env.LD_LIBRARY_PATH}` : '')
  } else if (platform === 'mac') {
    env.DYLD_FALLBACK_LIBRARY_PATH = libDir + (process.env.DYLD_FALLBACK_LIBRARY_PATH ? `:${process.env.DYLD_FALLBACK_LIBRARY_PATH}` : '')
  }
  return env
}

function getBackendPath() {
  if (!app.isPackaged) return path.join(__dirname, '../../backend')
  return path.join(process.resourcesPath, 'backend')
}

function getDataDir() {
  // userData persists across app updates and is writable — unlike resourcesPath
  return path.join(app.getPath('userData'), 'mysql_data')
}

// ── MySQL ─────────────────────────────────────────────────────────────────────

function initMySQLDataDir() {
  const dataDir = getDataDir()
  if (fs.existsSync(path.join(dataDir, 'mysql'))) return

  console.log('[MySQL] Primera ejecución: inicializando directorio de datos...')
  fs.mkdirSync(dataDir, { recursive: true })

  const platform = getPlatform()
  const baseDir = getMysqlBaseDir()

  if (platform === 'win') {
    const mariadbd = path.join(baseDir, 'bin', 'mariadbd.exe')
    const shareDir = path.join(baseDir, 'share')

    const sqlFiles = [
      'mariadb_system_tables.sql',
      'mariadb_system_tables_data.sql',
      'mariadb_performance_tables.sql',
      'mariadb_sys_schema.sql',
    ]

    let sql = 'CREATE DATABASE IF NOT EXISTS mysql;\nUSE mysql;\n'
    for (const file of sqlFiles) {
      sql += fs.readFileSync(path.join(shareDir, file), 'utf8') + '\n'
    }

    execFileSync(mariadbd, [
      '--bootstrap',
      `--datadir=${dataDir}`,
      `--basedir=${baseDir}`,
    ], {
      input: sql,
      env: getMysqlEnv(),
      stdio: ['pipe', 'pipe', 'pipe'],
    })
  } else {
    const installScript = path.join(baseDir, 'scripts', 'mariadb-install-db')
    execFileSync(installScript, [
      `--datadir=${dataDir}`,
      `--basedir=${baseDir}`,
      '--auth-root-authentication-method=normal',
    ], { stdio: 'pipe', env: getMysqlEnv() })
  }
}

function spawnMySQL() {
  const dataDir = getDataDir()

  mysqlProcess = spawn(getMysqldBinary(), [
    `--datadir=${dataDir}`,
    `--basedir=${getMysqlBaseDir()}`,
    `--port=${LOCAL_DB_PORT}`,
    '--bind-address=127.0.0.1',
    `--socket=${path.join(dataDir, 'mysql.sock')}`,
    '--console',
  ], { windowsHide: true, env: getMysqlEnv() })

  mysqlProcess.on('error', (err) => console.error('[MySQL] ERROR AL LANZAR PROCESO:', err))
  mysqlProcess.stdout.on('data', (d) => console.log('[MySQL stdout]', d.toString().trim()))
  mysqlProcess.stderr.on('data', (d) => console.log('[MySQL stderr]', d.toString().trim()))
  mysqlProcess.on('close', (code, signal) => console.log(`[MySQL] cerrado código=${code} signal=${signal}`))
}

async function waitMySQL(tries = 30) {
  const args = ['-u', 'root', `--port=${LOCAL_DB_PORT}`, '--host=127.0.0.1', '-e', 'SELECT 1']
  for (let i = 0; i < tries; i++) {
    try {
      execFileSync(getMysqlClientBinary(), args, { stdio: 'pipe', env: getMysqlEnv() })
      console.log('[MySQL] listo.')
      return
    } catch (_) { /* todavía arrancando */ }
    await new Promise((r) => setTimeout(r, 500))
  }
  throw new Error('[MySQL] no respondió después de varios intentos')
}

// Imports the schema and creates the application user — first run only
function setupDatabase() {
  console.log('[MySQL] Configurando base de datos...')
  const clientArgs = ['-u', 'root', `--port=${LOCAL_DB_PORT}`, '--host=127.0.0.1']

  execFileSync(getMysqlClientBinary(), clientArgs, {
    input: `CREATE DATABASE IF NOT EXISTS \`${LOCAL_DB_NAME}\`;`,
    stdio: ['pipe', 'pipe', 'pipe'],
    env: getMysqlEnv(),
  })

  const schemaSQL = fs.readFileSync(
    path.join(getBackendPath(), 'config', 'database.sql'), 'utf8'
  )
  execFileSync(getMysqlClientBinary(), [...clientArgs, LOCAL_DB_NAME], {
    input: schemaSQL,
    stdio: ['pipe', 'pipe', 'pipe'],
    env: getMysqlEnv(),
  })

  const userSQL = [
    `CREATE OR REPLACE USER '${LOCAL_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${LOCAL_DB_PASS}';`,
    `CREATE OR REPLACE USER '${LOCAL_DB_USER}'@'localhost' IDENTIFIED BY '${LOCAL_DB_PASS}';`,
    `GRANT ALL PRIVILEGES ON ${LOCAL_DB_NAME}.* TO '${LOCAL_DB_USER}'@'127.0.0.1';`,
    `GRANT ALL PRIVILEGES ON ${LOCAL_DB_NAME}.* TO '${LOCAL_DB_USER}'@'localhost';`,
    `FLUSH PRIVILEGES;`,
  ].join('\n')
  execFileSync(getMysqlClientBinary(), clientArgs, {
    input: userSQL,
    stdio: ['pipe', 'pipe', 'pipe'],
    env: getMysqlEnv(),
  })

  console.log('[MySQL] Base de datos configurada.')
}

// ── PHP ───────────────────────────────────────────────────────────────────────

function spawnPHP() {
  const phpBin = getPhpBinary()
  const backend = getBackendPath()
  const router = path.join(backend, 'index.php')

  phpProcess = spawn(phpBin, ['-S', 'localhost:8000', '-t', backend, router], {
    windowsHide: true,
  })

  phpProcess.stderr.on('data', (d) => console.log('[PHP]', d.toString().trim()))
  phpProcess.on('close', (code) => console.log('[PHP] cerrado con código:', code))
}

async function waitPHP(tries = 10) {
  for (let i = 0; i < tries; i++) {
    try {
      const res = await fetch('http://localhost:8000/api/ping')
      if (res.ok) {
        console.log('[PHP] listo.')
        return
      }
    } catch (_) { /* todavía arrancando */ }
    await new Promise((r) => setTimeout(r, 500))
  }
  throw new Error('[PHP] no respondió después de varios intentos')
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
// Con http://localhost el API (localhost:8000) es del mismo sitio y la cookie funciona.
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

// ── Create window ─────────────────────────────────────────────────────────────

function createWindow() {
  const win = new BrowserWindow({
    width: 1200,
    height: 768,
    webPreferences: {
      preload: path.join(__dirname, '../preload/preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
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

function isDatabaseReady() {
  try {
    execFileSync(getMysqlClientBinary(),
      ['-u', LOCAL_DB_USER, `-p${LOCAL_DB_PASS}`,
       `--port=${LOCAL_DB_PORT}`, '--host=127.0.0.1',
       LOCAL_DB_NAME, '-e', 'SELECT 1'],
      { stdio: 'pipe', env: getMysqlEnv() }
    )
    return true
  } catch {
    return false
  }
}

// App lifecycle

app.whenReady().then(async () => {
  if (app.isPackaged) {
    Object.assign(process.env, {
      DB_HOST: '127.0.0.1',
      DB_PORT: LOCAL_DB_PORT,
      DB_NAME: LOCAL_DB_NAME,
      DB_USER: LOCAL_DB_USER,
      DB_PASS: LOCAL_DB_PASS,
      APP_ENV: 'production',
    })

    initMySQLDataDir()

    spawnMySQL()
    await waitMySQL()

    if (!isDatabaseReady()) {
      setupDatabase()
    }
  }

  if (!process.env['ELECTRON_RENDERER_URL']) {
    rendererURL = await servirRenderer()
    // El backend solo acepta peticiones con credenciales de este origen (ver CORS en index.php)
    process.env.APP_ORIGIN = rendererURL
  }

  spawnPHP()
  await waitPHP()

  runSeeders()

  ipcMain.handle('ping', () => 'pong')
  ipcMain.on('caja:estado', (_event, abierta) => {
    cajaAbierta = Boolean(abierta)
  })

  createWindow()

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow()
  })
})

app.on('will-quit', () => {
  if (phpProcess) phpProcess.kill()
  if (mysqlProcess) mysqlProcess.kill()
})

app.on('window-all-closed', () => {
  if (getPlatform() !== 'mac') app.quit()
})
