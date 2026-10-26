/**
 * @file Búsqueda de productos para la venta (RF03) y sugerencias para surtir.
 * La búsqueda es por nombre o por categoría, sin distinguir mayúsculas ni acentos ("azucar" encuentra "Azúcar").
 * Los agotados no se pueden vender.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

/**
 * Texto sin acentos, en minúsculas y sin espacios al inicio ni al final, para comparar.
 *
 * @param {*} texto Texto (null y undefined cuentan como '')
 * @returns {string} El texto normalizado
 */
export function normalizar(texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

/**
 * Productos cuyo nombre o categoría contienen la búsqueda.
 *
 * @param {Array<Object>} productos Productos de getProducts()
 * @param {string} consulta Texto que escribió el usuario
 * @param {number} [limite=8] Máximo de resultados
 * @param {Object} [opciones]
 * @param {boolean} [opciones.incluirAgotados=false] En Surtir se incluyen: son justo los que hay que reabastecer
 * @returns {Array<Object>} Los productos que coinciden ([] si la búsqueda está vacía)
 */
export function filtrarProductos(productos, consulta, limite = 8, { incluirAgotados = false } = {}) {
  const q = normalizar(consulta)
  if (!q) return []

  return (productos ?? [])
    .filter(
      (p) =>
        (incluirAgotados || p.Estado !== 'Agotado') &&
        (normalizar(p.Nombre_Producto).includes(q) || normalizar(p.Nombre_Categoria).includes(q))
    )
    .slice(0, limite)
}

/**
 * Productos que conviene surtir: los que están en stock bajo o agotados.
 *
 * @param {Array<Object>} productos Productos con su Estado
 * @returns {Array<Object>} Los productos por surtir
 */
export function necesitanSurtido(productos) {
  return (productos ?? []).filter((p) => p.Estado === 'Stock bajo' || p.Estado === 'Agotado')
}

/**
 * Cantidad sugerida para surtir: lo necesario para llegar al doble de la cantidad mínima (al menos 1).
 * Es la misma regla que Reporte::cantidadSugerida en el backend.
 *
 * @param {{Cantidad: number, Cantidad_Minima: number}} producto Producto
 * @returns {number} Piezas por surtir
 */
export function cantidadSugerida(producto) {
  const existencia = Math.max(Number(producto.Cantidad) || 0, 0)
  const minima = Math.max(Number(producto.Cantidad_Minima) || 0, 0)
  return Math.max(minima * 2 - existencia, 1)
}
