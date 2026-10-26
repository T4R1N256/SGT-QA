// Inventario, Categorías y Proveedores (RF06, RF07): nombres repetidos, teléfono, estado y texto tal cual
import { ADMIN, expect, fila, iniciarSesion, ir, modal, producto, sinModal, test } from './ayudas'

async function categoria(api, nombre) {
  const { categorias } = await (await api.get('/api/getAllCategories?todas=1')).json()
  return categorias.find((c) => c.Nombre_Categoria === nombre)
}

async function proveedor(api, nombre) {
  const { proveedores } = await (await api.get('/api/getAllSuppliers?todos=1')).json()
  return proveedores.find((p) => p.Nombre_Proveedor === nombre)
}

const conBordeRojo = /border-red-400/

test.describe('Categorías', () => {
  test.beforeEach(async ({ page }) => {
    await iniciarSesion(page, ADMIN)
    await ir(page, '#/categorias')
    await fila(page, 'Abarrotes').waitFor()
  })

  test('la tabla muestra los productos activos y el estado; un nombre repetido se rechaza en el campo', async ({
    page,
    api,
  }) => {
    const abarrotes = await categoria(api, 'Abarrotes')
    await expect(fila(page, 'Abarrotes').locator('span').nth(2)).toHaveText(
      String(abarrotes.Productos)
    )
    await expect(fila(page, 'Abarrotes').locator('span').nth(3)).toHaveText('Activo')

    await page.getByRole('button', { name: 'Nueva categoría' }).click()
    await modal(page).getByPlaceholder('Nombre de la categoría').fill('abarrotes')
    await modal(page).getByRole('button', { name: 'Guardar categoría' }).click()
    await expect(modal(page).getByText('Ya existe una categoría llamada «Abarrotes»')).toBeVisible()
    await expect(modal(page).getByPlaceholder('Nombre de la categoría')).toHaveClass(conBordeRojo)
  })

  test('desactivar una categoría con productos activos pide confirmación y se puede volver a activar', async ({
    page,
    api,
  }) => {
    const { Productos } = await categoria(api, 'Abarrotes')

    await fila(page, 'Abarrotes').click()
    await page.getByRole('button', { name: /Desactivar seleccionada/ }).click()
    await expect(
      modal(page).getByText(
        `«Abarrotes» tiene ${Productos} productos activos. Si la desactivas, la tienda en línea deja de mostrarlos`
      )
    ).toBeVisible()
    await modal(page).getByRole('button', { name: 'Desactivar de todas formas' }).click()
    await sinModal(page)
    await expect(
      fila(page, 'Abarrotes').locator('span').nth(3),
      'sigue en la lista, inactiva'
    ).toHaveText('Inactivo')
    expect((await categoria(api, 'Abarrotes')).Estado).toBe('inactivo')

    // Volver a activarla desde Editar
    await fila(page, 'Abarrotes').click()
    await page.getByRole('button', { name: /Editar seleccionada/ }).click()
    await modal(page).locator('select').selectOption('activo')
    await modal(page).getByRole('button', { name: 'Guardar cambios' }).click()
    await sinModal(page)
    await expect(fila(page, 'Abarrotes').locator('span').nth(3)).toHaveText('Activo')
  })

  test('desde Editar, el primer intento de desactivar muestra el aviso y el segundo confirma', async ({
    page,
    api,
  }) => {
    await fila(page, 'Bebidas').click()
    await page.getByRole('button', { name: /Editar seleccionada/ }).click()
    await modal(page).locator('select').selectOption('inactivo')
    await modal(page).getByRole('button', { name: 'Guardar cambios' }).click()
    await expect(
      modal(page)
        .getByRole('alert')
        .filter({ hasText: /«Bebidas» tiene \d+ productos activos/ })
    ).toBeVisible()
    expect((await categoria(api, 'Bebidas')).Estado, 'el primer intento no desactiva').toBe(
      'activo'
    )

    await modal(page).getByRole('button', { name: 'Desactivar de todas formas' }).click()
    await sinModal(page)
    expect((await categoria(api, 'Bebidas')).Estado).toBe('inactivo')

    // Inventario solo ofrece las categorías activas
    await ir(page, '#/inventory')
    await page.getByRole('button', { name: /Nuevo producto/ }).click()
    const opciones = await modal(page).locator('select').nth(1).locator('option').allTextContents()
    expect(opciones).toContain('Abarrotes')
    expect(opciones).not.toContain('Bebidas')
  })

  test('una categoría sin productos se desactiva con la pregunta simple; el nombre se guarda tal cual', async ({
    page,
    api,
  }) => {
    await page.getByRole('button', { name: 'Nueva categoría' }).click()
    await modal(page).getByPlaceholder('Nombre de la categoría').fill('Frutas & Verduras <3')
    await modal(page).getByRole('button', { name: 'Guardar categoría' }).click()
    await fila(page, 'Frutas & Verduras <3').waitFor()
    expect(await categoria(api, 'Frutas & Verduras <3'), 'sin entidades HTML').toBeTruthy()

    await fila(page, 'Frutas & Verduras <3').click()
    await page.getByRole('button', { name: /Desactivar seleccionada/ }).click()
    await expect(
      modal(page).getByText(
        '¿Desactivar «Frutas & Verduras <3»? No podrá usarse para productos nuevos'
      )
    ).toBeVisible()
    await modal(page).getByRole('button', { name: 'Desactivar', exact: true }).click()
    await sinModal(page)
    expect((await categoria(api, 'Frutas & Verduras <3')).Estado).toBe('inactivo')
  })
})

