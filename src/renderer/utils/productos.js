// Búsqueda de productos para la venta (RF03): por nombre o por categoría,
// sin distinguir mayúsculas ni acentos ("azucar" encuentra "Azúcar"). Los agotados no se pueden vender.

export function normalizar(texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .trim()
}

export function filtrarProductos(productos, consulta, limite = 8) {
  const q = normalizar(consulta)
  if (!q) return []

  return (productos ?? [])
    .filter(
      (p) =>
        p.Estado !== 'Agotado' &&
        (normalizar(p.Nombre_Producto).includes(q) || normalizar(p.Nombre_Categoria).includes(q))
    )
    .slice(0, limite)
}
