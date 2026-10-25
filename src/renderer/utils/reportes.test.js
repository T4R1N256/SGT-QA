import { describe, expect, it } from 'vitest'
import { construirReporte, formatoPorcentaje } from './reportes'

// Respuestas del backend con la forma de getSalesReport, getRestockReport, getStockReport y getSummaryReport
const ventas = {
  desde: '2026-10-01',
  hasta: '2026-10-31',
  generado: '2026-10-25 14:03:59',
  resumen: { ventas: 3, total: 128.4, articulos: 7, promedio: 42.8, canceladas: 1 },
  formas_pago: [
    { Forma_Pago: 'efectivo', Ventas: 2, Total: 65.5 },
    { Forma_Pago: 'transferencia', Ventas: 1, Total: 62.9 },
  ],
  dias: [{ Fecha: '2026-10-25', Ventas: 3, Total: 128.4 }],
  productos: [{ ID_Producto: 8, Nombre_Producto: 'Pasta para sopa 200 g', Unidad_Medida: 'pieza', Unidades: 3, Importe: 33 }],
  cajeros: [{ Cajero: 'Empleado Demo', Ventas: 3, Total: 128.4 }],
}

const surtidos = {
  desde: '2026-10-01',
  hasta: '2026-10-31',
  generado: '2026-10-25 14:03:59',
  resumen: { surtidos: 1, total: 435.3, unidades: 42, productos: 2 },
  proveedores: [{ Proveedor: 'Distribuidora La Central', Surtidos: 1, Unidades: 42, Total: 435.3 }],
  productos: [{ ID_Producto: 6, Nombre_Producto: 'Harina de maíz 1 kg', Unidades: 12, Total: 220.8, Costo_Promedio: 18.4 }],
  surtidos: [
    {
      ID_Surtir: 1,
      Fecha: '2026-10-25',
      Hora: '09:15:00',
      Monto: 435.3,
      Nombre_Proveedor: 'Distribuidora La Central',
      Registro: 'Usuario Administrador',
      Productos: 2,
      Unidades: 42,
    },
  ],
}

const stock = {
  generado: '2026-10-25 14:03:59',
  resumen: {
    productos: 3,
    unidades: 1250,
    valor_venta: 12345.6,
    valor_costo: 253,
    sin_costo: 2,
    stock_bajo: 1,
    agotados: 1,
  },
  categorias: [{ Categoria: 'Bebidas', Productos: 3, Unidades: 1250, Valor_Venta: 12345.6, Valor_Costo: 253 }],
  por_surtir: [
    {
      ID_Producto: 14,
      Nombre_Producto: 'Jugo de manzana 1 L',
      Proveedor: 'Refrescos del Norte',
      Cantidad: 0,
      Cantidad_Minima: 6,
      Estado: 'Agotado',
      Sugerido: 12,
      Costo: null,
      Costo_Estimado: null,
    },
    {
      ID_Producto: 12,
      Nombre_Producto: 'Refresco de naranja 600 ml',
      Proveedor: 'Refrescos del Norte',
      Cantidad: 22,
      Cantidad_Minima: 24,
      Estado: 'Stock bajo',
      Sugerido: 26,
      Costo: 11.5,
      Costo_Estimado: 299,
    },
  ],
  productos: [],
}

const resumen = {
  desde: '2026-10-25',
  hasta: '2026-10-25',
  generado: '2026-10-25 14:03:59',
  ventas: { ventas: 3, total: 128.4 },
  compras: { surtidos: 2, total: 746.3 },
  flujo: -617.9,
  utilidad: { estimada: 32.05, importe_con_costo: 95.9, margen: 33.4, cobertura: 74.7 },
  cajas: { cortes: 1, descuadradas: 1, diferencia: -0.5 },
  dias: [{ Fecha: '2026-10-25', Ventas: 128.4, Compras: 746.3, Flujo: -617.9 }],
}

const indicador = (reporte, etiqueta) => reporte.indicadores.find((i) => i.etiqueta === etiqueta)
const seccion = (reporte, titulo) => reporte.secciones.find((s) => s.titulo === titulo)

describe('formatoPorcentaje', () => {
  it('un decimal, o guion si no hay base', () => {
    expect(formatoPorcentaje(33.42)).toBe('33.4 %')
    expect(formatoPorcentaje(null)).toBe('—')
  })
})

