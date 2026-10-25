// Filtros de la lista de productos. Viven en la dirección (?q=&categoria=&disponibles=1&orden=&pagina=):
// Atrás y Adelante del navegador los respetan y una búsqueda se puede compartir

export const ORDENES = [
  { valor: 'nombre', etiqueta: 'Nombre (A-Z)' },
  { valor: 'precio_asc', etiqueta: 'Precio: menor a mayor' },
  { valor: 'precio_desc', etiqueta: 'Precio: mayor a menor' },
]

const ORDEN_POR_DEFECTO = 'nombre'

// Entero positivo escrito en la dirección ("12"), o null si no lo es
export function enteroPositivo(texto) {
  return /^[1-9]\d{0,8}$/.test(texto ?? '') ? Number(texto) : null
}

// Lo que no sea válido se ignora: cualquiera puede escribir la dirección
export function leerFiltros(params) {
  const orden = params.get('orden')
  return {
    q: (params.get('q') ?? '').trim().slice(0, 100),
    categoria: enteroPositivo(params.get('categoria')),
    disponibles: params.get('disponibles') === '1',
    orden: ORDENES.some((o) => o.valor === orden) ? orden : ORDEN_POR_DEFECTO,
    pagina: enteroPositivo(params.get('pagina')) ?? 1,
  }
}

// Parámetros con los filtros cambiados. Los valores por defecto no se escriben en la dirección,
// y cambiar cualquier filtro que no sea la página regresa a la página 1
export function cambiarFiltros(params, cambios) {
  const nuevos = new URLSearchParams(params)
  if (!('pagina' in cambios)) nuevos.delete('pagina')

  for (const [clave, valor] of Object.entries(cambios)) {
    const porDefecto =
      valor === null ||
      valor === '' ||
      valor === false ||
      (clave === 'orden' && valor === ORDEN_POR_DEFECTO) ||
      (clave === 'pagina' && valor === 1)
    if (porDefecto) nuevos.delete(clave)
    else nuevos.set(clave, valor === true ? '1' : String(valor))
  }
  return nuevos
}

// Números de página que se muestran: la primera, la última y las vecinas de la actual,
// con '…' donde se saltan páginas. Ej. (7, 15) → [1, '…', 6, 7, 8, '…', 15]
export function paginasVisibles(pagina, paginas) {
  const numeros = [...new Set([1, pagina - 1, pagina, pagina + 1, paginas])]
    .filter((n) => n >= 1 && n <= paginas)
    .sort((a, b) => a - b)

  return numeros.flatMap((n, i) => {
    const anterior = numeros[i - 1]
    if (anterior === undefined || n === anterior + 1) return [n]
    return n === anterior + 2 ? [anterior + 1, n] : ['…', n]
  })
}
