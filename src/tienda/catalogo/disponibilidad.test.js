import { describe, expect, it } from 'vitest'
import { DISPONIBILIDAD, cantidadConUnidad, textoExistencia } from './disponibilidad'

describe('disponibilidad', () => {
  it('tiene etiqueta para cada valor del API', () => {
    expect(Object.keys(DISPONIBILIDAD)).toEqual(['disponible', 'pocas_piezas', 'agotado'])
    expect(DISPONIBILIDAD.pocas_piezas.etiqueta).toBe('Pocas piezas')
  })

  it('escribe la cantidad con su unidad', () => {
    expect(cantidadConUnidad(1, 'pieza')).toBe('1 pieza')
    expect(cantidadConUnidad(3, 'pieza')).toBe('3 piezas')
    expect(cantidadConUnidad(2, 'kg')).toBe('2 kg')
    expect(cantidadConUnidad(5, 'litro')).toBe('5 litros')
  })

  it('describe la existencia según la disponibilidad', () => {
    expect(textoExistencia({ disponibilidad: 'disponible', existencia: 50, unidad: 'pieza' })).toBe(
      '50 piezas disponibles'
    )
    expect(
      textoExistencia({ disponibilidad: 'pocas_piezas', existencia: 3, unidad: 'pieza' })
    ).toBe('Quedan solo 3 piezas')
    expect(textoExistencia({ disponibilidad: 'pocas_piezas', existencia: 1, unidad: 'kg' })).toBe(
      'Queda solo 1 kg'
    )
    expect(textoExistencia({ disponibilidad: 'agotado', existencia: 0, unidad: 'pieza' })).toBe(
      'Por ahora no hay existencias'
    )
  })
})
