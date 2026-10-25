// Ayudas de las pruebas en el navegador de la tienda
import { test as base, expect, request } from '@playwright/test'

export { expect }

// Cada prueba falla si la página tiene errores de JavaScript o si el navegador se conecta a otro sitio:
// la tienda no usa servicios externos y el pago no contacta a ningún banco ni pasarela (R14)
export const test = base.extend({
  page: async ({ page, baseURL }, use) => {
    const errores = []
    const externas = new Set()
    page.on('pageerror', (e) => errores.push(e.message))
    page.on('request', (r) => {
      const origen = new URL(r.url()).origin
      if (origen !== new URL(baseURL).origin) externas.add(origen)
    })
    await use(page)
    expect(errores, 'errores de JavaScript en la página').toEqual([])
    expect([...externas], 'conexiones fuera de la tienda').toEqual([])
  },
})

// ---- SGT como administrador: cambia existencias, precios y bajas, como lo haría un empleado ----

export const SGT_API = process.env.SGT_API ?? 'http://localhost:8001'

export async function sgtAdmin() {
  const api = await request.newContext({ baseURL: SGT_API })
  const res = await api.post('/api/login', { data: { ID_Usuario: 1, Password: 'passadmin' } })
  expect(res.ok(), 'iniciar sesión en SGT como admin').toBeTruthy()
  return api
}

// Producto nuevo con nombre único en la categoría Mascotas, para no tocar los productos que revisan otras pruebas
export async function crearProducto(api, { precio = 20, cantidad = 10, minima = 2 } = {}) {
  const nombre = `Producto de prueba ${Date.now()}${Math.floor(Math.random() * 1000)}`
  const { categorias } = await (await api.get('/api/getAllCategories')).json()
  const { proveedores } = await (await api.get('/api/getAllSuppliers')).json()

  const res = await api.post('/api/createProduct', {
    data: {
      Nombre_Producto: nombre,
      Descripcion: 'Producto creado por las pruebas en el navegador.',
      Precio: precio,
      Cantidad: cantidad,
      Unidad_Medida: 'pieza',
      Cantidad_Minima: minima,
      ID_Categoria: categorias.find((c) => c.Nombre_Categoria === 'Mascotas').ID_Categoria,
      ID_Proveedor: proveedores.find((p) => (p.Estado ?? 'activo') === 'activo').ID_Proveedor,
    },
  })
  expect(res.status(), `crear ${nombre}`).toBe(201)

  const { productos } = await (await api.get('/api/getAllProducts')).json()
  return { id: Number(productos.find((p) => p.Nombre_Producto === nombre).ID_Producto), nombre }
}

export async function modificarProducto(api, id, cambios) {
  const res = await api.patch('/api/modifyProduct', { data: { ID_Producto: id, ...cambios } })
  expect(res.ok(), `modificar el producto ${id}`).toBeTruthy()
}

export async function darDeBaja(api, id) {
  await api.delete('/api/deleteProduct', { data: { ID_Producto: id } })
}

// ---- Tienda ----

// ID de un producto del catálogo de prueba por su nombre
export async function idDe(page, nombre) {
  const res = await page.request.get(`/api/tienda/productos?q=${encodeURIComponent(nombre)}`)
  const { productos } = await res.json()
  return productos.find((p) => p.nombre === nombre).id
}

export const correoUnico = (nombre = 'cliente') =>
  `${nombre}.${Date.now()}${Math.floor(Math.random() * 1000)}@ejemplo.mx`

// Registra un cliente por el API con el contexto del navegador: la sesión queda iniciada en la página
export async function registrarCliente(page, datos = {}) {
  const res = await page.request.post('/api/tienda/registro', {
    data: {
      nombre: 'Ana',
      apellidos: 'Pérez',
      correo: correoUnico('ana'),
      telefono: '6561234567',
      password: 'secreta123',
      ...datos,
    },
  })
  expect(res.status(), 'registrar cliente').toBe(201)
  return (await res.json()).cliente
}

export async function agregarAlCarrito(page, id, cantidad = 1) {
  await page.goto(`/producto/${id}`)
  await page.getByRole('textbox', { name: /^Cantidad de/ }).fill(String(cantidad))
  await page.getByRole('button', { name: 'Agregar al carrito' }).click()
  await expect(page.getByRole('status')).toContainText('al carrito')
}

// Espera a que no haya nada revisándose en el servidor (aria-busy)
export async function sinCargas(page) {
  await page.waitForFunction(() => !document.querySelector('[aria-busy="true"]'))
}

// Mensaje de error ligado a un campo (aria-describedby)
export async function errorDe(campo) {
  return campo.evaluate(
    (el) => document.getElementById(el.getAttribute('aria-describedby'))?.textContent ?? ''
  )
}

export async function llenar(page, datos) {
  for (const [etiqueta, valor] of Object.entries(datos)) {
    const campo = page.getByLabel(etiqueta, { exact: true })
    if ((await campo.evaluate((el) => el.tagName)) === 'SELECT') await campo.selectOption(valor)
    else await campo.fill(valor)
  }
}
