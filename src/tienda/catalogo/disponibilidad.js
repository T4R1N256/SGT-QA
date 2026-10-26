/**
 * @file Cómo se muestra la disponibilidad que manda el API (disponible, pocas_piezas, agotado).
 * Siempre con texto además del color.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

export const DISPONIBILIDAD = {
  disponible: {
    etiqueta: 'Disponible',
    clase: 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
  },
  pocas_piezas: { etiqueta: 'Pocas piezas', clase: 'bg-amber-50 text-amber-800 ring-amber-600/30' },
  agotado: { etiqueta: 'Agotado', clase: 'bg-gray-100 text-gray-600 ring-gray-500/20' },
}

const PLURALES = { pieza: 'piezas', litro: 'litros' }

/**
 * Cantidad con su unidad: 1 pieza, 3 piezas, 2 kg, 5 litros.
 *
 * @param {number} cantidad Cantidad
 * @param {string} unidad Unidad de medida en singular
 * @returns {string} La cantidad con la unidad
 */
export function cantidadConUnidad(cantidad, unidad) {
  if (cantidad === 1 || !PLURALES[unidad]) return `${cantidad} ${unidad}`
  return `${cantidad} ${PLURALES[unidad]}`
}

/**
 * Texto de la existencia en el detalle del producto: "Quedan solo 3 piezas", "12 kg disponibles".
 *
 * @param {{disponibilidad: string, existencia: number, unidad: string}} producto Producto del API
 * @returns {string} El texto para mostrar
 */
export function textoExistencia({ disponibilidad, existencia, unidad }) {
  if (disponibilidad === 'agotado') return 'Por ahora no hay existencias'
  const quedan = cantidadConUnidad(existencia, unidad)
  return disponibilidad === 'pocas_piezas'
    ? `${existencia === 1 ? 'Queda' : 'Quedan'} solo ${quedan}`
    : `${quedan} disponibles`
}
