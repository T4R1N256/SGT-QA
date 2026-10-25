import { describe, expect, it } from 'vitest'
import { cambiarFiltros, enteroPositivo, leerFiltros, paginasVisibles } from './filtros'

const filtros = (consulta) => leerFiltros(new URLSearchParams(consulta))

describe('leerFiltros', () => {
  it('sin parámetros usa los valores por defecto', () => {
    expect(filtros('')).toEqual({
      q: '',
      categoria: null,
      disponibles: false,
      orden: 'nombre',
      pagina: 1,
    })
  })

  it('lee todos los filtros de la dirección', () => {
    expect(
      filtros('q=%20az%C3%BAcar%20&categoria=3&disponibles=1&orden=precio_desc&pagina=2')
    ).toEqual({
      q: 'azúcar',
      categoria: 3,
      disponibles: true,
      orden: 'precio_desc',
      pagina: 2,
    })
  })

  it('ignora los valores inválidos escritos a mano', () => {
    expect(filtros('categoria=abc&disponibles=si&orden=costo&pagina=0')).toEqual(filtros(''))
    expect(filtros('pagina=-2').pagina).toBe(1)
    expect(filtros('pagina=1.5').pagina).toBe(1)
    expect(filtros('pagina=99999999999').pagina).toBe(1)
  })

  it('recorta la búsqueda a 100 caracteres, como el API', () => {
    expect(filtros(`q=${'a'.repeat(150)}`).q).toHaveLength(100)
  })
})

describe('cambiarFiltros', () => {
  const params = new URLSearchParams('q=arroz&orden=precio_asc&pagina=3')

  it('cambiar un filtro regresa a la página 1', () => {
    expect(cambiarFiltros(params, { disponibles: true }).toString()).toBe(
      'q=arroz&orden=precio_asc&disponibles=1'
    )
  })

  it('cambiar de página conserva los filtros', () => {
    expect(cambiarFiltros(params, { pagina: 4 }).toString()).toBe(
      'q=arroz&orden=precio_asc&pagina=4'
    )
  })

  it('los valores por defecto no se escriben en la dirección', () => {
    expect(cambiarFiltros(params, { orden: 'nombre', pagina: 1 }).toString()).toBe('q=arroz')
    expect(cambiarFiltros(params, { categoria: null, disponibles: false }).toString()).toBe(
      'q=arroz&orden=precio_asc'
    )
  })

  it('no modifica los parámetros originales', () => {
    cambiarFiltros(params, { q: 'frijol' })
    expect(params.toString()).toBe('q=arroz&orden=precio_asc&pagina=3')
  })
})

describe('enteroPositivo', () => {
  it('acepta solo enteros positivos escritos sin adornos', () => {
    expect(enteroPositivo('12')).toBe(12)
    expect(enteroPositivo('012')).toBeNull()
    expect(enteroPositivo('12abc')).toBeNull()
    expect(enteroPositivo(undefined)).toBeNull()
  })
})

describe('paginasVisibles', () => {
  it('muestra todas cuando son pocas', () => {
    expect(paginasVisibles(1, 1)).toEqual([1])
    expect(paginasVisibles(2, 4)).toEqual([1, 2, 3, 4])
  })

  it('salta páginas con puntos suspensivos', () => {
    expect(paginasVisibles(7, 15)).toEqual([1, '…', 6, 7, 8, '…', 15])
    expect(paginasVisibles(1, 15)).toEqual([1, 2, '…', 15])
    expect(paginasVisibles(15, 15)).toEqual([1, '…', 14, 15])
  })

  it('no pone puntos suspensivos para saltar una sola página', () => {
    expect(paginasVisibles(4, 15)).toEqual([1, 2, 3, 4, 5, '…', 15])
  })

  it('sin páginas no muestra ninguna', () => {
    expect(paginasVisibles(1, 0)).toEqual([])
  })
})
