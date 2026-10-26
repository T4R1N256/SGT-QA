// Reportes del admin: ventas, surtidos y compras, stock y resumen, con exportación a PDF (RF11, regla 5)
import {
  ADMIN,
  EMPLEADO,
  abrirCaja,
  aceptar,
  agregar,
  archivoPDF,
  cerrarCaja,
  cerrarSesion,
  cobrar,
  expect,
  hoy,
  iniciarSesion,
  ir,
  paginasPDF,
  producto,
  test,
  textoPDF,
} from './ayudas'

const dmy = (fecha) => fecha.split('-').reverse().join('/')

test('reportes de ventas, surtidos, stock y resumen con sus PDF', async ({
  page,
  api,
  carpeta,
}) => {
  const fecha = hoy()
  const inicioMes = `${fecha.slice(0, 8)}01`
  const [anio, mes] = fecha.split('-').map(Number)
  const finMes = `${fecha.slice(0, 8)}${String(new Date(anio, mes, 0).getDate()).padStart(2, '0')}`
  const indicador = (etiqueta) =>
    page.locator('div', { has: page.getByText(etiqueta, { exact: true }) }).last()
  const exportar = async () => {
    await page.getByRole('button', { name: /Exportar PDF/ }).click()
    await page.getByText('Reporte exportado').waitFor()
    await aceptar(page)
  }

  await test.step('el admin surte Harina y Pasta por $435.30 y el empleado vende Arroz y Harina', async () => {
    const harina = await producto(api, 'Harina de maíz 1 kg')
    const pasta = await producto(api, 'Pasta para sopa 200 g')
    const res = await api.post('/api/createRestock', {
      data: {
        ID_Proveedor: 1,
        productos: [
          { ID_Producto: harina.ID_Producto, Cantidad: 12, Costo: 18.4 },
          { ID_Producto: pasta.ID_Producto, Cantidad: 30, Costo: 7.15 },
        ],
      },
    })
    expect(res.status()).toBe(201)

    await iniciarSesion(page, EMPLEADO)
    await abrirCaja(page, 100)
    await ir(page, '#/sales')
    await agregar(page, 'arroz', 'Arroz 1 kg')
    await cobrar(page, { pago: 50 })
    await agregar(page, 'harina', 'Harina de maíz')
    await cobrar(page, { tarjeta: true })
    await cerrarCaja(page, /Corte de caja: cuadrada/)
    await cerrarSesion(page)
  })

  await test.step('ventas del mes y su PDF', async () => {
    await iniciarSesion(page, ADMIN)
    await page.getByRole('link', { name: 'Reportes' }).click()
    await page.getByText('Total vendido').waitFor()
    await expect(
      page.getByText(`Del ${dmy(inicioMes)} al ${dmy(finMes)}`),
      'el periodo por defecto es el mes actual'
    ).toBeVisible()
    await expect(indicador('Total vendido'), 'Arroz $32.50 + Harina $24.90').toContainText('$57.40')
    await expect(indicador('Ticket promedio')).toContainText('$28.70')
    await expect(page.locator('tr', { hasText: 'Efectivo' })).toHaveCount(1)
    await expect(page.locator('tr', { hasText: 'Tarjeta' })).toHaveCount(1)
    await expect(page.locator('tr', { hasText: 'Harina de maíz 1 kg' })).toHaveCount(1)

    await exportar()
    const pdf = archivoPDF(carpeta, 'Reportes', `reporte-ventas-${inicioMes}_${finMes}`)
    expect(pdf, 'Reportes/reporte-ventas-<mes>.pdf').toBeTruthy()
    const texto = textoPDF(pdf)
    for (const fragmento of [
      'Reporte de ventas',
      '$57.40',
      'Productos más vendidos',
      'Abarrotes',
    ]) {
      expect.soft(texto, `el PDF contiene "${fragmento}"`).toContain(fragmento)
    }
    expect(texto, 'número de página al pie').toMatch(/Página 1 de \d/)
  })

  await test.step('un día sin ventas muestra $0.00 y tablas vacías', async () => {
    await page.getByLabel('Periodo').selectOption('dia')
    await page.getByLabel('Fecha', { exact: true }).fill('2020-01-15')
    await page.getByText('15/01/2020', { exact: true }).waitFor()
    await expect(page.getByText('Sin movimientos en este periodo').first()).toBeVisible()
    await expect(indicador('Total vendido')).toContainText('$0.00')
    await page.getByLabel('Periodo').selectOption('mes')
    await page.getByLabel('Mes', { exact: true }).fill(fecha.slice(0, 7))
    await page.getByText(`Del ${dmy(inicioMes)}`).waitFor()
  })

  await test.step('surtidos y compras', async () => {
    await page.getByRole('tab', { name: 'Surtidos y compras' }).click()
    await page.getByText('Total comprado').waitFor()
    await expect(indicador('Total comprado')).toContainText('$435.30')
    await expect(page.locator('tr', { hasText: 'Distribuidora La Central' }).first()).toBeVisible()
  })

  await test.step('stock y su PDF de varias páginas', async () => {
    await page.getByRole('tab', { name: 'Stock' }).click()
    await page.getByText('Valor a precio de venta').waitFor()
    await expect(
      page.getByText(/Existencias en este momento/),
      'el de stock no pide periodo'
    ).toHaveCount(1)
    await expect(page.locator('tr', { hasText: 'Chicles menta' }).first()).toContainText('Agotado')
    await expect(page.getByText('No incluye 30 productos sin costo')).toBeVisible()

    await exportar()
    const pdf = archivoPDF(carpeta, 'Reportes', `reporte-stock-${fecha}`)
    expect(pdf, `Reportes/reporte-stock-${fecha}.pdf`).toBeTruthy()
    const texto = textoPDF(pdf)
    for (const fragmento of ['Reporte de stock', 'Productos por surtir', 'Pasta dental 100 ml']) {
      expect.soft(texto, `el PDF contiene "${fragmento}"`).toContain(fragmento)
    }
    const paginas = paginasPDF(pdf)
    expect(paginas).toBeGreaterThanOrEqual(2)
    expect(texto).toContain(`Página 2 de ${paginas}`)
  })

  await test.step('resumen: ventas menos compras, utilidad estimada y cortes', async () => {
    await page.getByRole('tab', { name: 'Resumen' }).click()
    await page.getByText('Utilidad estimada').waitFor()
    await expect(indicador('Ventas menos compras'), '$57.40 - $435.30').toContainText('-$377.90')
    await expect(indicador('Utilidad estimada'), 'Harina $24.90 - $18.40').toContainText('$6.50')
    await expect(page.getByText('Margen 26.1 % · cubre 43.4 % de lo vendido')).toBeVisible()
    await expect(page.getByText('0 descuadrados · diferencia $0.00')).toBeVisible()
  })
})
