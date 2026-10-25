// MariaDB empaquetada (solo en la app instalada): arranque, cuentas y apagado
// Los datos viven en userData/mysql_data. El servidor escucha solo en 127.0.0.1, en un puerto libre.
// Las contraseñas de root y del usuario de la app se generan al azar en el primer arranque y se guardan
// en userData/credenciales-bd.json. Nunca van en la línea de comandos: el cliente las lee de MYSQL_PWD.
// Servidor, instalador y cliente van con --no-defaults (siempre primero): así no leen el my.cnf / my.ini
// de un MySQL o MariaDB que ya esté instalado en el equipo (por ejemplo XAMPP).

import { app } from 'electron'
import { spawn, execFileSync } from 'node:child_process'
import crypto from 'node:crypto'
import fs from 'node:fs'
import path from 'node:path'
import { puertoLibre, puertoAbierto, esperar } from './puertos'

const NOMBRE_BD = 'sgtdb'
const USUARIO_APP = 'sgt_root'
// Hasta la fase 5 el puerto era fijo: una MariaDB que haya quedado viva de esa versión estaría ahí
const PUERTO_ANTERIOR = 3307

let proceso = null
let puerto = null
let credenciales = null
let ultimoMensaje = ''

export function plataforma() {
  return process.platform === 'win32' ? 'win' : process.platform === 'darwin' ? 'mac' : 'linux'
}

const baseDir = () => path.join(process.resourcesPath, 'mysql', plataforma())
const binario = (nombre) => path.join(baseDir(), 'bin', plataforma() === 'win' ? `${nombre}.exe` : nombre)
const dirDatos = () => path.join(app.getPath('userData'), 'mysql_data')
const rutaCredenciales = () => path.join(app.getPath('userData'), 'credenciales-bd.json')
const rutaEstado = () => path.join(app.getPath('userData'), 'mariadb.json')

// Linux/macOS: mariadbd está enlazado dinámicamente; se le indican las bibliotecas incluidas
function entorno(contrasena) {
  const env = { ...process.env, MYSQL_PWD: contrasena ?? '' }
  const lib = path.join(baseDir(), 'lib')
  if (plataforma() === 'linux') {
    env.LD_LIBRARY_PATH = lib + (process.env.LD_LIBRARY_PATH ? `:${process.env.LD_LIBRARY_PATH}` : '')
  } else if (plataforma() === 'mac') {
    env.DYLD_FALLBACK_LIBRARY_PATH = lib + (process.env.DYLD_FALLBACK_LIBRARY_PATH ? `:${process.env.DYLD_FALLBACK_LIBRARY_PATH}` : '')
  }
  return env
}

// Ejecuta SQL como root con el cliente incluido; regresa la salida sin encabezados (columnas separadas por tab)
function sql(contrasena, sentencias, { puerto: p = puerto, baseDatos } = {}) {
  const args = ['--no-defaults', '-u', 'root', `--port=${p}`, '--host=127.0.0.1', '-N', '-B']
  if (baseDatos) args.push(baseDatos)
  return execFileSync(binario('mariadb'), args, {
    input: sentencias,
    stdio: ['pipe', 'pipe', 'pipe'],
    env: entorno(contrasena),
    timeout: 120_000,
    windowsHide: true,
  })
    .toString()
    .trim()
}

function puedeEntrar(contrasena) {
  try {
    sql(contrasena, 'SELECT 1')
    return true
  } catch {
    return false
  }
}

// Escribe un archivo que solo debe leer el usuario (en Windows, userData ya es privado del usuario)
function escribirPrivado(ruta, contenido) {
  fs.mkdirSync(path.dirname(ruta), { recursive: true })
  const temporal = `${ruta}.tmp`
  fs.writeFileSync(temporal, contenido, { mode: 0o600 })
  fs.renameSync(temporal, ruta)
}

function cargarCredenciales() {
  try {
    const guardadas = JSON.parse(fs.readFileSync(rutaCredenciales(), 'utf8'))
    if (guardadas.root && guardadas.app) return guardadas
  } catch {
    /* primer arranque */
  }
  const alAzar = () => crypto.randomBytes(24).toString('base64url')
  const nuevas = { root: alAzar(), app: alAzar() }
  escribirPrivado(rutaCredenciales(), JSON.stringify(nuevas))
  return nuevas
}

