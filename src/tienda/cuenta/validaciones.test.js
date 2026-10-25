import { describe, expect, it } from 'vitest'
import { destinoSeguro, validarIngreso, validarRegistro } from './validaciones'

const validos = {
  nombre: 'Ana María',
  apellidos: "O'Connor Pérez-Díaz",
  correo: 'ana@ejemplo.mx',
  telefono: '(656) 123-4567',
  password: 'secreta123',
  confirmar: 'secreta123',
}
const errorDe = (cambios) => validarRegistro({ ...validos, ...cambios })

describe('validarRegistro', () => {
  it('acepta datos válidos, con acentos, apóstrofos y teléfono con espacios o guiones', () => {
    expect(validarRegistro(validos)).toEqual({})
  })

  it('marca cada campo vacío o solo con espacios (P8)', () => {
    expect(
      validarRegistro({ nombre: '  ', apellidos: '', correo: ' ', telefono: '', password: '' })
    ).toEqual({
      nombre: 'Escribe tu nombre',
      apellidos: 'Escribe tus apellidos',
      correo: 'Escribe tu correo',
      telefono: 'Escribe tu teléfono',
      password: 'Escribe una contraseña',
    })
  })

  it('da un mensaje específico por formato (P9)', () => {
    expect(errorDe({ correo: 'ana@' }).correo).toBe(
      'Escribe un correo válido, como nombre@ejemplo.com'
    )
    expect(errorDe({ telefono: '12345' }).telefono).toBe('El teléfono debe tener 10 dígitos')
    expect(errorDe({ password: 'abc123', confirmar: 'abc123' }).password).toBe(
      'La contraseña debe tener al menos 8 caracteres'
    )
    expect(errorDe({ password: 'abcdefghij', confirmar: 'abcdefghij' }).password).toBe(
      'La contraseña debe tener letras y números'
    )
    expect(errorDe({ confirmar: 'otra12345' }).confirmar).toBe('Las contraseñas no coinciden')
  })

  it('rechaza HTML, comillas dobles y textos demasiado largos (P10)', () => {
    expect(errorDe({ nombre: '<script>' }).nombre).toBe(
      'El nombre solo puede tener letras, espacios, apóstrofos y guiones'
    )
    expect(errorDe({ apellidos: 'Pérez "2"' }).apellidos).toBe(
      'Los apellidos solo pueden tener letras, espacios, apóstrofos y guiones'
    )
    expect(errorDe({ nombre: 'a'.repeat(101) }).nombre).toBe(
      'El nombre no puede tener más de 100 caracteres'
    )
    const larga = 'a1'.repeat(37)
    expect(errorDe({ password: larga, confirmar: larga }).password).toBe(
      'La contraseña no puede tener más de 72 caracteres'
    )
  })
})

describe('validarIngreso', () => {
  it('pide correo y contraseña', () => {
    expect(validarIngreso({ correo: ' ', password: '' })).toEqual({
      correo: 'Escribe tu correo',
      password: 'Escribe tu contraseña',
    })
    expect(validarIngreso({ correo: 'a@b.mx', password: 'x' })).toEqual({})
  })
})

describe('destinoSeguro', () => {
  it('acepta rutas de la tienda', () => {
    expect(destinoSeguro('/compra/entrega')).toBe('/compra/entrega')
  })

  it('no lleva a otros sitios', () => {
    expect(destinoSeguro('//malo.com')).toBe('/cuenta')
    expect(destinoSeguro('/\\malo.com')).toBe('/cuenta')
    expect(destinoSeguro('https://malo.com')).toBe('/cuenta')
    expect(destinoSeguro(null, '/')).toBe('/')
  })
})
