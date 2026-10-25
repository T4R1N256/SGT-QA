import { describe, expect, it } from 'vitest'
import { aCentavos, calcularCambio, desglosarIVA, totalCarrito } from './money'

describe('aCentavos', () => {
  it('convierte sin errores de punto flotante', () => {
    expect(aCentavos(45.9)).toBe(4590)
    expect(aCentavos('10.10')).toBe(1010)
    expect(aCentavos(0.1 + 0.2)).toBe(30)
  })

  it('trata valores inválidos como cero', () => {
    expect(aCentavos('')).toBe(0)
    expect(aCentavos('abc')).toBe(0)
    expect(aCentavos(undefined)).toBe(0)
  })
})

describe('totalCarrito', () => {
  it('suma precio por cantidad con centavos exactos', () => {
    const cart = [
      { Precio: '45.90', cantidad: 1 },
      { Precio: '32.50', cantidad: 3 },
      { Precio: '0.10', cantidad: 3 },
    ]
    expect(totalCarrito(cart)).toBe(143.7)
  })

  it('ignora cantidades vacías mientras se escriben', () => {
    expect(totalCarrito([{ Precio: '19.00', cantidad: '' }])).toBe(0)
  })
})

describe('calcularCambio', () => {
  it('regresa el cambio exacto', () => {
    expect(calcularCambio('200', 143.7)).toBe(56.3)
  })

  it('es negativo si el pago no alcanza', () => {
    expect(calcularCambio('100', 143.7)).toBe(-43.7)
  })
})

describe('desglosarIVA', () => {
  it('separa el IVA del 16 % de un precio que ya lo incluye', () => {
    expect(desglosarIVA(116, 16)).toEqual({ subtotal: 100, iva: 16 })
    expect(desglosarIVA(34, 16)).toEqual({ subtotal: 29.31, iva: 4.69 })
  })

  it('el subtotal más el IVA da siempre el total exacto', () => {
    for (const total of [0.01, 0.99, 9.9, 45.9, 1234.56, 99999.99]) {
      const { subtotal, iva } = desglosarIVA(total, 16)
      expect(aCentavos(subtotal) + aCentavos(iva)).toBe(aCentavos(total))
    }
  })

  it('con tasa 0 todo es subtotal', () => {
    expect(desglosarIVA(50, 0)).toEqual({ subtotal: 50, iva: 0 })
  })
})
