import { describe, expect, it } from 'vitest'
import { capitalize, cuenta, formatoMoneda, formatoNumero } from './format'

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

describe('formatoNumero y cuenta', () => {
  it('números con separador de miles', () => {
    expect(formatoNumero(1250)).toBe('1,250')
    expect(formatoNumero(null)).toBe('0')
  })

  it('singular y plural según la cantidad', () => {
    expect(cuenta(1, 'producto')).toBe('1 producto')
    expect(cuenta(0, 'producto')).toBe('0 productos')
    expect(cuenta(1200, 'venta cobrada', 'ventas cobradas')).toBe('1,200 ventas cobradas')
  })
})

describe('formatoMoneda', () => {
  it('moneda en pesos', () => {
    expect(formatoMoneda('1234.5')).toBe('$1,234.50')
    expect(formatoMoneda(null)).toBe('$0.00')
  })
})
