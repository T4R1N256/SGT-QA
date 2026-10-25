import { describe, expect, it } from 'vitest'
import { ENTIDADES, nuevaClave, validarEntrega } from './validaciones'

const valida = {
  recibe: 'Ana Pérez',
  telefono: '656-123-4567',
  calle: 'Av. Juárez',
  numero_exterior: '123-B',
  numero_interior: '',
  colonia: 'Centro',
  codigo_postal: '32000',
  ciudad: 'Ciudad Juárez',
  entidad: 'Chihuahua',
  referencias: 'Portón verde',
}
const errorDe = (cambios) => validarEntrega({ ...valida, ...cambios })

describe('validarEntrega', () => {
  it('acepta datos válidos; el número interior y las referencias son opcionales', () => {
    expect(validarEntrega(valida)).toEqual({})
  })

  it('marca cada campo obligatorio vacío o solo con espacios (P8)', () => {
    const vacios = Object.fromEntries(Object.keys(valida).map((campo) => [campo, '   ']))
    expect(validarEntrega(vacios)).toEqual({
      recibe: 'Escribe quién recibe el pedido',
      telefono: 'Escribe un teléfono de contacto',
      calle: 'Escribe la calle',
      numero_exterior: 'Escribe el número exterior',
      colonia: 'Escribe la colonia',
      codigo_postal: 'Escribe el código postal',
      ciudad: 'Escribe la ciudad o municipio',
      entidad: 'Elige la entidad',
    })
  })

  it('da un mensaje específico por formato (P9)', () => {
    expect(errorDe({ codigo_postal: '3200' }).codigo_postal).toBe(
      'El código postal debe tener 5 dígitos'
    )
    expect(errorDe({ codigo_postal: '32A00' }).codigo_postal).toBe(
      'El código postal debe tener 5 dígitos'
    )
    expect(errorDe({ telefono: '656123' }).telefono).toBe('El teléfono debe tener 10 dígitos')
    expect(errorDe({ entidad: 'Narnia' }).entidad).toBe('Elige una entidad de la lista')
  })

  it('rechaza HTML en nombre y número, y textos largos (P10)', () => {
    expect(errorDe({ recibe: '<b>Ana</b>' }).recibe).toBe(
      'El nombre de quien recibe solo puede tener letras, espacios, apóstrofos y guiones'
    )
    expect(errorDe({ numero_exterior: '<1>' }).numero_exterior).toBe(
      'El número exterior solo puede tener letras, números, espacios y - / # .'
    )
    expect(errorDe({ calle: 'a'.repeat(101) }).calle).toBe(
      'La calle no puede tener más de 100 caracteres'
    )
    expect(errorDe({ referencias: 'a'.repeat(256) }).referencias).toBe(
      'Las referencias no pueden tener más de 255 caracteres'
    )
  })

  it('acepta números como S/N o #12 y comillas en la calle', () => {
    expect(
      errorDe({ numero_exterior: 'S/N', numero_interior: '#12', calle: 'Calle "La Ñ"' })
    ).toEqual({})
  })
})

describe('ENTIDADES', () => {
  it('son las 32 entidades federativas', () => {
    expect(ENTIDADES).toHaveLength(32)
    expect(new Set(ENTIDADES).size).toBe(32)
  })
})

describe('nuevaClave', () => {
  it('genera claves distintas de 32 caracteres hexadecimales', () => {
    const a = nuevaClave()
    expect(a).toMatch(/^[0-9a-f]{32}$/)
    expect(nuevaClave()).not.toBe(a)
  })
})