function mismaRuta(a, b) {
  const normal = (ruta) => path.resolve(ruta).replace(/[\\/]+$/, '')
  return plataforma() === 'win' ? normal(a).toLowerCase() === normal(b).toLowerCase() : normal(a) === normal(b)
}

// ── Servidor ──────────────────────────────────────────────────────────────────

function inicializarDatos() {
  const datos = dirDatos()
  if (fs.existsSync(path.join(datos, 'mysql'))) return

  console.log('[MySQL] Primera ejecución: inicializando directorio de datos...')
  fs.mkdirSync(datos, { recursive: true })

  if (plataforma() === 'win') {
    const archivos = [
      'mariadb_system_tables.sql',
      'mariadb_system_tables_data.sql',
      'mariadb_performance_tables.sql',
      'mariadb_sys_schema.sql',
    ]
    let script = 'CREATE DATABASE IF NOT EXISTS mysql;\nUSE mysql;\n'
    for (const archivo of archivos) {
      script += fs.readFileSync(path.join(baseDir(), 'share', archivo), 'utf8') + '\n'
    }
    execFileSync(binario('mariadbd'), ['--no-defaults', '--bootstrap', `--datadir=${datos}`, `--basedir=${baseDir()}`], {
      input: script,
      env: entorno(),
      stdio: ['pipe', 'pipe', 'pipe'],
      windowsHide: true,
    })
  } else {
    execFileSync(
      path.join(baseDir(), 'scripts', 'mariadb-install-db'),
      ['--no-defaults', `--datadir=${datos}`, `--basedir=${baseDir()}`, '--auth-root-authentication-method=normal'],
      { stdio: 'pipe', env: entorno() }
    )
  }
}

function lanzar(extra = []) {
  const datos = dirDatos()
  ultimoMensaje = ''
  const nuevo = spawn(
    binario('mariadbd'),
    [
      '--no-defaults',
      `--datadir=${datos}`,
      `--basedir=${baseDir()}`,
      `--port=${puerto}`,
      '--bind-address=127.0.0.1',
      `--socket=${path.join(datos, 'mysql.sock')}`,
      '--console',
      ...extra,
    ],
    { windowsHide: true, env: entorno() }
  )
  proceso = nuevo

  nuevo.on('error', (err) => console.error('[MySQL] Error al lanzar el proceso:', err))
  nuevo.stdout.on('data', (d) => console.log('[MySQL stdout]', d.toString().trim()))
  nuevo.stderr.on('data', (d) => {
    const texto = d.toString().trim()
    ultimoMensaje = texto.split('\n').slice(-3).join('\n')
    console.log('[MySQL stderr]', texto)
  })
  nuevo.on('close', (code, signal) => {
    console.log(`[MySQL] cerrado código=${code} signal=${signal}`)
    if (proceso === nuevo) proceso = null
  })
}

async function esperarListo() {
  const listo = await esperar(async () => {
    if (!proceso) throw new Error(`MariaDB se cerró al arrancar.\n${ultimoMensaje}`)
    return puertoAbierto(puerto)
  }, 120)
  if (!listo) throw new Error('MariaDB no respondió después de 60 segundos')
  console.log('[MySQL] listo.')
}

// Apaga el servidor de forma ordenada (SHUTDOWN); si no se puede entrar, termina el proceso
async function detener(contrasena) {
  const actual = proceso
  if (!actual) return
  const cerrado = new Promise((resolve) => actual.once('close', resolve))
  try {
    sql(contrasena, 'SHUTDOWN')
  } catch {
    actual.kill()
  }
  await Promise.race([cerrado, new Promise((resolve) => setTimeout(resolve, 30_000))])
}

// Una MariaDB que quedó viva de una ejecución que terminó mal tiene bloqueados los datos: se apaga antes de arrancar.
// Solo si es la nuestra (mismo directorio de datos)
async function apagarHuerfana() {
  let anterior = PUERTO_ANTERIOR
  try {
    anterior = JSON.parse(fs.readFileSync(rutaEstado(), 'utf8')).puerto
  } catch {
    /* sin registro: primer arranque o versión anterior */
  }
  if (!(await puertoAbierto(anterior))) return

  for (const contrasena of [credenciales.root, '']) {
    try {
      if (!mismaRuta(sql(contrasena, 'SELECT @@datadir', { puerto: anterior }), dirDatos())) return
      console.log(`[MySQL] Apagando la MariaDB que quedó abierta en el puerto ${anterior}`)
      sql(contrasena, 'SHUTDOWN', { puerto: anterior })
      await esperar(async () => !(await puertoAbierto(anterior)))
      return
    } catch {
      /* se prueba con la otra contraseña */
    }
  }
}

