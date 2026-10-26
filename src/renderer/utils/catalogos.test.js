import { describe, expect, it } from 'vitest'
import {
  avisoDesactivarCategoria,
  errorDelApi,
  parseCategoria,
  parseProveedor,
  validateCategoria,
  validateProveedor,
} from './catalogos'

describe('validateCategoria', () => {
  const validar = (form) => validateCategoria(parseCategoria({ estado: 'activo', ...form }))

  it('acepta un nombre y lo recorta', () => {
    expect(parseCategoria({ nombre: '  Mascotas & más  ', estado: 'activo' }).nombre).toBe(
      'Mascotas & más'
    )
    expect(validar({ nombre: 'Mascotas' })).toBeNull()
  })

  it('pide el nombre y no acepta más de 50 caracteres', () => {
    expect(validar({ nombre: '   ' })).toEqual({
      message: 'El nombre es obligatorio',
      field: 'nombre',
    })
    expect(validar({ nombre: 'a'.repeat(50) })).toBeNull()
    expect(validar({ nombre: 'a'.repeat(51) })?.field).toBe('nombre')
  })

  it('solo acepta los estados activo e inactivo', () => {
    expect(validar({ nombre: 'X', estado: 'inactivo' })).toBeNull()
    expect(validar({ nombre: 'X', estado: 'borrado' })?.field).toBe('estado')
  })
})

describe('validateProveedor', () => {
  const validar = (form) =>
    validateProveedor(
      parseProveedor({ nombre: 'Abarrotera', telefono: '', estado: 'activo', ...form })
    )

  it('el teléfono es opcional', () => {
    expect(validar({})).toBeNull()
    expect(validar({ telefono: '   ' })).toBeNull()
  })

  it.each(['6561234567', '526561234567', ' 6561234567 '])('acepta %j', (telefono) => {
    expect(validar({ telefono })).toBeNull()
  })

  it.each(['hola', '656123456', '65612345678', '5265612345678', '656-123-4567', '+526561234567'])(
    'rechaza %j con el mismo mensaje que Usuarios',
    (telefono) => {
      expect(validar({ telefono })).toEqual({
        message: 'El teléfono debe tener 10 o 12 dígitos numéricos',
        field: 'telefono',
      })
    }
  )

  it('pide el nombre y no acepta más de 255 caracteres', () => {
    expect(validar({ nombre: '' })?.field).toBe('nombre')
    expect(validar({ nombre: 'a'.repeat(256) })?.field).toBe('nombre')
  })
})

describe('errorDelApi', () => {
  it('marca el campo que dice el API', () => {
    expect(
      errorDelApi(
        {
          ok: false,
          mensaje: 'Ya existe una categoría llamada «Abarrotes»',
          campo: 'Nombre_Categoria',
        },
        { Nombre_Categoria: 'nombre' },
        'Error'
      )
    ).toEqual({
      type: 'error',
      message: 'Ya existe una categoría llamada «Abarrotes»',
      field: 'nombre',
    })
  })

  it('sin mensaje usa el de respaldo y no marca ningún campo', () => {
    expect(errorDelApi({ ok: false }, { Nombre_Categoria: 'nombre' }, 'Error al crear')).toEqual({
      type: 'error',
      message: 'Error al crear',
      field: undefined,
    })
  })
})

describe('avisoDesactivarCategoria', () => {
  it('sin productos activos solo pregunta', () => {
    expect(avisoDesactivarCategoria('Temporada', 0)).toBe(
      '¿Desactivar «Temporada»? No podrá usarse para productos nuevos hasta que la vuelvas a activar.'
    )
  })

  it('con productos activos avisa que la tienda en línea deja de mostrarlos', () => {
    expect(avisoDesactivarCategoria('Abarrotes', 15)).toMatch(
      /^«Abarrotes» tiene 15 productos activos\. Si la desactivas, la tienda en línea deja de mostrarlos/
    )
    expect(avisoDesactivarCategoria('Abarrotes', 1)).toMatch(
      /^«Abarrotes» tiene 1 producto activo\./
    )
  })
})
