import { describe, expect, it } from 'vitest'
import { cantidadSugerida, filtrarProductos, necesitanSurtido, normalizar } from './productos'

const productos = [
  { ID_Producto: 1, Nombre_Producto: 'Azúcar estándar 1 kg', Nombre_Categoria: 'Abarrotes', Estado: 'Disponible' },
  { ID_Producto: 2, Nombre_Producto: 'Leche entera 1 L', Nombre_Categoria: 'Lácteos y huevo', Estado: 'Stock bajo' },
  { ID_Producto: 3, Nombre_Producto: 'Yogur bebible', Nombre_Categoria: 'Lácteos y huevo', Estado: 'Agotado' },
  { ID_Producto: 4, Nombre_Producto: 'Refresco de cola', Nombre_Categoria: 'Bebidas', Estado: 'Disponible' },
]

const ids = (lista) => lista.map((p) => p.ID_Producto)

describe('normalizar', () => {
  it('quita acentos y mayúsculas', () => {
    expect(normalizar('  Azúcar ESTÁNDAR ')).toBe('azucar estandar')
  })
})

describe('filtrarProductos', () => {
  it('busca por nombre sin importar acentos', () => {
    expect(ids(filtrarProductos(productos, 'azucar'))).toEqual([1])
  })

  it('busca por categoría', () => {
    expect(ids(filtrarProductos(productos, 'bebidas'))).toEqual([4])
    expect(ids(filtrarProductos(productos, 'lacteos'))).toEqual([2])
  })

  it('no muestra productos agotados', () => {
    expect(ids(filtrarProductos(productos, 'yogur'))).toEqual([])
  })

  it('sin texto no hay resultados', () => {
    expect(filtrarProductos(productos, '   ')).toEqual([])
  })

  it('respeta el límite de resultados', () => {
    expect(filtrarProductos(productos, 'e', 2)).toHaveLength(2)
  })
})

describe('surtido', () => {
  it('filtrarProductos puede incluir agotados', () => {
    expect(ids(filtrarProductos(productos, 'yogur', 8, { incluirAgotados: true }))).toEqual([3])
  })

  it('necesitanSurtido regresa los de stock bajo y agotados', () => {
    expect(ids(necesitanSurtido(productos))).toEqual([2, 3])
  })

  it('cantidadSugerida llega al doble del mínimo', () => {
    expect(cantidadSugerida({ Cantidad: 8, Cantidad_Minima: 10 })).toBe(12)
    expect(cantidadSugerida({ Cantidad: 0, Cantidad_Minima: 15 })).toBe(30)
  })

  it('cantidadSugerida es al menos 1', () => {
    expect(cantidadSugerida({ Cantidad: 50, Cantidad_Minima: 10 })).toBe(1)
    expect(cantidadSugerida({ Cantidad: 0, Cantidad_Minima: 0 })).toBe(1)
  })
})
