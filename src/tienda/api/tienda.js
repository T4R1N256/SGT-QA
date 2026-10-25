// Cliente HTTP de la tienda: todas las rutas son /api/tienda/<ruta>, en el mismo origen que la página
// Regresa siempre un objeto: la respuesta del API ({ ok: true, ... }) o { ok: false, status, mensaje }

const BASE = '/api/tienda'

export async function peticion(ruta, { metodo = 'GET', datos } = {}) {
  try {
    const res = await fetch(`${BASE}/${ruta}`, {
      method: metodo,
      credentials: 'same-origin',
      headers: datos === undefined ? undefined : { 'Content-Type': 'application/json' },
      body: datos === undefined ? undefined : JSON.stringify(datos),
    })
    const cuerpo = await res.json().catch(() => null)

    if (!cuerpo) {
      return { ok: false, status: res.status, mensaje: 'La tienda respondió algo inesperado. Intenta de nuevo.' }
    }
    if (!res.ok) {
      return { ...cuerpo, ok: false, status: res.status, mensaje: cuerpo.mensaje ?? `Error ${res.status}` }
    }
    return cuerpo
  } catch {
    return { ok: false, status: 0, mensaje: 'No se pudo conectar con la tienda. Revisa tu conexión e intenta de nuevo.' }
  }
}

export const obtenerConfiguracion = () => peticion('config')
