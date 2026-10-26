// Gestión de usuarios (RF13): alta, validación del formulario, baja y reactivación, cambio de contraseña
import {
  ADMIN,
  EMPLEADO,
  aceptar,
  cerrarSesion,
  expect,
  fila,
  iniciarSesion,
  ir,
  modal,
  sinModal,
  test,
} from './ayudas'

// Campo del formulario de usuario por su etiqueta (las etiquetas no están ligadas al campo)
const campo = (page, etiqueta) =>
  modal(page)
    .locator('div')
    .filter({ has: page.locator('label', { hasText: new RegExp(`^${etiqueta}$`) }) })
    .last()
    .locator('input, select')

async function idDe(api, nombre) {
  const { usuarios } = await (await api.get('/api/getUsers')).json()
  return String(usuarios.find((u) => u.Nombre === nombre).ID_Usuario)
}

async function intentarEntrar(page, [id, password]) {
  await page.getByPlaceholder('ID de usuario').fill(id)
  await page.getByPlaceholder('Contraseña').fill(password)
  await page.getByRole('button', { name: /Iniciar Sesión/i }).click()
}

test('el admin registra un empleado: el formulario marca el teléfono y las contraseñas distintas, y el nuevo empleado entra', async ({
  page,
  api,
}) => {
  await iniciarSesion(page, ADMIN)
  await ir(page, '#/users')
  await page.getByRole('button', { name: /Registrar usuario/ }).click()

  await campo(page, 'Nombre completo \\*').fill('Lupita Martínez')
  await campo(page, 'Puesto \\*').selectOption('empleado')
  await campo(page, 'Teléfono').fill('656-123')
  await campo(page, 'Contraseña \\*').fill('secreta1')
  await campo(page, 'Confirmar contraseña').fill('secreta2')
  const registrar = modal(page).getByRole('button', { name: 'Registrar' })

  await registrar.click()
  await expect(modal(page).getByText('Las contraseñas no coinciden')).toBeVisible()
  await campo(page, 'Confirmar contraseña').fill('secreta1')
  await registrar.click()
  await expect(
    modal(page).getByText('El teléfono debe tener 10 o 12 dígitos numéricos')
  ).toBeVisible()

  await campo(page, 'Teléfono').fill('6561234567')
  await registrar.click()
  await expect(modal(page).getByText(/creado/i)).toBeVisible()
  await page.keyboard.press('Escape')
  await fila(page, 'Lupita Martínez').waitFor()

  const id = await idDe(api, 'Lupita Martínez')
  await cerrarSesion(page)
  await iniciarSesion(page, [id, 'secreta1'])
  await expect(page.getByText('Lupita Martínez').first()).toBeVisible()
  // Entra como empleado: sin módulos de admin
  await expect(page.getByRole('link', { name: 'Usuarios', exact: true })).toHaveCount(0)
})

test('un usuario no autorizado no entra; al autorizarlo otra vez con otra contraseña entra con la nueva', async ({
  page,
}) => {
  const editarEmpleado = async () => {
    await ir(page, '#/users')
    await fila(page, 'Empleado Demo').click()
    await page.getByText('Empleado Demo').nth(1).waitFor()
    await page.getByRole('button', { name: /Editar seleccionado/ }).click()
  }

  await iniciarSesion(page, ADMIN)
  await editarEmpleado()
  await campo(page, 'Estado \\*').selectOption('no autorizado')
  await modal(page).getByRole('button', { name: 'Guardar cambios' }).click()
  await sinModal(page)
  await expect(fila(page, 'Empleado Demo'), 'sigue en la lista').toBeVisible()
  await cerrarSesion(page)

  await intentarEntrar(page, EMPLEADO)
  await expect(page.getByText('Usuario no autorizado')).toBeVisible()

  await iniciarSesion(page, ADMIN)
  await editarEmpleado()
  await campo(page, 'Estado \\*').selectOption('autorizado')
  await campo(page, 'Nueva contraseña').fill('nueva2026')
  await campo(page, 'Confirmar contraseña').fill('nueva2026')
  await modal(page).getByRole('button', { name: 'Guardar cambios' }).click()
  await sinModal(page)
  await cerrarSesion(page)

  await intentarEntrar(page, EMPLEADO)
  await expect(
    page.getByText('Credenciales incorrectas'),
    'la contraseña anterior ya no sirve'
  ).toBeVisible()
  await iniciarSesion(page, [EMPLEADO[0], 'nueva2026'])
})

test('eliminar un usuario lo quita de la lista y ya no puede entrar', async ({ page }) => {
  await iniciarSesion(page, ADMIN)
  await ir(page, '#/users')
  await fila(page, 'Empleado Demo').click()
  await page.getByText('Empleado Demo').nth(1).waitFor()
  await page.getByRole('button', { name: /Eliminar seleccionado/ }).click()
  await modal(page).getByRole('button', { name: 'Confirmar' }).click()
  await modal(page).getByText('fue desactivado correctamente').waitFor()
  await aceptar(page)
  await sinModal(page)
  await expect(fila(page, 'Empleado Demo')).toHaveCount(0)
  await cerrarSesion(page)

  await intentarEntrar(page, EMPLEADO)
  await expect(page.getByText('Usuario no autorizado')).toBeVisible()
  await expect(page).not.toHaveURL(/#\/dashboard/)
})
