// Surtir: compras a proveedores que reabastecen el inventario (RF05)
import { ADMIN, expect, iniciarSesion, ir, producto, test } from './ayudas'

test('sugerir stock bajo, registrar el surtido y verlo en el historial', async ({ page, api }) => {
  await iniciarSesion(page, ADMIN)
  await ir(page, '#/surtir')
  await page.getByText('Todavía no hay surtidos registrados').waitFor()

  // Distribuidora La Central surte Harina (8 de mínimo 10) y Pasta (agotada, mínimo 15)
  await page.locator('select').selectOption({ label: 'Distribuidora La Central' })
  await page.getByRole('button', { name: /Sugerir stock bajo/ }).click()
  await expect(
    page.getByLabel('Cantidad de Harina de maíz 1 kg'),
    'sugiere completar al doble del mínimo'
  ).toHaveValue('12')
  await expect(page.getByLabel('Cantidad de Pasta para sopa 200 g')).toHaveValue('30')
  await expect(
    page.locator('li', { hasText: 'Arroz 1 kg' }),
    'no sugiere los que tienen existencia'
  ).toHaveCount(0)

  // Un producto con existencia suficiente se agrega a mano
  await page.getByPlaceholder('Buscar por nombre o categoría...').fill('arroz')
  await page.locator('li', { hasText: 'Arroz 1 kg' }).first().click()
  await page.getByLabel('Cantidad de Arroz 1 kg').fill('10')
  await page.getByLabel('Costo de Harina de maíz 1 kg').fill('18.40')
  await page.getByLabel('Costo de Pasta para sopa 200 g').fill('7.15')
  await page.getByLabel('Costo de Arroz 1 kg').fill('25')
  // 12 × 18.40 + 30 × 7.15 + 10 × 25 = 220.80 + 214.50 + 250.00
  await expect(page.getByText('$685.30')).toBeVisible()

  const arrozAntes = (await producto(api, 'Arroz 1 kg')).Cantidad
  await page.locator('input[maxlength="255"]').fill('Factura A-123')
  await page.getByRole('button', { name: /Registrar surtido/ }).click()
  await page.getByText('Surtido registrado').waitFor()
  await page.getByRole('button', { name: 'Aceptar' }).click()

  const harina = await producto(api, 'Harina de maíz 1 kg')
  const pasta = await producto(api, 'Pasta para sopa 200 g')
  expect(harina.Cantidad, 'Harina sube de 8 a 20').toBe(20)
  expect(harina.Costo, 'se guarda el costo de compra').toBe('18.40')
  expect(pasta.Cantidad, 'Pasta sube de 0 a 30').toBe(30)
  expect(pasta.Estado, 'Pasta deja de estar agotada').toBe('Disponible')
  expect((await producto(api, 'Arroz 1 kg')).Cantidad).toBe(arrozAntes + 10)
  const { surtidos } = await (await api.get('/api/getAllRestocks')).json()
  expect(surtidos.map((s) => s.Monto)).toEqual(['685.30'])

  // Historial y detalle
  await page.locator('button', { hasText: 'Distribuidora La Central' }).first().click()
  await page.getByText('Notas: Factura A-123').waitFor()
  await expect(page.getByText('12 × Harina de maíz 1 kg a $18.40')).toBeVisible()
})

test('un surtido sin costo o con cantidad 0 no se registra y se dice qué revisar', async ({
  page,
  api,
}) => {
  await iniciarSesion(page, ADMIN)
  await ir(page, '#/surtir')
  await page.locator('select').selectOption({ label: 'Distribuidora La Central' })
  await page.getByPlaceholder('Buscar por nombre o categoría...').fill('arroz')
  await page.locator('li', { hasText: 'Arroz 1 kg' }).first().click()
  await page.getByLabel('Cantidad de Arroz 1 kg').fill('5')
  const registrar = page.getByRole('button', { name: /Registrar surtido/ })

  await registrar.click()
  await expect(page.getByText('Revisa el costo de Arroz 1 kg')).toBeVisible()

  await page.getByLabel('Costo de Arroz 1 kg').fill('25')
  await page.getByLabel('Cantidad de Arroz 1 kg').fill('0')
  await registrar.click()
  await expect(page.getByText('Revisa la cantidad de Arroz 1 kg')).toBeVisible()

  const { surtidos } = await (await api.get('/api/getAllRestocks')).json()
  expect(surtidos).toEqual([])
})
