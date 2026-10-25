// Compra (fase T4): N9–N11 y P6, P7, P8/P9 de la entrega, P12, P13, T-S4, T-S5 y T-S6
import {
  agregarAlCarrito,
  crearProducto,
  darDeBaja,
  errorDe,
  expect,
  llenar,
  modificarProducto,
  registrarCliente,
  sgtAdmin,
  sinCargas,
  test,
} from './ayudas'

let admin
const creados = []

test.beforeAll(async () => {
  admin = await sgtAdmin()
})

test.afterAll(async () => {
  for (const id of creados) await darDeBaja(admin, id)
  await admin.dispose()
})

async function productoDePrueba(opciones) {
  const producto = await crearProducto(admin, opciones)
  creados.push(producto.id)
  return producto
}

const ENTREGA = {
  'Nombre de quien recibe': 'Ana Pérez',
  'Teléfono de contacto': '656 123 4567',
  Calle: 'Av. Juárez',
  'Núm. exterior': '123-B',
  Colonia: 'Centro',
  'Código postal': '32000',
  'Ciudad o municipio': 'Ciudad Juárez',
  Entidad: 'Chihuahua',
  Referencias: 'Portón verde',
}

async function irAEntrega(page) {
  await page.goto('/carrito')
  await expect(page.getByRole('table')).toBeVisible()
  await sinCargas(page)
  await page.getByRole('button', { name: 'Continuar con la compra' }).click()
  await expect(page).toHaveURL(/\/compra\/entrega$/)
  await expect(page.getByRole('button', { name: 'Continuar al resumen' })).toBeVisible()
}

async function pedidos(page) {
  return (await (await page.request.get('/api/tienda/pedidos')).json()).pedidos
}

test('P7: los pasos de la compra piden sesión, productos y el paso anterior', async ({ page }) => {
  await page.goto('/compra/resumen')
  await expect(page).toHaveURL(/\/ingresar\?siguiente=%2Fcompra%2Fresumen$/)

  await registrarCliente(page)
  for (const paso of ['entrega', 'resumen']) {
    await page.goto(`/compra/${paso}`)
    await expect(page).toHaveURL(/\/carrito$/)
    await expect(page.getByText('Tu carrito está vacío')).toBeVisible()
  }
})

