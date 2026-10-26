/**
 * @file Cliente HTTP de la tienda en línea: todas las rutas son /api/tienda/<ruta>, en el mismo origen que la página.
 * Regresa siempre un objeto: la respuesta del API ({ ok: true, ... }) o { ok: false, status, mensaje }. Nunca lanza.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

const BASE = '/api/tienda'
const SIN_CONEXION = 'No se pudo conectar con la tienda. Revisa tu conexión e intenta de nuevo.'
const RESPUESTA_INESPERADA = 'La tienda respondió algo inesperado. Intenta de nuevo.'

/**
 * Hace una petición al API de la tienda con la cookie de sesión del cliente.
 * Si la respuesta no es JSON (el proxy, nginx o Vite, responde 502/503/504 cuando el backend no está disponible)
 * regresa un mensaje de conexión.
 *
 * @param {string} ruta Ruta sin /api/tienda/, con su query string
 * @param {Object} [opciones]
 * @param {string} [opciones.metodo='GET'] Método HTTP
 * @param {Object} [opciones.datos] Cuerpo de la petición (se manda como JSON)
 * @returns {Promise<Object>} La respuesta, o {ok: false, status, mensaje} con los datos que acompañan al error
 *                            (por ejemplo, errores por campo o los avisos del carrito)
 */
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

/**
 * Lista de productos del catálogo. Los filtros vacíos o con su valor por defecto no se mandan.
 *
 * @param {Object} [filtros]
 * @param {string} [filtros.q=''] Texto a buscar
 * @param {number|null} [filtros.categoria=null] ID de la categoría
 * @param {boolean} [filtros.disponibles=false] Ocultar los agotados
 * @param {'nombre'|'precio_asc'|'precio_desc'} [filtros.orden='nombre'] Orden
 * @param {number} [filtros.pagina=1] Página
 * @returns {Promise<Object>} {ok, productos, total, pagina, paginas, por_pagina} (y la categoría si se filtra), o el error
 */
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

// Cuentas de cliente (fase T3)
export const obtenerCuenta = () => peticion('cuenta')
export const registrarCliente = (datos) => peticion('registro', { metodo: 'POST', datos })
export const ingresarCliente = (datos) => peticion('ingresar', { metodo: 'POST', datos })
export const salirCliente = () => peticion('salir', { metodo: 'POST' })

// Compra y pedidos (fase T4)
export const obtenerEntrega = () => peticion('entrega')
export const guardarEntrega = (datos) => peticion('entrega', { metodo: 'POST', datos })
export const obtenerPedidos = () => peticion('pedidos')
export const obtenerPedido = (folio) => peticion(`pedido?folio=${encodeURIComponent(folio)}`)
export const pagarPedido = (folio, metodo) =>
  peticion('pedido/pagar', { metodo: 'POST', datos: { folio, metodo } })
export const confirmarPedido = (lineas, clave) =>
  peticion('pedidos', {
    metodo: 'POST',
    datos: {
      productos: lineas.map(({ id, cantidad, precio }) => ({ id, cantidad, precio })),
      clave,
    },
  })
