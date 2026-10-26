/**
 * @file Utilidades de formato que usan SGT de escritorio y la tienda en línea.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

/**
 * Primera letra en mayúscula y el resto en minúsculas: "disponible" → "Disponible".
 *
 * @param {string} str Texto
 * @returns {string} El texto con formato ('' si no es texto)
 */
export function capitalize(str) {
  if (typeof str !== 'string' || str.length === 0) return ''
  return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase()
}

const numero = new Intl.NumberFormat('es-MX')

/**
 * Número con separador de miles: 1250 → 1,250.
 *
 * @param {number|string} valor Número
 * @returns {string} El número con formato (0 si no es un número)
 */
export function formatoNumero(valor) {
  const n = Number(valor)
  return numero.format(Number.isFinite(n) ? n : 0)
}

/**
 * Cantidad con su palabra en singular o plural: 1 producto, 2 productos.
 *
 * @param {number} n        Cantidad
 * @param {string} singular Palabra en singular
 * @param {string} [plural] Palabra en plural (por defecto, el singular con "s")
 * @returns {string} La cantidad con formato y la palabra
 */
export function cuenta(n, singular, plural = `${singular}s`) {
  return `${formatoNumero(n)} ${Number(n) === 1 ? singular : plural}`
}

const moneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' })

/**
 * Cantidad en pesos mexicanos: 1234.5 → $1,234.50.
 *
 * @param {number|string} monto Cantidad en pesos
 * @returns {string} La cantidad con formato ($0.00 si no es un número)
 */
export function formatoMoneda(monto) {
  const n = Number(monto)
  return moneda.format(Number.isFinite(n) ? n : 0)
}
