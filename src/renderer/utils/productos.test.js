import { describe, expect, it } from 'vitest'
import { filtrarProductos, normalizar } from './productos'

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
