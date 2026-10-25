// Cliente HTTP de la tienda: todas las rutas son /api/tienda/<ruta>, en el mismo origen que la página
// Regresa siempre un objeto: la respuesta del API ({ ok: true, ... }) o { ok: false, status, mensaje }

const BASE = '/api/tienda'
const SIN_CONEXION = 'No se pudo conectar con la tienda. Revisa tu conexión e intenta de nuevo.'
const RESPUESTA_INESPERADA = 'La tienda respondió algo inesperado. Intenta de nuevo.'

export async function peticion(ruta, { metodo = 'GET', datos } = {}) {
  try {
    const res = await fetch(`${BASE}/${ruta}`, {
      method: metodo,
      credentials: 'same-origin',
      headers: datos === undefined ? undefined : { 'Content-Type': 'application/json' },
      body: datos === undefined ? undefined : JSON.stringify(datos),
    })
    const cuerpo = await res.json().catch(() => null)

    // Sin JSON: el proxy (nginx o Vite) responde 502/503/504 cuando el backend no está disponible
    if (!cuerpo) {
      return {
        ok: false,
        status: res.status,
        mensaje: res.status >= 500 ? SIN_CONEXION : RESPUESTA_INESPERADA,
      }
    }
    if (!res.ok) {
      return {
        ...cuerpo,
        ok: false,
        status: res.status,
        mensaje: cuerpo.mensaje ?? `Error ${res.status}`,
      }
    }
    return cuerpo
  } catch {
    return { ok: false, status: 0, mensaje: SIN_CONEXION }
  }
}

export const obtenerConfiguracion = () => peticion('config')

// Catálogo (fase T1)
export const obtenerCategorias = () => peticion('categorias')
export const obtenerDestacados = () => peticion('destacados')
export const obtenerProducto = (id) => peticion(`producto?id=${encodeURIComponent(id)}`)

// Lista de productos. Los filtros vacíos o con su valor por defecto no se mandan
export function obtenerProductos({
  q = '',
  categoria = null,
  disponibles = false,
  orden = 'nombre',
  pagina = 1,
} = {}) {
  const params = new URLSearchParams()
  if (q) params.set('q', q)
  if (categoria) params.set('categoria', categoria)
  if (disponibles) params.set('disponibles', '1')
  if (orden !== 'nombre') params.set('orden', orden)
  if (pagina > 1) params.set('pagina', pagina)

  const consulta = params.toString()
  return peticion(consulta ? `productos?${consulta}` : 'productos')
}

// Carrito (fase T2): el servidor lo revisa con los precios y existencias vigentes
export const revisarCarrito = (lineas) =>
  peticion('carrito/revisar', {
    metodo: 'POST',
    datos: { productos: lineas.map(({ id, cantidad, precio }) => ({ id, cantidad, precio })) },
  })
