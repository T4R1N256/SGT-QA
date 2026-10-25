// Catálogo (fase T1): N1–N4, P13 en la lista, P14 y P15
import { expect, idDe, test } from './ayudas'

test('N1: el inicio muestra las categorías y productos destacados sin iniciar sesión', async ({
  page,
}) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { name: /Bienvenido a/ })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Categorías' }).getByRole('listitem')).toHaveCount(
    12
  )
  const destacados = page.getByRole('list', { name: 'Productos destacados' })
  await expect(destacados.getByRole('listitem')).toHaveCount(12)
  await expect(destacados.getByText('Agotado')).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Ingresar' })).toBeVisible()
})

test('N2: una categoría muestra sus productos paginados con precio y disponibilidad', async ({
  page,
}) => {
  await page.goto('/')
  await page
    .getByRole('navigation', { name: 'Categorías' })
    .getByRole('link', { name: 'Abarrotes' })
    .click()

  await expect(page.getByRole('heading', { level: 1, name: 'Abarrotes' })).toBeVisible()
  const productos = page.getByRole('list', { name: 'Productos' }).getByRole('listitem')
  await expect(productos).toHaveCount(12)
  await expect(page.getByText('15 productos', { exact: true })).toBeVisible()
  await expect(productos.first()).toContainText('$')
  await expect(
    page.getByRole('list', { name: 'Productos' }).getByText(/^(Disponible|Pocas piezas|Agotado)$/)
  ).toHaveCount(12)

  await page.getByRole('link', { name: 'Página siguiente' }).click()
  await expect(page).toHaveURL(/pagina=2/)
  await expect(productos).toHaveCount(3)

  // P13: Atrás regresa a la página 1
  await page.goBack()
  await expect(page.getByRole('link', { name: 'Página 1' })).toHaveAttribute('aria-current', 'page')
  await expect(productos).toHaveCount(12)
})

test('N2: los filtros "Solo disponibles" y "Ordenar por" viven en la dirección', async ({
  page,
}) => {
  await page.goto('/categoria/1')
  await expect(page.getByRole('heading', { level: 1 })).not.toBeEmpty()

  await page.getByLabel('Solo disponibles').check()
  await expect(page).toHaveURL(/disponibles=1/)
  await expect(page.getByRole('list', { name: 'Productos' }).getByText('Agotado')).toHaveCount(0)

  await page.getByLabel('Ordenar por').selectOption('precio_desc')
  await expect(page).toHaveURL(/orden=precio_desc/)
  await expect
    .poll(async () => {
      const precios = await page
        .getByRole('list', { name: 'Productos' })
        .locator('li span.text-lg')
        .allInnerTexts()
      const numeros = precios.map((p) => Number(p.replace(/[^\d.]/g, '')))
      return numeros.length > 1 && numeros.every((p, i) => i === 0 || numeros[i - 1] >= p)
    })
    .toBe(true)

  // P13: Atrás quita el orden y conserva el filtro anterior
  await page.goBack()
  await expect(page).not.toHaveURL(/orden=/)
  await expect(page.getByLabel('Solo disponibles')).toBeChecked()
})

test('N3: la búsqueda no distingue acentos', async ({ page }) => {
  await page.goto('/')
  await page.getByLabel('Buscar productos').fill('azucar')
  await page.getByLabel('Buscar productos').press('Enter')

  await expect(page).toHaveURL(/\/buscar\?q=azucar/)
  await expect(page.getByRole('heading', { name: 'Resultados para «azucar»' })).toBeVisible()
  await expect(
    page
      .getByRole('list', { name: 'Productos' })
      .getByRole('heading', { name: 'Azúcar estándar 1 kg' })
  ).toBeVisible()

  // Sin resultados lo explica; Atrás restaura la búsqueda anterior
  await page.getByLabel('Buscar productos').fill('xyzzy')
  await page.getByLabel('Buscar productos').press('Enter')
  await expect(page.getByText(/No encontramos productos para «xyzzy»/)).toBeVisible()
  await page.goBack()
  await expect(page.getByLabel('Buscar productos')).toHaveValue('azucar')
})

test('N4: el detalle muestra nombre, descripción, categoría, precio con IVA y disponibilidad', async ({
  page,
}) => {
  await page.goto(`/producto/${await idDe(page, 'Azúcar estándar 1 kg')}`)

  await expect(page.getByRole('heading', { level: 1, name: 'Azúcar estándar 1 kg' })).toBeVisible()
  const detalle = page.locator('main')
  await expect(detalle).toContainText(
    'Azúcar de caña estándar para endulzar bebidas y preparar postres.'
  )
  await expect(detalle).toContainText('$34.00')
  await expect(detalle).toContainText('IVA incluido ($4.69)')
  await expect(page.getByText('Disponible', { exact: true })).toBeVisible()
  await expect(
    page.getByRole('main').getByRole('link', { name: 'Abarrotes' }).first()
  ).toBeVisible()
  await expect(page).toHaveTitle(/^Azúcar estándar 1 kg · /)
})

test('N4: un producto con pocas piezas y uno agotado lo dicen', async ({ page }) => {
  await page.goto(`/producto/${await idDe(page, 'Mayonesa 390 g')}`)
  await expect(page.getByText('Quedan solo 3 piezas')).toBeVisible()
  await expect(page.getByText('Pocas piezas', { exact: true })).toBeVisible()

  await page.goto(`/producto/${await idDe(page, 'Chocolate de mesa 6 tablillas')}`)
  await expect(page.getByText('Por ahora no hay existencias')).toBeVisible()
})

test('P14: un producto o una categoría que no existe muestra "no encontrado"', async ({ page }) => {
  for (const ruta of ['/producto/99999', '/producto/abc']) {
    await page.goto(ruta)
    await expect(page.getByRole('heading', { name: 'Producto no encontrado' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Ir al inicio' })).toBeVisible()
  }
  await page.goto('/categoria/99999')
  await expect(page.getByRole('heading', { name: 'Categoría no encontrada' })).toBeVisible()

  // Filtros inválidos escritos a mano en la dirección se ignoran
  await page.goto('/buscar?pagina=abc&orden=costo&categoria=-1')
  await expect(page.getByRole('heading', { name: 'Todos los productos' })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Productos' }).getByRole('listitem')).toHaveCount(12)
})

test('P15: si el API no responde se avisa y se puede reintentar, sin pantalla en blanco', async ({
  page,
}) => {
  await page.route('**/api/tienda/**', (ruta) => ruta.abort())
  await page.goto('/')

  await expect(page.getByRole('alert')).toHaveCount(2)
  await expect(page.getByRole('alert').first()).toContainText('No se pudo cargar esta sección')
  await expect(page.getByRole('heading', { name: /Bienvenido a/ })).toBeVisible()

  await page.unroute('**/api/tienda/**')
  // Cada sección se reintenta por su cuenta: al cargar, su botón desaparece
  const reintentar = page.getByRole('button', { name: 'Reintentar' })
  await reintentar.first().click()
  await expect(reintentar).toHaveCount(1)
  await reintentar.first().click()
  await expect(
    page.getByRole('list', { name: 'Productos destacados' }).getByRole('listitem')
  ).toHaveCount(12)
  await expect(page.getByRole('alert')).toHaveCount(0)
})
