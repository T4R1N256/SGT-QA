// Inicio de sesión, permisos por puesto y regla 4 (RF01, RF13, reglas 3, 4 y 5)
import {
  ADMIN,
  EMPLEADO,
  abrirCaja,
  aceptar,
  cerrarSesion,
  expect,
  iniciarSesion,
  ir,
  test,
} from './ayudas'

test('credenciales incorrectas, usuario inexistente y campos vacíos se rechazan con un mensaje', async ({
  page,
}) => {
  const entrar = page.getByRole('button', { name: /Iniciar Sesión/i })

  await entrar.click()
  await expect(page.getByText('Completa ambos campos')).toBeVisible()

  await page.getByPlaceholder('ID de usuario').fill('1')
  await page.getByPlaceholder('Contraseña').fill('equivocada')
  await entrar.click()
  await expect(page.getByText('Credenciales incorrectas')).toBeVisible()

  await page.getByPlaceholder('ID de usuario').fill('999')
  await entrar.click()
  await expect(page.getByText('Usuario no encontrado')).toBeVisible()
  await expect(page).not.toHaveURL(/#\/dashboard/)
})

test('el admin ve todos los módulos y el empleado solo los suyos (reglas 3 y 5)', async ({
  page,
}) => {
  const menu = (nombre) => page.getByRole('link', { name: nombre, exact: true })
  const admin = [
    'Historial',
    'Reportes',
    'Categorías',
    'Proveedores',
    'Inventario',
    'Surtir',
    'Usuarios',
    'Configuración',
  ]

  await iniciarSesion(page, ADMIN)
  for (const modulo of [...admin, 'Ventas', 'Caja'])
    await expect(menu(modulo), modulo).toBeVisible()
  await cerrarSesion(page)

  await iniciarSesion(page, EMPLEADO)
  await expect(menu('Ventas')).toBeVisible()
  await expect(menu('Caja')).toBeVisible()
  for (const modulo of admin) await expect(menu(modulo), modulo).toHaveCount(0)

  // Entrar por la dirección a una página de admin regresa al inicio
  for (const ruta of ['#/historial', '#/report', '#/inventory', '#/users', '#/categorias']) {
    await ir(page, ruta)
    await expect(page, ruta).toHaveURL(/#\/dashboard/)
  }
})

test('una contraseña con "&" y "<" funciona en el Login', async ({ page, api }) => {
  // Antes el Login pasaba la contraseña por DOMPurify y "Clave&2025<x>" llegaba como "Clave&amp;2025"
  const res = await api.post('/api/createUser', {
    data: { Nombre: 'Rosa & Hijos', Puesto: 'empleado', Password: 'Clave&2025<x>' },
  })
  expect(res.status()).toBe(201)
  const { usuarios } = await (await api.get('/api/getUsers')).json()
  const id = String(usuarios.find((u) => u.Nombre === 'Rosa & Hijos').ID_Usuario)

  await iniciarSesion(page, [id, 'Clave&2025<x>'])
  await expect(page.getByText('Rosa & Hijos').first()).toBeVisible()
})

test('regla 4: con la caja abierta la ventana no se cierra; con la caja cerrada sí', async ({
  app,
  page,
}) => {
  const ventanas = () => app.evaluate(({ BrowserWindow }) => BrowserWindow.getAllWindows().length)
  const intentarCerrar = () =>
    app.evaluate(({ BrowserWindow }) => BrowserWindow.getAllWindows()[0]?.close())

  await iniciarSesion(page, EMPLEADO)
  await abrirCaja(page, 0)

  await intentarCerrar()
  await page.waitForTimeout(1000)
  expect(await ventanas(), 'con la caja abierta la ventana sigue').toBe(1)
  // Cerrar sesión también se bloquea
  await expect(page.getByRole('button', { name: /Cerrar sesión/ })).toBeDisabled()

  await ir(page, '#/caja')
  await page.getByRole('button', { name: /Cerrar caja/i }).click()
  await page.getByRole('button', { name: /Confirmar cierre/i }).click()
  await page.getByText(/Corte de caja/).waitFor()
  await aceptar(page)

  const cerrada = new Promise((resolve) => {
    app.once('close', () => resolve(true))
    setTimeout(() => resolve(false), 5000)
  })
  await intentarCerrar().catch(() => {})
  expect(await cerrada, 'con la caja cerrada la app termina').toBe(true)
})