// ── Cuentas y esquema ─────────────────────────────────────────────────────────

// root con la contraseña guardada en todas sus cuentas, sin usuarios anónimos ni la base de datos test
function asegurarRoot(contrasenaActual, { recargarPermisos = false } = {}) {
  const cuentas = sql(contrasenaActual, "SELECT CONCAT(QUOTE(User), '@', QUOTE(Host)), User FROM mysql.user WHERE User IN ('root', '')")
    .split('\n')
    .filter(Boolean)
    .map((linea) => linea.split('\t'))

  const sentencias = [
    recargarPermisos ? 'FLUSH PRIVILEGES;' : '',
    ...cuentas.map(([cuenta, usuario]) =>
      usuario === 'root' ? `ALTER USER ${cuenta} IDENTIFIED BY '${credenciales.root}';` : `DROP USER ${cuenta};`
    ),
    'DROP DATABASE IF EXISTS test;',
  ]
  sql(contrasenaActual, sentencias.join('\n'))
}

// Si la contraseña guardada ya no sirve (se borró credenciales-bd.json), se reinicia MariaDB sin permisos
// para ponerle la nueva y se vuelve a arrancar normal
async function restablecerRoot() {
  console.warn('[MySQL] La contraseña de root no coincide: se restablece')
  await detener(credenciales.root)
  lanzar(['--skip-grant-tables'])
  await esperarListo()
  asegurarRoot('', { recargarPermisos: true })
  await detener(credenciales.root)
  lanzar()
  await esperarListo()
}

async function prepararCuentas(esquema) {
  if (!puedeEntrar(credenciales.root)) {
    // Primer arranque o instalación anterior a la fase 6: root no tenía contraseña
    if (puedeEntrar('')) asegurarRoot('')
    else await restablecerRoot()
  }
  const root = credenciales.root

  // El esquema base se importa solo si la base de datos no existe; las migraciones las aplica seeder.php
  const tablas = Number(sql(root, `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${NOMBRE_BD}'`))
  if (tablas === 0) {
    console.log('[MySQL] Creando la base de datos...')
    sql(root, `CREATE DATABASE IF NOT EXISTS ${NOMBRE_BD} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`)
    sql(root, fs.readFileSync(esquema, 'utf8'), { baseDatos: NOMBRE_BD })
  }

  // Usuario de la app, en cada arranque: así su contraseña siempre es la guardada
  const contrasena = credenciales.app
  sql(
    root,
    [
      `CREATE USER IF NOT EXISTS '${USUARIO_APP}'@'127.0.0.1' IDENTIFIED BY '${contrasena}';`,
      `CREATE USER IF NOT EXISTS '${USUARIO_APP}'@'localhost' IDENTIFIED BY '${contrasena}';`,
      `ALTER USER '${USUARIO_APP}'@'127.0.0.1' IDENTIFIED BY '${contrasena}', '${USUARIO_APP}'@'localhost' IDENTIFIED BY '${contrasena}';`,
      `GRANT ALL PRIVILEGES ON ${NOMBRE_BD}.* TO '${USUARIO_APP}'@'127.0.0.1', '${USUARIO_APP}'@'localhost';`,
    ].join('\n')
  )
}

// ── API del módulo ────────────────────────────────────────────────────────────

// Arranca MariaDB y deja lista la base de datos. `esquema` es la ruta de database.sql
// Regresa los datos de conexión para el backend
export async function iniciar({ esquema }) {
  credenciales = cargarCredenciales()
  await apagarHuerfana()
  inicializarDatos()

  puerto = await puertoLibre()
  escribirPrivado(rutaEstado(), JSON.stringify({ puerto }))
  lanzar()
  await esperarListo()
  await prepararCuentas(esquema)

  return { host: '127.0.0.1', puerto, baseDatos: NOMBRE_BD, usuario: USUARIO_APP, contrasena: credenciales.app }
}

// Al cerrar la app: SHUTDOWN para que MariaDB termine de escribir sus datos (en Windows, kill la cortaría)
export function apagar() {
  if (!proceso) return
  try {
    sql(credenciales.root, 'SHUTDOWN')
  } catch {
    proceso.kill()
  }
}
