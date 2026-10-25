import { describe, expect, it } from 'vitest'
import { reporteHTML } from './imprimir'

const reporte = {
  tipo: 'stock',
  titulo: 'Reporte de stock',
  periodo: 'Existencias al 25/10/2026 14:03',
  generado: '25/10/2026 14:03',
  archivo: 'reporte-stock-2026-10-25',
  indicadores: [
    { etiqueta: 'Productos activos', valor: '2' },
    { etiqueta: 'Valor a costo', valor: '$253.00', nota: 'No incluye 1 producto sin costo' },
  ],
  secciones: [
    {
      titulo: 'Existencias',
      columnas: [
        { titulo: 'Producto', tipo: 'texto' },
        { titulo: 'Existencia', tipo: 'numero' },
      ],
      filas: [
        ['<script>alert(1)</script>', '3'],
        ['Refresco de naranja 600 ml', '22'],
      ],
      vacio: 'No hay productos activos',
      nota: null,
    },
    {
      titulo: 'Productos por surtir',
      columnas: [{ titulo: 'Producto', tipo: 'texto' }],
      filas: [],
      vacio: 'Ningún producto está en stock bajo ni agotado',
      nota: null,
    },
  ],
  notas: ['Los costos son el último costo de compra registrado en Surtir.'],
}

const tienda = { Nombre_Tienda: 'Abarrotes "La bamba" & Hijos' }

describe('reporteHTML', () => {
  const html = reporteHTML(reporte, tienda)

  it('es un documento independiente y sin scripts', () => {
    expect(html).toMatch(/^<!doctype html>/)
    expect(html).toContain("default-src 'none'")
    expect(html).toContain('<title>Reporte de stock</title>')
  })

  it('escapa los textos que vienen de la base de datos', () => {
    expect(html).not.toContain('<script>alert(1)</script>')
    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
    expect(html).toContain('Abarrotes &quot;La bamba&quot; &amp; Hijos')
  })

  it('incluye encabezado, indicadores, tablas y notas', () => {
    expect(html).toContain('Existencias al 25/10/2026 14:03')
    expect(html).toContain('Generado el 25/10/2026 14:03')
    expect(html).toContain('No incluye 1 producto sin costo')
    expect(html).toContain('<th class="numero">Existencia</th>')
    expect(html).toContain('<td class="numero">22</td>')
    expect(html).toContain('Los costos son el último costo de compra registrado en Surtir.')
  })

  it('una sección vacía muestra su mensaje en lugar de la tabla', () => {
    expect(html).toContain('<p class="vacio">Ningún producto está en stock bajo ni agotado</p>')
  })
})