test('N9–N11: entrega validada, resumen, confirmar y pagar (sin cobrar)', async ({ page }) => {
  const a = await productoDePrueba({ precio: 20, cantidad: 10 })
  const b = await productoDePrueba({ precio: 30, cantidad: 10 })
  await registrarCliente(page, { nombre: 'Ana', apellidos: 'Pérez' })
  await agregarAlCarrito(page, a.id, 2)
  await agregarAlCarrito(page, b.id, 1)
  await irAEntrega(page)

  // T-S4: sin pedidos previos se prellena con los datos del cliente
  await expect(page.getByLabel('Nombre de quien recibe')).toHaveValue('Ana Pérez')
  await expect(page.getByLabel('Teléfono de contacto')).toHaveValue('6561234567')

  // P7: el resumen sin haber guardado la entrega regresa a la entrega
  await page.goto('/compra/resumen')
  await expect(page).toHaveURL(/\/compra\/entrega$/)

  // P8 y P9 en la entrega
  await llenar(page, {
    'Nombre de quien recibe': '  ',
    'Teléfono de contacto': '123',
    'Código postal': '320',
    'Núm. exterior': '<1>',
  })
  await page.getByRole('button', { name: 'Continuar al resumen' }).click()
  const esperados = {
    'Nombre de quien recibe': 'Escribe quién recibe el pedido',
    'Teléfono de contacto': 'El teléfono debe tener 10 dígitos',
    Calle: 'Escribe la calle',
    'Núm. exterior': 'El número exterior solo puede tener letras, números, espacios y - / # .',
    Colonia: 'Escribe la colonia',
    'Código postal': 'El código postal debe tener 5 dígitos',
    'Ciudad o municipio': 'Escribe la ciudad o municipio',
    Entidad: 'Elige la entidad',
  }
  for (const [etiqueta, mensaje] of Object.entries(esperados)) {
    expect(await errorDe(page.getByLabel(etiqueta, { exact: true })), etiqueta).toBe(mensaje)
  }
  await expect(page).toHaveURL(/\/compra\/entrega$/)

  // N9: con datos válidos pasa al resumen
  await llenar(page, ENTREGA)
  await page.getByRole('button', { name: 'Continuar al resumen' }).click()
  await expect(page).toHaveURL(/\/compra\/resumen$/)
  await expect(page.getByRole('table')).toBeVisible()
  await sinCargas(page)
  const main = page.locator('main')
  await expect(main).toContainText(a.nombre)
  await expect(main).toContainText(b.nombre)
  // $70.00 = $60.34 + $9.66 de IVA
  await expect(main).toContainText('$60.34')
  await expect(main).toContainText('$9.66')
  await expect(main).toContainText('$70.00')
  await expect(main).toContainText('Av. Juárez 123-B')
  await expect(main).toContainText('Ciudad Juárez, Chihuahua')

  // N10: corregir datos y regresar al resumen
  await page.getByRole('link', { name: 'Corregir datos' }).click()
  await expect(page.getByLabel('Calle')).toHaveValue('Av. Juárez')
  await llenar(page, { Calle: 'Av. 16 de Septiembre' })
  await page.getByRole('button', { name: 'Continuar al resumen' }).click()
  await expect(page.getByText('Av. 16 de Septiembre 123-B')).toBeVisible()

  // P13: Atrás y Adelante en medio del flujo
  await page.goBack()
  await expect(page.getByLabel('Calle')).toHaveValue('Av. 16 de Septiembre')
  await page.goForward()
  await expect(page.getByText('Av. 16 de Septiembre 123-B')).toBeVisible()

  // T-S5: la existencia cambia antes de confirmar
  await sinCargas(page)
  await modificarProducto(admin, b.id, { Cantidad: 0 })
  await page.getByRole('button', { name: 'Confirmar pedido' }).click()
  await expect(page.getByRole('alert')).toContainText(
    `«${b.nombre}» se agotó y se quitó de tu carrito.`
  )
  await expect(page).toHaveURL(/\/compra\/resumen$/)
  expect(await pedidos(page)).toHaveLength(0)
  await expect(main).toContainText('$40.00')

  // P12: doble clic en Confirmar crea un solo pedido
  await sinCargas(page)
  await page.getByRole('button', { name: 'Confirmar pedido' }).dblclick()
  await expect(page).toHaveURL(/\/compra\/pago\/P\d{6}-[A-Z2-9]{5}$/)
  const folio = decodeURIComponent(page.url().split('/').pop())
  expect(await pedidos(page)).toHaveLength(1)

  // N11: la pantalla de pago, sin campos de tarjeta; "Pagar" avisa que no está disponible
  await expect(page.getByRole('heading', { name: `Pedido ${folio}` })).toBeVisible()
  await expect(page.getByText('Pendiente de pago')).toBeVisible()
  await expect(page.getByRole('radio')).toHaveCount(3)
  await expect(page.locator('main input:not([type="radio"])')).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Carrito', exact: true })).toBeVisible()
  await page.getByRole('button', { name: /^Pagar/ }).click()
  await expect(page.getByRole('alert')).toHaveText('Elige un método de pago')
  await page.getByLabel('Tarjeta de crédito o débito').check()
  await page.getByRole('button', { name: 'Pagar $40.00' }).dblclick()
  await expect(page.getByRole('alert')).toContainText(
    `El pago en línea todavía no está disponible. No se hizo ningún cargo y tu pedido ${folio} sigue pendiente de pago.`
  )
  const [pedido] = await pedidos(page)
  expect(pedido).toMatchObject({ folio, estado: 'pendiente_pago', total: 40 })

  // D4: el pedido pendiente no descuenta existencias
  const { producto } = await (await page.request.get(`/api/tienda/producto?id=${a.id}`)).json()
  expect(producto.existencia).toBe(10)

  // P13: Atrás desde el pago lleva al carrito vacío, sin repetir el pedido
  await page.goBack()
  await expect(page).toHaveURL(/\/carrito$/)
  await expect(page.getByText('Tu carrito está vacío')).toBeVisible()
  expect(await pedidos(page)).toHaveLength(1)

  // T-S6: Mis pedidos y regreso al pago
  await page.goto('/cuenta')
  const fila = page.getByRole('row').filter({ hasText: folio })
  await expect(fila).toContainText('Pendiente de pago')
  await expect(fila).toContainText('$40.00')
  await page.getByRole('link', { name: `Ir al pago del pedido ${folio}` }).click()
  await expect(page).toHaveURL(new RegExp(`/compra/pago/${folio}$`))

  // T-S4: la siguiente compra se prellena con la dirección del último pedido
  await agregarAlCarrito(page, a.id, 1)
  await irAEntrega(page)
  await expect(page.getByLabel('Calle')).toHaveValue('Av. 16 de Septiembre')

  // Otro cliente no ve el pedido
  await page.request.post('/api/tienda/salir')
  await registrarCliente(page, { nombre: 'Luis' })
  await page.goto(`/compra/pago/${folio}`)
  await expect(page.getByRole('heading', { name: 'Pedido no encontrado' })).toBeVisible()
})
