import { describe, expect, it } from 'vitest'
import { fechaLocal, formatoFecha, periodo, rangoDias, textoPeriodo } from './fechas'

describe('fechaLocal', () => {
  it('usa la fecha local aunque en UTC ya sea otro día', () => {
    // 25 de octubre a las 23:30 hora local
    expect(fechaLocal(new Date(2026, 9, 25, 23, 30))).toBe('2026-10-25')
  })

  it('rellena mes y día con cero', () => {
    expect(fechaLocal(new Date(2026, 0, 5))).toBe('2026-01-05')
  })
})

describe('rangoDias', () => {
  it('regresa desde hace N días hasta hoy', () => {
    expect(rangoDias(30, new Date(2026, 9, 25))).toEqual({ desde: '2026-09-25', hasta: '2026-10-25' })
  })

  it('cruza el cambio de año', () => {
    expect(rangoDias(7, new Date(2026, 0, 3))).toEqual({ desde: '2025-12-27', hasta: '2026-01-03' })
  })
})

describe('periodo', () => {
  it('día: solo esa fecha', () => {
    expect(periodo('dia', '2026-10-25')).toEqual({ desde: '2026-10-25', hasta: '2026-10-25' })
  })

  it('semana: de lunes a domingo', () => {
    // 25 de octubre de 2026 es domingo: su semana empieza el lunes 19
    expect(periodo('semana', '2026-10-25')).toEqual({ desde: '2026-10-19', hasta: '2026-10-25' })
    expect(periodo('semana', '2026-10-19')).toEqual({ desde: '2026-10-19', hasta: '2026-10-25' })
  })

  it('semana que cruza de mes y de año', () => {
    expect(periodo('semana', '2026-01-01')).toEqual({ desde: '2025-12-29', hasta: '2026-01-04' })
  })

  it('mes: del día 1 al último, con febrero bisiesto', () => {
    expect(periodo('mes', '2026-10-25')).toEqual({ desde: '2026-10-01', hasta: '2026-10-31' })
    expect(periodo('mes', '2028-02-10')).toEqual({ desde: '2028-02-01', hasta: '2028-02-29' })
  })
})

describe('textoPeriodo', () => {
  it('un día o un rango en formato DD/MM/AAAA', () => {
    expect(formatoFecha('2026-10-05')).toBe('05/10/2026')
    expect(textoPeriodo('2026-10-25', '2026-10-25')).toBe('25/10/2026')
    expect(textoPeriodo('2026-10-01', '2026-10-31')).toBe('Del 01/10/2026 al 31/10/2026')
  })
})
