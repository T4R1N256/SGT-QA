/**
 * @file Montos en centavos enteros: las sumas con decimales (0.1 + 0.2) no acumulan errores
 * y el total coincide con el que calcula el backend con los precios de la base de datos.
 * Lo usan SGT de escritorio y la tienda en línea.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

/**
 * Convierte una cantidad de dinero a centavos enteros.
 *
 * @param {number|string} monto Cantidad en pesos
 * @returns {number} La cantidad en centavos (0 si no es un número)
 */
export function aCentavos(monto) {
  const n = Number(monto)
  return Number.isFinite(n) ? Math.round(n * 100) : 0
}

/**
 * Total del carrito de SGT: suma de precio × cantidad de cada producto.
 *
 * @param {Array<{Precio: number|string, cantidad: number}>} cart Productos del carrito
 * @returns {number} El total en pesos
 */
export function totalCarrito(cart) {
  const centavos = cart.reduce(
    (suma, item) => suma + aCentavos(item.Precio) * (Number(item.cantidad) || 0),
    0
  )
  return centavos / 100
}

/**
 * Cambio a entregar al cobrar en efectivo.
 *
 * @param {number|string} pago  Lo que entrega el cliente
 * @param {number|string} total Total de la venta
 * @returns {number} El cambio; negativo si el pago no alcanza
 */
export function calcularCambio(pago, total) {
  return (aCentavos(pago) - aCentavos(total)) / 100
}

/**
 * Desglose de un precio que ya incluye IVA (D2): subtotal = total ÷ (1 + tasa), redondeado a centavos,
 * y el IVA es la diferencia, para que subtotal + IVA siempre dé el total exacto (igual que Carrito::desglose en PHP).
 *
 * @param {number|string} total Total con IVA
 * @param {number|string} tasa  Tasa de IVA en porcentaje (16)
 * @returns {{subtotal: number, iva: number}} El subtotal y el IVA en pesos
 */
export function desglosarIVA(total, tasa) {
  const centavos = aCentavos(total)
  const factor = 1 + (Number(tasa) || 0) / 100
  const subtotal = Math.round(centavos / factor)
  return { subtotal: subtotal / 100, iva: (centavos - subtotal) / 100 }
}
