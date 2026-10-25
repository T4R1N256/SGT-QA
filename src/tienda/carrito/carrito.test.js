import { describe, expect, it } from 'vitest'
import { leerGuardado, reducer, totalPiezas, validarCantidad } from './carrito'

const arroz = { id: 1, precio: 32.5 }
const azucar = { id: 5, precio: 34 }

describe('reducer del carrito', () => {
  it('agrega un producto con su cantidad y su precio', () => {
    expect(reducer([], { tipo: 'agregar', producto: arroz, cantidad: 3 })).toEqual([
      { id: 1, cantidad: 3, precio: 32.5 },
    ])
  })

  it('agregar un producto que ya está suma la cantidad', () => {
    const lineas = [{ id: 1, cantidad: 3, precio: 32.5 }]
    expect(
      reducer(lineas, { tipo: 'agregar', producto: { ...arroz, precio: 33 }, cantidad: 2 })
    ).toEqual([{ id: 1, cantidad: 5, precio: 33 }])
  })

  it('cambia la cantidad, quita y vacía', () => {
    let lineas = reducer([], { tipo: 'agregar', producto: arroz, cantidad: 3 })
    lineas = reducer(lineas, { tipo: 'agregar', producto: azucar, cantidad: 1 })
    lineas = reducer(lineas, { tipo: 'cambiar', id: 1, cantidad: 5 })
    expect(lineas).toEqual([
      { id: 1, cantidad: 5, precio: 32.5 },
      { id: 5, cantidad: 1, precio: 34 },
    ])
    expect(reducer(lineas, { tipo: 'quitar', id: 1 })).toEqual([{ id: 5, cantidad: 1, precio: 34 }])
    expect(reducer(lineas, { tipo: 'vaciar' })).toEqual([])
  })

  it('sincroniza con el servidor y regresa la misma lista si no cambió nada', () => {
    const lineas = [
      { id: 1, cantidad: 5, precio: 30 },
      { id: 5, cantidad: 1, precio: 34 },
    ]
    const servidor = [{ id: 1, nombre: 'Arroz', cantidad: 3, precio: 32.5, importe: 97.5 }]

    const sincronizadas = reducer(lineas, { tipo: 'sincronizar', lineas: servidor })
    expect(sincronizadas).toEqual([{ id: 1, cantidad: 3, precio: 32.5 }])
    expect(reducer(sincronizadas, { tipo: 'sincronizar', lineas: servidor })).toBe(sincronizadas)
  })
})

describe('leerGuardado', () => {
  it('lee el carrito guardado', () => {
    expect(leerGuardado('[{"id":1,"cantidad":2,"precio":32.5}]')).toEqual([
      { id: 1, cantidad: 2, precio: 32.5 },
    ])
  })

  it('sin carrito o con algo que no es un carrito regresa uno vacío', () => {
    expect(leerGuardado(null)).toEqual([])
    expect(leerGuardado('no es json')).toEqual([])
    expect(leerGuardado('{"id":1}')).toEqual([])
  })

  it('descarta las líneas inválidas o repetidas', () => {
    const guardado = JSON.stringify([
      { id: 1, cantidad: 2 },
      { id: 1, cantidad: 9 },
      { id: 0, cantidad: 1 },
      { id: 2, cantidad: 0 },
      { id: 3, cantidad: 1.5 },
      { id: '4', cantidad: 1 },
      null,
      { id: 6, cantidad: 1, precio: 'x' },
    ])
    expect(leerGuardado(guardado)).toEqual([
      { id: 1, cantidad: 2, precio: null },
      { id: 6, cantidad: 1, precio: null },
    ])
  })
})

describe('totalPiezas', () => {
  it('suma las cantidades', () => {
    expect(totalPiezas([])).toBe(0)
    expect(totalPiezas([{ cantidad: 3 }, { cantidad: 2 }])).toBe(5)
  })
})

describe('validarCantidad', () => {
  const diez = { existencia: 10 }

  it('acepta enteros desde 1 hasta la existencia', () => {
    expect(validarCantidad('3', diez)).toEqual({ cantidad: 3 })
    expect(validarCantidad(' 10 ', diez)).toEqual({ cantidad: 10 })
  })

  it('rechaza cero, negativos, decimales y texto (P2)', () => {
    expect(validarCantidad('0', diez).mensaje).toBe('La cantidad debe ser al menos 1')
    expect(validarCantidad('-2', diez).mensaje).toBe('La cantidad no puede ser negativa')
    expect(validarCantidad('2.5', diez).mensaje).toBe('La cantidad debe ser un número entero')
    expect(validarCantidad('tres', diez).mensaje).toBe('La cantidad debe ser un número')
    expect(validarCantidad('', diez).mensaje).toBe('Escribe la cantidad')
  })

  it('no acepta más que la existencia (P1)', () => {
    expect(validarCantidad('11', diez).mensaje).toBe('Solo hay 10 disponibles')
    expect(validarCantidad('99999999999999999999', diez).mensaje).toBe('Solo hay 10 disponibles')
  })

  it('al agregar más toma en cuenta lo que ya está en el carrito', () => {
    expect(validarCantidad('3', { existencia: 10, enCarrito: 7 })).toEqual({ cantidad: 3 })
    expect(validarCantidad('4', { existencia: 10, enCarrito: 7 }).mensaje).toBe(
      'Solo hay 10 disponibles y ya tienes 7 en tu carrito'
    )
    expect(validarCantidad('1', { existencia: 10, enCarrito: 10 }).mensaje).toBe(
      'Ya tienes en tu carrito las 10 disponibles'
    )
  })
})
