import net from 'node:net'

// Puerto TCP libre en 127.0.0.1 (el sistema operativo lo elige)
export function puertoLibre() {
  return new Promise((resolve, reject) => {
    const servidor = net.createServer()
    servidor.unref()
    servidor.on('error', reject)
    servidor.listen(0, '127.0.0.1', () => {
      const { port } = servidor.address()
      servidor.close(() => resolve(port))
    })
  })
}

// true si algo escucha en ese puerto de 127.0.0.1
export function puertoAbierto(puerto) {
  return new Promise((resolve) => {
    const conexion = net.connect({ port: puerto, host: '127.0.0.1' })
    const terminar = (abierto) => {
      conexion.destroy()
      resolve(abierto)
    }
    conexion.setTimeout(1000)
    conexion.once('connect', () => terminar(true))
    conexion.once('error', () => terminar(false))
    conexion.once('timeout', () => terminar(false))
  })
}

// Repite `condicion` (async) hasta que sea verdadera; false si se acaban los intentos
export async function esperar(condicion, intentos = 60, pausaMs = 500) {
  for (let i = 0; i < intentos; i++) {
    if (await condicion()) return true
    await new Promise((r) => setTimeout(r, pausaMs))
  }
  return false
}
