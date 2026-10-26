// Historial de ventas y de cortes de caja (RF09, RF10, RF12)
import {
  ADMIN,
  EMPLEADO,
  abrirCaja,
  agregar,
  archivoPDF,
  cerrarCaja,
  cerrarSesion,
  cobrar,
  expect,
  iniciarSesion,
  ir,
  test,
} from './ayudas'
import fs from 'node:fs'

// El empleado abre caja con $100, vende Arroz en efectivo (paga $50), Aceite con tarjeta,
// abandona una venta de Refresco (Limpiar carrito) y hace el corte
async function turnoDelEmpleado(page) {
  await iniciarSesion(page, EMPLEADO)
  await abrirCaja(page, 100)
  await ir(page, '#/sales')
  await agregar(page, 'arroz', 'Arroz 1 kg')
  await cobrar(page, { pago: 50 })
  await agregar(page, 'aceite', 'Aceite vegetal')
  await cobrar(page, { tarjeta: true })
  await agregar(page, 'refresco', 'Refresco de cola')
  await expect(page.getByRole('button', { name: /Limpiar carrito/ })).toBeEnabled()
  await page.getByRole('button', { name: /Limpiar carrito/ }).click()
  await cerrarCaja(page, 132.5)
  await cerrarSesion(page)
}

test('historial de ventas: resumen, filtros, detalle y reimpresión del ticket', async ({
  page,
  carpeta,
}) => {
  await turnoDelEmpleado(page)
  await iniciarSesion(page, ADMIN)
  await ir(page, '#/historial')

  await page.getByText('2 ventas · Total cobrado').waitFor()
  await expect(page.getByText('$78.40').first(), 'Arroz $32.50 + Aceite $45.90').toBeVisible()

  const formaPago = page.locator('select').nth(0)
  const estado = page.locator('select').nth(1)
  await formaPago.selectOption('tarjeta')
  await page.getByText('1 venta · Total cobrado').waitFor()
  await expect(page.locator('li', { hasText: 'Tarjeta' })).toHaveCount(1)

  await formaPago.selectOption('')
  await estado.selectOption('cancelada')
  await expect(
    page.locator('li', { hasText: 'Cancelada' }),
    'la venta abandonada quedó cancelada'
  ).toHaveCount(1)

  await estado.selectOption('finalizada')
  await page.locator('li button', { hasText: '000001' }).click()
  await page.getByText(/1 × Arroz 1 kg a \$32\.50/).waitFor()
  await expect(page.getByText(/Pagó \$50\.00 · Cambio \$17\.50/)).toBeVisible()

  const ticket = archivoPDF(carpeta, 'Tickets', 'ticket-000001')
  fs.rmSync(ticket)
  await page.getByRole('button', { name: /Reimprimir ticket/ }).click()
  await expect
    .poll(() => archivoPDF(carpeta, 'Tickets', 'ticket-000001'), {
      message: 'reimprimir genera el ticket del folio 000001',
    })
    .toBeTruthy()
})

test('historial de cortes de caja: el corte cuadrado con su efectivo esperado y las ventas canceladas', async ({
  page,
}) => {
  await turnoDelEmpleado(page)
  await iniciarSesion(page, ADMIN)
  await ir(page, '#/historial')

  await page.getByRole('tab', { name: 'Cortes de caja' }).click()
  await page.locator('li button', { hasText: 'Cuadrada' }).first().click()
  await page.getByText('2 ventas cobradas · 1 cancelada').waitFor()
  // Solo cuenta el efectivo: $100 de fondo + $32.50 de Arroz
  await expect(page.getByText('$132.50').first()).toBeVisible()
})
