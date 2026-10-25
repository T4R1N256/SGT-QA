import { describe, expect, it } from 'vitest'
import { capitalize } from './format'

describe('capitalize', () => {
  it('pone en mayúscula solo la primera letra', () => {
    expect(capitalize('no autorizado')).toBe('No autorizado')
    expect(capitalize('CUADRADA')).toBe('Cuadrada')
  })

  it('regresa cadena vacía para valores que no son texto', () => {
    expect(capitalize('')).toBe('')
    expect(capitalize(null)).toBe('')
    expect(capitalize(undefined)).toBe('')
  })
})
