import { describe, expect, it } from 'vitest'
import { fechaLocal, rangoDias } from './fechas'

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
