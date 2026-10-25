import { describe, expect, it } from 'vitest'
import { validateCorreo, validateTelefono, validateUserForm } from './validators'

const formBase = {
  nombre: 'Ana López',
  puesto: 'empleado',
  estado: 'autorizado',
  password: 'secreta123',
  confirmar: 'secreta123',
  telefono: '',
  correo: '',
}

describe('validateTelefono', () => {
  it('acepta vacío porque el teléfono es opcional', () => {
    expect(validateTelefono('')).toBeNull()
  })

  it('acepta 10 o 12 dígitos', () => {
    expect(validateTelefono('6561234567')).toBeNull()
    expect(validateTelefono('526561234567')).toBeNull()
  })

  it('rechaza letras o longitudes distintas', () => {
    expect(validateTelefono('656123456a')).not.toBeNull()
    expect(validateTelefono('12345')).not.toBeNull()
  })
})

describe('validateCorreo', () => {
  it('acepta vacío y correos con formato válido', () => {
    expect(validateCorreo('')).toBeNull()
    expect(validateCorreo('ana@tienda.mx')).toBeNull()
  })

  it('rechaza formatos inválidos', () => {
    expect(validateCorreo('ana@')).not.toBeNull()
    expect(validateCorreo('ana tienda.mx')).not.toBeNull()
  })
})

describe('validateUserForm', () => {
  it('acepta un alta completa', () => {
    expect(validateUserForm(formBase, 'create')).toBeNull()
  })

  it('exige nombre, puesto y contraseña al crear', () => {
    const error = validateUserForm({ ...formBase, nombre: '  ' }, 'create')
    expect(error.field).toBe('required')
  })

  it('detecta contraseñas que no coinciden', () => {
    const error = validateUserForm({ ...formBase, confirmar: 'otra' }, 'create')
    expect(error.field).toBe('confirmar')
  })

  it('al editar permite dejar la contraseña vacía', () => {
    const form = { ...formBase, password: '', confirmar: '' }
    expect(validateUserForm(form, 'edit')).toBeNull()
  })

  it('reporta el campo con formato inválido', () => {
    expect(validateUserForm({ ...formBase, telefono: '123' }, 'create').field).toBe('telefono')
    expect(validateUserForm({ ...formBase, correo: 'malo' }, 'create').field).toBe('correo')
  })
})
