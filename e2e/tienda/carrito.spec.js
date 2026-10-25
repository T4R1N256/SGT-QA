// Carrito (fase T2): N5–N8 y P1–P6, más P11 y la sincronización entre pestañas
// Los cambios de existencia, precio y baja los hace un admin en SGT, con productos creados para la prueba
import {
  agregarAlCarrito,
  crearProducto,
  darDeBaja,
  expect,
  idDe,
  modificarProducto,
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

const resumen = (page) => page.getByRole('complementary', { name: 'Resumen de la compra' })
const cantidad = (page, nombre) => page.getByLabel(`Cantidad de ${nombre}`)
const fila = (page, nombre) => page.getByRole('row').filter({ hasText: nombre })

test('N5–N8 y P1–P3: elegir cantidades, administrar el carrito y ver el desglose', async ({
  page,
}) => {
  const a = await productoDePrueba({ precio: 20, cantidad: 10 })
  const azucar = await idDe(page, 'Azúcar estándar 1 kg')

  // N5
  await agregarAlCarrito(page, a.id, 3)
  await expect(page.getByRole('status')).toHaveText(/Agregaste 3 piezas al carrito\./)
  await expect(page.getByRole('link', { name: 'Carrito: 3 piezas' })).toBeVisible()

  // P1: más que la existencia, contando lo que ya tiene
  const campo = page.getByRole('textbox', { name: /^Cantidad de/ })
  await campo.fill('8')
  await page.getByRole('button', { name: 'Agregar al carrito' }).click()
  await expect(page.getByRole('alert')).toHaveText(
    'Solo hay 10 disponibles y ya tienes 3 en tu carrito'
  )

  // P2
  for (const [valor, mensaje] of [
    ['0', 'La cantidad debe ser al menos 1'],
    ['-1', 'La cantidad no puede ser negativa'],
    ['2.5', 'La cantidad debe ser un número entero'],
    ['abc', 'La cantidad debe ser un número'],
  ]) {
    await campo.fill(valor)
    await page.getByRole('button', { name: 'Agregar al carrito' }).click()
    await expect(page.getByRole('alert')).toHaveText(mensaje)
  }
  await expect(page.getByRole('link', { name: 'Carrito: 3 piezas' })).toBeVisible()

  // P3
  await page.goto(`/producto/${await idDe(page, 'Chocolate de mesa 6 tablillas')}`)
  await expect(page.getByRole('button', { name: 'Agotado' })).toBeDisabled()
  await expect(page.getByRole('textbox', { name: /^Cantidad de/ })).toHaveCount(0)

  // N8: importes, subtotal, IVA, envío y total
  await agregarAlCarrito(page, azucar, 2)
  await page.getByRole('link', { name: 'Ver carrito' }).click()
  await expect(fila(page, a.nombre)).toContainText('$60.00')
  await expect(fila(page, 'Azúcar estándar 1 kg')).toContainText('$68.00')
  await sinCargas(page)
  // $128.00 = $110.34 + $17.66 de IVA
  await expect(resumen(page)).toContainText('5 piezas')
  await expect(resumen(page)).toContainText('$110.34')
  await expect(resumen(page)).toContainText('IVA (16 %)')
  await expect(resumen(page)).toContainText('$17.66')
  await expect(resumen(page).getByRole('definition').filter({ hasText: 'Gratis' })).toBeVisible()
  await expect(resumen(page)).toContainText('$128.00')

  // N6: cambiar la cantidad y quitar un producto
  await cantidad(page, a.nombre).fill('5')
  await expect(fila(page, a.nombre)).toContainText('$100.00')
  await expect(resumen(page)).toContainText('$168.00')
  await page.getByRole('button', { name: 'Quitar Azúcar estándar 1 kg del carrito' }).click()
  await expect(fila(page, 'Azúcar estándar 1 kg')).toHaveCount(0)
  await expect(resumen(page)).toContainText('$100.00')

  // P1 y P2 en el carrito: no cambia y al salir del campo se conserva la cantidad anterior
  await cantidad(page, a.nombre).fill('11')
  await expect(page.getByRole('alert')).toHaveText('Solo hay 10 disponibles')
  await page.getByRole('heading', { name: 'Tu carrito' }).click()
  await expect(cantidad(page, a.nombre)).toHaveValue('5')
  await expect(page.getByRole('alert')).toContainText('Se conservó la cantidad anterior: 5.')
  await cantidad(page, a.nombre).fill('abc')
  await expect(page.getByRole('alert')).toHaveText('La cantidad debe ser un número')
  await expect(resumen(page)).toContainText('$100.00')

  // N7: recargar conserva el carrito
  await page.reload()
  await expect(cantidad(page, a.nombre)).toHaveValue('5')
  await expect(resumen(page)).toContainText('$100.00')
})

test('P4 y P5: existencias, precios y bajas que cambian con el producto en el carrito', async ({
  page,
}) => {
  const b = await productoDePrueba({ precio: 20, cantidad: 10 })
  const c = await productoDePrueba({ precio: 15, cantidad: 10 })
  await agregarAlCarrito(page, b.id, 5)

  // P4: la existencia baja
  await modificarProducto(admin, b.id, { Cantidad: 4 })
  await page.goto('/carrito')
  await expect(page.getByRole('alert')).toContainText(
    `Solo hay 4 disponibles de «${b.nombre}»; ajustamos tu cantidad a 4.`
  )
  await expect(cantidad(page, b.nombre)).toHaveValue('4')
  await expect(resumen(page)).toContainText('$80.00')

  // P5: el precio cambia; el total usa el vigente y el aviso sale una sola vez
  await modificarProducto(admin, b.id, { Precio: 25 })
  await page.reload()
  await expect(page.getByRole('alert')).toContainText(
    `El precio de «${b.nombre}» cambió de $20.00 a $25.00.`
  )
  await expect(resumen(page)).toContainText('$100.00')
  await page.reload()
  await expect(page.getByRole('table')).toBeVisible()
  await sinCargas(page)
  await expect(page.getByRole('alert')).toHaveCount(0)

  // P5: se da de baja; se descubre al continuar (el carrito se revisa otra vez)
  await agregarAlCarrito(page, c.id, 1)
  await page.goto('/carrito')
  await sinCargas(page)
  await darDeBaja(admin, c.id)
  await page.getByRole('button', { name: 'Continuar con la compra' }).click()
  await expect(page.getByRole('alert')).toContainText(
    `«${c.nombre}» ya no está a la venta y se quitó de tu carrito.`
  )
  await expect(page).toHaveURL(/\/carrito$/)

  // Sin avisos continúa (sin sesión, primero pide ingresar)
  await page.getByRole('button', { name: 'Continuar con la compra' }).click()
  await expect(page).toHaveURL(/\/ingresar\?siguiente=%2Fcompra%2Fentrega$/)
})

test('P6, P11 y pestañas: carrito vacío, precio alterado en el navegador y otra pestaña', async ({
  page,
  context,
}) => {
  const d = await productoDePrueba({ precio: 35, cantidad: 10 })

  // Agregar en otra pestaña actualiza esta
  await page.goto('/')
  const otra = await context.newPage()
  await agregarAlCarrito(otra, d.id, 2)
  await expect(page.getByRole('link', { name: 'Carrito: 2 piezas' })).toBeVisible()
  await otra.close()

  // P11: un precio alterado en localStorage no cambia el total
  await page.evaluate(
    (id) =>
      localStorage.setItem('tienda-carrito', JSON.stringify([{ id, cantidad: 1, precio: 0.01 }])),
    d.id
  )
  await page.goto('/carrito')
  await expect(page.getByRole('alert')).toContainText('cambió de $0.01 a $35.00')
  await expect(resumen(page)).toContainText('$35.00')

  // P6: vaciar y el carrito vacío no deja continuar
  await page.getByRole('button', { name: 'Vaciar carrito' }).click()
  await page.getByRole('button', { name: 'Sí, vaciar' }).click()
  await expect(page.getByText('Tu carrito está vacío')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Continuar con la compra' })).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Explorar la tienda' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Carrito', exact: true })).toBeVisible()

  // Un carrito guardado inválido se descarta
  await page.evaluate(() => localStorage.setItem('tienda-carrito', '{basura'))
  await page.reload()
  await expect(page.getByText('Tu carrito está vacío')).toBeVisible()
})