describe('construirReporte', () => {
  it('reporte de ventas: periodo, indicadores y porcentaje por forma de pago', () => {
    const r = construirReporte('ventas', ventas)

    expect(r.titulo).toBe('Reporte de ventas')
    expect(r.periodo).toBe('Del 01/10/2026 al 31/10/2026')
    expect(r.generado).toBe('25/10/2026 14:03')
    expect(r.archivo).toBe('reporte-ventas-2026-10-01_2026-10-31')
    expect(indicador(r, 'Total vendido').valor).toBe('$128.40')
    expect(indicador(r, 'Ticket promedio').valor).toBe('$42.80')
    expect(seccion(r, 'Por forma de pago').filas).toEqual([
      ['Efectivo', '2', '$65.50', '51.0 %'],
      ['Transferencia', '1', '$62.90', '49.0 %'],
    ])
    expect(seccion(r, 'Por día').filas).toEqual([['25/10/2026', '3', '$128.40']])
  })

  it('las secciones sin filas traen el mensaje de vacío', () => {
    const r = construirReporte('ventas', { ...ventas, productos: [] })

    expect(seccion(r, 'Productos más vendidos').filas).toEqual([])
    expect(seccion(r, 'Productos más vendidos').vacio).toBe('Sin movimientos en este periodo')
  })

  it('reporte de surtidos con el costo promedio y la lista de surtidos', () => {
    const r = construirReporte('surtidos', surtidos)

    expect(r.titulo).toBe('Reporte de surtidos y compras')
    expect(indicador(r, 'Total comprado').valor).toBe('$435.30')
    expect(seccion(r, 'Productos surtidos').filas).toEqual([['Harina de maíz 1 kg', '12', '$18.40', '$220.80']])
    expect(seccion(r, 'Surtidos del periodo').filas[0]).toEqual([
      '25/10/2026 09:15',
      'Distribuidora La Central',
      'Usuario Administrador',
      '2',
      '42',
      '$435.30',
    ])
  })

  it('reporte de stock: sin periodo, con los productos sin costo y el costo estimado para surtir', () => {
    const r = construirReporte('stock', stock)

    expect(r.periodo).toBe('Existencias al 25/10/2026 14:03')
    expect(r.archivo).toBe('reporte-stock-2026-10-25')
    expect(indicador(r, 'Valor a precio de venta').valor).toBe('$12,345.60')
    expect(indicador(r, 'Valor a costo').nota).toBe('No incluye 2 productos sin costo')

    const porSurtir = seccion(r, 'Productos por surtir')
    expect(porSurtir.filas[0]).toEqual(['Jugo de manzana 1 L', 'Refrescos del Norte', '0', '6', 'Agotado', '12', '—'])
    expect(porSurtir.columnas[4]).toEqual({ titulo: 'Estado', tipo: 'estado' })
    expect(porSurtir.nota).toBe('Costo estimado para surtir lo sugerido: $299.00 (sin contar 1 producto sin costo)')
    expect(seccion(r, 'Existencias').vacio).toBe('No hay productos activos')
  })

  it('stock sin costos registrados: sin estimado de compra y categorías con valor a costo desconocido', () => {
    const sinCostos = {
      ...stock,
      categorias: [{ ...stock.categorias[0], Valor_Costo: null }],
      por_surtir: [stock.por_surtir[0]],
    }
    const r = construirReporte('stock', sinCostos)

    expect(seccion(r, 'Productos por surtir').nota).toBe(
      'Ninguno tiene costo de compra registrado: se conoce al surtirlo por primera vez.'
    )
    expect(seccion(r, 'Por categoría').filas[0][4]).toBe('—')
  })

  it('resumen: flujo negativo, margen y cortes de caja', () => {
    const r = construirReporte('resumen', resumen)

    expect(r.periodo).toBe('25/10/2026')
    expect(indicador(r, 'Ventas menos compras').valor).toBe('-$617.90')
    expect(indicador(r, 'Utilidad estimada').nota).toBe('Margen 33.4 % · cubre 74.7 % de lo vendido')
    expect(indicador(r, 'Cortes de caja').nota).toBe('1 descuadrado · diferencia -$0.50')
  })

  it('resumen sin costos registrados', () => {
    const r = construirReporte('resumen', {
      ...resumen,
      utilidad: { estimada: 0, importe_con_costo: 0, margen: null, cobertura: null },
    })

    expect(indicador(r, 'Utilidad estimada').nota).toBe('Sin costos de compra registrados')
  })

  it('tipo desconocido', () => {
    expect(() => construirReporte('otro', ventas)).toThrow('Tipo de reporte desconocido')
  })
})
