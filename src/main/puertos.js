/**
 * @file Puertos TCP locales para PHP y MariaDB (proceso principal de Electron).
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import net from 'node:net'

/**
 * Puerto TCP libre en 127.0.0.1 (el sistema operativo lo elige).
 *
 * @returns {Promise<number>} El puerto
 * @throws {Error} Si no se puede abrir un servidor (la promesa se rechaza)
 */
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

/**
 * Indica si algo escucha en ese puerto de 127.0.0.1 (espera hasta 1 segundo).
 *
 * @param {number} puerto Puerto
 * @returns {Promise<boolean>} true si aceptó la conexión
 */
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

/**
 * Repite `condicion` hasta que sea verdadera.
 *
 * @param {function(): Promise<boolean>} condicion Condición asíncrona
 * @param {number} [intentos=60] Máximo de intentos
 * @param {number} [pausaMs=500] Pausa entre intentos, en milisegundos
 * @returns {Promise<boolean>} true si se cumplió; false si se acabaron los intentos
 */
export async function esperar(condicion, intentos = 60, pausaMs = 500) {
  for (let i = 0; i < intentos; i++) {
    if (await condicion()) return true
    await new Promise((r) => setTimeout(r, pausaMs))
  }
  return false
}
