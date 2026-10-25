import { describe, expect, it } from 'vitest'
import { limpiarTexto } from './texto'

describe('limpiarTexto', () => {
  it('quita los espacios al inicio y al final', () => {
    expect(limpiarTexto('  Ana  ')).toBe('Ana')
  })

  it('conserva el texto tal cual, con &, <, > y comillas', () => {
    expect(limpiarTexto('Pan & Cía <grande>')).toBe('Pan & Cía <grande>')
    expect(limpiarTexto('Clave<2025')).toBe('Clave<2025')
    expect(limpiarTexto('a<b')).toBe('a<b')
    expect(limpiarTexto('<script>x</script>')).toBe('<script>x</script>')
    expect(limpiarTexto('Comillas "dobles" y \'simples\'')).toBe('Comillas "dobles" y \'simples\'')
  })

  it('sin valor regresa texto vacío', () => {
    expect(limpiarTexto(undefined)).toBe('')
    expect(limpiarTexto(null)).toBe('')
  })
})
