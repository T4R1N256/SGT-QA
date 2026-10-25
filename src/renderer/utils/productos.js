// Búsqueda de productos para la venta (RF03): por nombre o por categoría,
// sin distinguir mayúsculas ni acentos ("azucar" encuentra "Azúcar"). Los agotados no se pueden vender.

export function normalizar(texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

// En Surtir se usa { incluirAgotados: true }: los agotados son justo los que hay que reabastecer
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

// Productos que conviene surtir: los que están en stock bajo o agotados
export function necesitanSurtido(productos) {
  return (productos ?? []).filter((p) => p.Estado === 'Stock bajo' || p.Estado === 'Agotado')
}

// Cantidad sugerida para surtir: lo necesario para llegar al doble de la cantidad mínima (al menos 1)
export function cantidadSugerida(producto) {
  const existencia = Math.max(Number(producto.Cantidad) || 0, 0)
  const minima = Math.max(Number(producto.Cantidad_Minima) || 0, 0)
  return Math.max(minima * 2 - existencia, 1)
}
