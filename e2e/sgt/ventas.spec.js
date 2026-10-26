// Ventas, ticket en PDF, configuración de la tienda y baja de productos (RF02–RF04, RF06, RF12, reglas 2 y 8)
import fs from 'node:fs'
import path from 'node:path'
import {
  ADMIN,
  abrirCaja,
  agregar,
  archivoPDF,
  cerrarCaja,
  cobrar,
  expect,
  iniciarSesion,
  ir,
  producto,
  test,
  textoPDF,
} from './ayudas'

test('la configuración de la tienda sale en el login y en el ticket; venta en efectivo con búsqueda y reimpresión', async ({
  page,
}) => {
  await expect(page.getByText('Abarrotes "La bamba"')).toBeVisible()
  await iniciarSesion(page, ADMIN)

  await ir(page, '#/configuracion')
  await page.locator('input[name="Direccion"]').fill('Av. Juárez 100')
  await page.locator('input[name="RFC"]').fill('lupe800101ab1')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await page.getByText('Configuración guardada').waitFor()
  await expect(page.locator('input[name="RFC"]'), 'el RFC se guarda en mayúsculas').toHaveValue(
    'LUPE800101AB1'
  )

  // Regla 2: sin caja abierta no se vende
  await ir(page, '#/sales')
  await expect(page.getByText(/caja abierta/i).first()).toBeVisible()

  await abrirCaja(page, 0)
  await ir(page, '#/sales')
  // Búsqueda por categoría ("lacteos") y sin acentos ("azucar")
  await agregar(page, 'lacteos', 'Leche entera')
  await agregar(page, 'azucar', 'Azúcar')
  await expect(page.getByText('$62.50').first()).toBeVisible()
  const aviso = await cobrar(page, { pago: 100 })

  const ticket = aviso.match(/Ticket: (\S+\.pdf)/)?.[1] ?? ''
  expect(ticket.endsWith(path.join('SGT', 'Tickets', 'ticket-000001.pdf')), ticket).toBe(true)
  expect(fs.existsSync(ticket), 'el ticket PDF se genera al cobrar').toBe(true)
  const texto = textoPDF(ticket)
  for (const fragmento of [
    'Abarrotes "La bamba"',
    'Av. Juárez 100',
    'RFC LUPE800101AB1',
    'Folio 000001',
    'Cajero: Usuario Administrador',
    'Leche entera',
    'Azúcar',
    '$62.50',
    '$100.00',
    '$37.50',
    'Precios con impuestos incluidos',
  ]) {
    expect.soft(texto, `el ticket contiene "${fragmento}"`).toContain(fragmento)
  }

  // Reimpresión del último ticket
  fs.rmSync(ticket)
  await page.getByRole('button', { name: /Reimprimir último ticket/ }).click()
  await expect
    .poll(() => fs.existsSync(ticket), { message: 'reimprimir vuelve a generar el ticket' })
    .toBe(true)

  await cerrarCaja(page)
})

test('con tarjeta no se captura el pago y el ticket no muestra cambio; un pago insuficiente no se cobra', async ({
  page,
  carpeta,
}) => {
  await iniciarSesion(page, ADMIN)
  await abrirCaja(page, 0)
  await ir(page, '#/sales')

  // RF04: en efectivo, un pago menor al total no se cobra
  await agregar(page, 'arroz', 'Arroz 1 kg')
  await page.getByPlaceholder('0.00').fill('10')
  await expect(page.getByRole('button', { name: /Cobrar/i })).toBeDisabled()

  await page.getByRole('button', { name: /tarjeta/i }).click()
  await expect(page.getByPlaceholder('0.00'), 'con tarjeta el pago no se captura').toBeDisabled()
  await cobrar(page)

  const ticket = archivoPDF(carpeta, 'Tickets', 'ticket-000001')
  expect(ticket, 'el ticket de la venta con tarjeta').toBeTruthy()
  const texto = textoPDF(ticket)
  expect(texto).toContain('Tarjeta')
  expect(texto).not.toContain('Cambio')

  await cerrarCaja(page)
})

test('vaciar el carrito cancela la venta, la existencia baja al cobrar y los agotados no se ofrecen', async ({
  page,
  api,
}) => {
  await iniciarSesion(page, ADMIN)
  await abrirCaja(page, 0)
  await ir(page, '#/sales')
  const antes = (await producto(api, 'Arroz 1 kg')).Cantidad

  await agregar(page, 'arroz', 'Arroz 1 kg')
  await page.getByRole('button', { name: /Limpiar carrito/ }).click()
  await expect(page.getByText('$32.50')).toHaveCount(0)

  await agregar(page, 'arroz', 'Arroz 1 kg')
  await cobrar(page, { pago: 50 })
  expect(
    (await producto(api, 'Arroz 1 kg')).Cantidad,
    'RF05: la venta descuenta la existencia'
  ).toBe(antes - 1)

  // Los productos agotados no se ofrecen (Chicles menta está agotado en los datos demo)
  await page.getByPlaceholder(/Buscar producto/).fill('chicles')
  await expect(page.locator('li', { hasText: 'Chicles menta' })).toHaveCount(0)

  await cerrarCaja(page)
})

test('eliminar un producto desde Inventario lo quita de la lista y de Ventas (RF06)', async ({
  page,
}) => {
  await iniciarSesion(page, ADMIN)
  await ir(page, '#/inventory')
  await page.locator('li', { hasText: 'Cloro 1 L' }).first().click()
  await page.getByRole('button', { name: /Eliminar seleccionado/ }).click()
  await page.getByRole('button', { name: 'Confirmar' }).click()
  await expect(page.locator('li', { hasText: 'Cloro 1 L' })).toHaveCount(0)

  await abrirCaja(page, 0)
  await ir(page, '#/sales')
  await page.getByPlaceholder(/Buscar producto/).fill('cloro')
  await expect(page.locator('li', { hasText: 'Cloro 1 L' })).toHaveCount(0)
  await cerrarCaja(page)
})
