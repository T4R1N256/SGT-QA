// Cuentas de cliente (fase T3): registro, ingreso y salida; P8–P10; regreso a la compra con el carrito (T-S2)
import {
  agregarAlCarrito,
  correoUnico,
  errorDe,
  expect,
  idDe,
  llenar,
  registrarCliente,
  sinCargas,
  test,
} from './ayudas'

const campo = (page, etiqueta) => page.getByLabel(etiqueta, { exact: true })

test('P8: ingresar sin datos marca cada campo y enfoca el primero', async ({ page }) => {
  await page.goto('/ingresar')
  await page.getByRole('button', { name: 'Ingresar' }).click()

  await expect(page.getByRole('alert')).toHaveText('Revisa los datos marcados')
  expect(await errorDe(campo(page, 'Correo'))).toBe('Escribe tu correo')
  expect(await errorDe(campo(page, 'Contraseña'))).toBe('Escribe tu contraseña')
  await expect(campo(page, 'Correo')).toBeFocused()
})

test('P8–P10: el registro rechaza datos vacíos, con formato incorrecto o con HTML, campo por campo', async ({
  page,
}) => {
  await page.goto('/registro')
  await llenar(page, { Nombre: '   ', Apellidos: ' ' })
  await page.getByRole('button', { name: 'Crear cuenta' }).click()
  const vacios = {
    Nombre: 'Escribe tu nombre',
    Apellidos: 'Escribe tus apellidos',
    Correo: 'Escribe tu correo',
    Teléfono: 'Escribe tu teléfono',
    Contraseña: 'Escribe una contraseña',
  }
  for (const [etiqueta, mensaje] of Object.entries(vacios))
    expect(await errorDe(campo(page, etiqueta)), etiqueta).toBe(mensaje)

  await llenar(page, {
    Nombre: '<b>Ana</b>',
    Apellidos: 'Pérez "2"',
    Correo: 'ana@',
    Teléfono: '656 12',
    Contraseña: 'abcdefghij',
    'Confirmar contraseña': 'abcdefghij',
  })
  const formatos = {
    Nombre: 'El nombre solo puede tener letras, espacios, apóstrofos y guiones',
    Apellidos: 'Los apellidos solo pueden tener letras, espacios, apóstrofos y guiones',
    Correo: 'Escribe un correo válido, como nombre@ejemplo.com',
    Teléfono: 'El teléfono debe tener 10 dígitos',
    Contraseña: 'La contraseña debe tener letras y números',
  }
  for (const [etiqueta, mensaje] of Object.entries(formatos))
    expect(await errorDe(campo(page, etiqueta)), etiqueta).toBe(mensaje)
  await expect(campo(page, 'Correo')).toHaveAttribute('aria-invalid', 'true')

  await llenar(page, { Contraseña: 'secreta123', 'Confirmar contraseña': 'secreta12' })
  expect(await errorDe(campo(page, 'Confirmar contraseña'))).toBe('Las contraseñas no coinciden')
  await expect(page).toHaveURL(/\/registro$/)
})

test('Registro, sesión, salida y correo repetido', async ({ page }) => {
  const correo = correoUnico('ana')
  await page.goto('/registro')
  await llenar(page, {
    Nombre: 'Ana María',
    Apellidos: "O'Connor Pérez",
    Correo: correo.toUpperCase(),
    Teléfono: '(656) 123-4567',
    Contraseña: 'secreta123',
    'Confirmar contraseña': 'secreta123',
  })
  await page.getByRole('button', { name: 'Crear cuenta' }).click()

  await expect(page).toHaveURL(/\/cuenta$/)
  await expect(page.locator('main')).toContainText("Ana María O'Connor Pérez")
  await expect(page.locator('main')).toContainText(correo)
  await expect(page.locator('main')).toContainText('6561234567')
  await expect(page.getByRole('link', { name: 'Hola, Ana María' })).toBeVisible()

  // La sesión se conserva al recargar; /ingresar con sesión lleva a Mi cuenta
  await page.reload()
  await expect(page.getByRole('link', { name: 'Hola, Ana María' })).toBeVisible()
  await page.goto('/ingresar')
  await expect(page).toHaveURL(/\/cuenta$/)

  // Salir
  await page.getByRole('button', { name: 'Cerrar sesión' }).click()
  await expect(page).toHaveURL(/\/$/)
  await page.goto('/cuenta')
  await expect(page).toHaveURL(/\/ingresar\?siguiente=%2Fcuenta$/)

  // Correo repetido (lo decide el servidor)
  await page.goto('/registro')
  await llenar(page, {
    Nombre: 'Otra',
    Apellidos: 'Persona',
    Correo: correo,
    Teléfono: '6560000000',
    Contraseña: 'otra12345',
    'Confirmar contraseña': 'otra12345',
  })
  await page.getByRole('button', { name: 'Crear cuenta' }).click()
  await expect(page.getByRole('alert')).toBeVisible()
  expect(await errorDe(campo(page, 'Correo'))).toBe('Ya existe una cuenta con este correo')

  // Contraseña incorrecta y correcta
  await page.goto('/ingresar')
  await llenar(page, { Correo: correo, Contraseña: 'equivocada1' })
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('alert')).toHaveText('Correo o contraseña incorrectos')
  await llenar(page, { Contraseña: 'secreta123' })
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page).toHaveURL(/\/cuenta$/)
})

test('T-S2: continuar la compra sin sesión pide cuenta y regresa con el carrito intacto', async ({
  page,
}) => {
  await agregarAlCarrito(page, await idDe(page, 'Azúcar estándar 1 kg'), 1)
  await page.goto('/carrito')
  await sinCargas(page)
  await page.getByRole('button', { name: 'Continuar con la compra' }).click()

  await expect(page).toHaveURL(/\/ingresar\?siguiente=%2Fcompra%2Fentrega$/)
  await expect(
    page.getByText(
      'Inicia sesión o crea una cuenta para continuar con tu compra. Tu carrito se conserva.'
    )
  ).toBeVisible()

  await page.getByRole('link', { name: 'Crea una' }).click()
  await expect(page).toHaveURL(/\/registro\?siguiente=%2Fcompra%2Fentrega$/)
  await llenar(page, {
    Nombre: 'Luis',
    Apellidos: 'Gómez',
    Correo: correoUnico('luis'),
    Teléfono: '6569876543',
    Contraseña: 'luis12345',
    'Confirmar contraseña': 'luis12345',
  })
  await page.getByRole('button', { name: 'Crear cuenta' }).click()
  await expect(page).toHaveURL(/\/compra\/entrega$/)
  await expect(page.getByRole('link', { name: 'Carrito: 1 pieza' })).toBeVisible()
})

test('?siguiente= con otro sitio no saca al cliente de la tienda', async ({ page }) => {
  const cliente = await registrarCliente(page)
  await page.request.post('/api/tienda/salir')

  await page.goto('/ingresar?siguiente=//ejemplo.com')
  await llenar(page, { Correo: cliente.correo, Contraseña: 'secreta123' })
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page).toHaveURL(/\/cuenta$/)
})