test.describe('Proveedores', () => {
  test.beforeEach(async ({ page }) => {
    await iniciarSesion(page, ADMIN)
    await ir(page, '#/proveedores')
    await fila(page, 'Distribuidora La Central').waitFor()
  })

  test('el teléfono lleva 10 o 12 dígitos y un nombre repetido se rechaza', async ({
    page,
    api,
  }) => {
    const nombre = modal(page).getByPlaceholder('Nombre del proveedor')
    const telefono = modal(page).getByPlaceholder('10 o 12 dígitos')
    const guardar = modal(page).getByRole('button', { name: 'Guardar proveedor' })

    await page.getByRole('button', { name: 'Nuevo proveedor' }).click()
    await nombre.fill('Abarrotera del Sur')
    await telefono.fill('hola')
    await guardar.click()
    await expect(
      modal(page).getByText('El teléfono debe tener 10 o 12 dígitos numéricos')
    ).toBeVisible()
    await expect(telefono).toHaveClass(conBordeRojo)

    await nombre.fill('distribuidora la central')
    await telefono.fill('6561234567')
    await guardar.click()
    await expect(
      modal(page).getByText('Ya existe un proveedor llamado «Distribuidora La Central»')
    ).toBeVisible()
    await expect(nombre).toHaveClass(conBordeRojo)

    await nombre.fill('Abarrotera del Sur')
    await guardar.click()
    await fila(page, 'Abarrotera del Sur').waitFor()
    expect((await proveedor(api, 'Abarrotera del Sur')).Telefono).toBe('6561234567')
  })

  test('se desactiva y se vuelve a activar desde Editar; Surtir solo ofrece los activos', async ({
    page,
    api,
  }) => {
    const cambiarEstado = async (estado) => {
      await fila(page, 'Refrescos del Norte').click()
      await page.getByRole('button', { name: /Editar seleccionado/ }).click()
      await modal(page).locator('select').selectOption(estado)
      await modal(page).getByRole('button', { name: 'Guardar cambios' }).click()
      await sinModal(page)
    }

    await cambiarEstado('inactivo')
    expect((await proveedor(api, 'Refrescos del Norte')).Estado).toBe('inactivo')
    await expect(fila(page, 'Refrescos del Norte').locator('span').nth(3)).toHaveText('Inactivo')

    await ir(page, '#/surtir')
    await expect(
      page.locator('select option', { hasText: 'Distribuidora La Central' })
    ).toHaveCount(1)
    await expect(page.locator('select option', { hasText: 'Refrescos del Norte' })).toHaveCount(0)

    await ir(page, '#/proveedores')
    await cambiarEstado('activo')
    expect((await proveedor(api, 'Refrescos del Norte')).Estado).toBe('activo')
  })
})

test('Inventario guarda el texto tal cual y lo muestra igual (antes DOMPurify lo convertía en HTML)', async ({
  page,
  api,
}) => {
  await iniciarSesion(page, ADMIN)
  await ir(page, '#/inventory')
  await page.getByRole('button', { name: /Nuevo producto/ }).click()
  const formulario = modal(page)
  await formulario.getByPlaceholder('Nombre del producto').fill('Pan & Cía <grande>')
  await formulario.getByPlaceholder('0.00').fill('12.50')
  const numeros = formulario.locator('input[type="number"][placeholder="0"]')
  await numeros.nth(0).fill('8')
  await numeros.nth(1).fill('2')
  const selects = formulario.locator('select')
  await selects.nth(0).selectOption('pieza')
  await selects.nth(1).selectOption({ label: 'Panadería' })
  await selects.nth(2).selectOption({ index: 1 })
  await formulario.getByRole('button', { name: 'Guardar producto' }).click()
  await sinModal(page)

  await expect(page.locator('li', { hasText: 'Pan & Cía <grande>' })).toHaveCount(1)
  const pan = await producto(api, 'Pan & Cía <grande>')
  expect(pan.Cantidad).toBe(8)
  expect(pan.Precio).toBe('12.50')
})
