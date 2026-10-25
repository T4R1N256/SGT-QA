// Carrito del cliente: vive en el navegador (localStorage) como una lista de { id, cantidad, precio }.
// El precio es el último que vio el cliente y solo sirve para que el servidor avise si cambió (P5);
// los importes y el total siempre los calcula el servidor al revisar el carrito

export const CLAVE_GUARDADO = 'tienda-carrito'
export const MAX_LINEAS = 100

const iguales = (a, b) => JSON.stringify(a) === JSON.stringify(b)

export function reducer(lineas, accion) {
  switch (accion.tipo) {
    case 'agregar': {
      const { id, precio } = accion.producto
      if (lineas.some((l) => l.id === id)) {
        return lineas.map((l) =>
          l.id === id ? { ...l, cantidad: l.cantidad + accion.cantidad, precio } : l
        )
      }
      if (lineas.length >= MAX_LINEAS) return lineas
      return [...lineas, { id, cantidad: accion.cantidad, precio }]
    }
    case 'cambiar':
      return lineas.map((l) => (l.id === accion.id ? { ...l, cantidad: accion.cantidad } : l))
    case 'quitar':
      return lineas.filter((l) => l.id !== accion.id)
    case 'vaciar':
      return []
    // Lo que respondió el servidor: quita lo retirado o agotado, ajusta cantidades y guarda el precio vigente.
    // Si no cambió nada regresa la misma lista, para no volver a revisar
    case 'sincronizar': {
      const nuevas = accion.lineas.map(({ id, cantidad, precio }) => ({ id, cantidad, precio }))
      return iguales(nuevas, lineas) ? lineas : nuevas
    }
    // El carrito cambió en otra pestaña
    case 'reemplazar':
      return iguales(accion.lineas, lineas) ? lineas : accion.lineas
    default:
      return lineas
  }
}

// Carrito guardado en localStorage. Lo que no tenga forma de carrito se descarta: cualquiera puede editarlo
export function leerGuardado(texto) {
  let datos
  try {
    datos = JSON.parse(texto ?? '[]')
  } catch {
    return []
  }
  if (!Array.isArray(datos)) return []

  const vistos = new Set()
  const lineas = []
  for (const linea of datos) {
    const { id, cantidad, precio } = linea ?? {}
    if (
      !Number.isInteger(id) ||
      id < 1 ||
      !Number.isInteger(cantidad) ||
      cantidad < 1 ||
      vistos.has(id)
    )
      continue
    vistos.add(id)
    lineas.push({ id, cantidad, precio: Number.isFinite(precio) ? precio : null })
  }
  return lineas.slice(0, MAX_LINEAS)
}

export const totalPiezas = (lineas) => lineas.reduce((suma, l) => suma + l.cantidad, 0)

// Cantidad escrita por el cliente (P1, P2). enCarrito: lo que ya tiene de ese producto al agregar más
// Regresa { cantidad } si es válida o { mensaje } si no
export function validarCantidad(texto, { existencia, enCarrito = 0 }) {
  const limpio = String(texto ?? '').trim()

  if (limpio === '') return { mensaje: 'Escribe la cantidad' }
  if (/^-/.test(limpio)) return { mensaje: 'La cantidad no puede ser negativa' }
  if (/^\d*[.,]\d+$/.test(limpio)) return { mensaje: 'La cantidad debe ser un número entero' }
  if (!/^\d+$/.test(limpio)) return { mensaje: 'La cantidad debe ser un número' }

  const cantidad = Number(limpio)
  if (cantidad < 1) return { mensaje: 'La cantidad debe ser al menos 1' }

  if (cantidad > existencia - enCarrito) {
    if (enCarrito === 0) return { mensaje: `Solo hay ${existencia} disponibles` }
    if (enCarrito >= existencia)
      return { mensaje: `Ya tienes en tu carrito las ${existencia} disponibles` }
    return { mensaje: `Solo hay ${existencia} disponibles y ya tienes ${enCarrito} en tu carrito` }
  }
  return { cantidad }
}
