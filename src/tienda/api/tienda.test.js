import { afterEach, describe, expect, it, vi } from 'vitest'
import { obtenerProducto, obtenerProductos, peticion } from './tienda'

function respuesta(status, cuerpo) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () =>
      cuerpo === undefined ? Promise.reject(new Error('sin JSON')) : Promise.resolve(cuerpo),
  })
}

describe('peticion', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('llama a /api/tienda/<ruta> en el mismo origen y regresa la respuesta', async () => {
    const fetch = vi.fn(() => respuesta(200, { ok: true, tienda: { nombre: 'X' } }))
    vi.stubGlobal('fetch', fetch)

    const res = await peticion('config')

    expect(fetch).toHaveBeenCalledWith(
      '/api/tienda/config',
      expect.objectContaining({ method: 'GET', credentials: 'same-origin' })
    )
    expect(res).toEqual({ ok: true, tienda: { nombre: 'X' } })
  })

  it('envía los datos como JSON', async () => {
    const fetch = vi.fn(() => respuesta(201, { ok: true }))
    vi.stubGlobal('fetch', fetch)

    await peticion('registro', { metodo: 'POST', datos: { correo: 'a@b.mx' } })

    const [, opciones] = fetch.mock.calls[0]
    expect(opciones.headers).toEqual({ 'Content-Type': 'application/json' })
    expect(opciones.body).toBe('{"correo":"a@b.mx"}')
  })

  it('un error del API conserva el mensaje, el código y los detalles', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        respuesta(400, { ok: false, mensaje: 'Datos inválidos', errores: { correo: 'x' } })
      )
    )

    const res = await peticion('registro', { metodo: 'POST', datos: {} })

    expect(res).toEqual({
      ok: false,
      status: 400,
      mensaje: 'Datos inválidos',
      errores: { correo: 'x' },
    })
  })

  it('sin conexión regresa un mensaje claro en lugar de fallar', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => Promise.reject(new TypeError('Failed to fetch')))
    )

    const res = await peticion('config')

    expect(res.ok).toBe(false)
    expect(res.status).toBe(0)
    expect(res.mensaje).toMatch(/No se pudo conectar/)
  })

  it('una respuesta que no es JSON no rompe la página', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => respuesta(200))
    )

    const res = await peticion('config')

    expect(res).toEqual({ ok: false, status: 200, mensaje: expect.stringMatching(/inesperado/) })
  })

  it('si el proxy no alcanza al backend (502) lo explica como falta de conexión', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => respuesta(502))
    )

    const res = await peticion('config')

    expect(res).toEqual({
      ok: false,
      status: 502,
      mensaje: expect.stringMatching(/No se pudo conectar/),
    })
  })
})

describe('catálogo', () => {
  afterEach(() => vi.unstubAllGlobals())

  function rutaPedida(llamada) {
    const fetch = vi.fn(() => respuesta(200, { ok: true }))
    vi.stubGlobal('fetch', fetch)
    llamada()
    return fetch.mock.calls[0][0]
  }

  it('sin filtros pide la primera página de todos los productos', () => {
    expect(rutaPedida(() => obtenerProductos())).toBe('/api/tienda/productos')
    expect(rutaPedida(() => obtenerProductos({ q: '', orden: 'nombre', pagina: 1 }))).toBe(
      '/api/tienda/productos'
    )
  })

  it('manda solo los filtros que cambian', () => {
    const ruta = rutaPedida(() =>
      obtenerProductos({
        q: 'café & té',
        categoria: 3,
        disponibles: true,
        orden: 'precio_asc',
        pagina: 2,
      })
    )
    expect(ruta).toBe(
      '/api/tienda/productos?q=caf%C3%A9+%26+t%C3%A9&categoria=3&disponibles=1&orden=precio_asc&pagina=2'
    )
  })

  it('codifica el id del producto', () => {
    expect(rutaPedida(() => obtenerProducto('12&x=1'))).toBe('/api/tienda/producto?id=12%26x%3D1')
  })
})
