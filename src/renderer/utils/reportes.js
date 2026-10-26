/**
 * @file Contenido de los reportes (RF11): a partir de lo que regresa el backend arma el título, el periodo,
 * los indicadores y las tablas ya con formato. Lo usan la página Reportes y el PDF, así se ven igual.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { formatoFecha, textoPeriodo } from './fechas'
import { capitalize, cuenta, formatoMoneda, formatoNumero } from '../../compartido/format'

export const TIPOS_REPORTE = [
  { id: 'ventas', label: 'Ventas', titulo: 'Reporte de ventas' },
  { id: 'surtidos', label: 'Surtidos y compras', titulo: 'Reporte de surtidos y compras' },
  { id: 'stock', label: 'Stock', titulo: 'Reporte de stock', sinPeriodo: true },
  { id: 'resumen', label: 'Resumen', titulo: 'Resumen del periodo' },
]

/**
 * Porcentaje con un decimal: 12.345 → "12.3 %".
 *
 * @param {number|null} valor Porcentaje
 * @returns {string} El porcentaje con formato, o "—" si no se pudo calcular
 */
export function formatoPorcentaje(valor) {
  return valor == null ? '—' : `${Number(valor).toFixed(1)} %`
}

const dineroOGuion = (monto) => (monto == null ? '—' : formatoMoneda(monto))

// Columna de una tabla: 'texto', 'numero' (alineado a la derecha) o 'estado' (Disponible, Stock bajo, Agotado)
const columna = (titulo, tipo = 'texto') => ({ titulo, tipo })

/**
 * Fecha y hora en que se generó el reporte: 2026-10-25 14:03:59 → 25/10/2026 14:03.
 *
 * @param {string} generado Fecha y hora del backend
 * @returns {string} La fecha y la hora para mostrar
 */
function formatoGenerado(generado) {
  const [fecha, hora] = String(generado ?? '').split(' ')
  return `${formatoFecha(fecha)} ${String(hora ?? '').slice(0, 5)}`.trim()
}

/**
 * Contenido del reporte de ventas: indicadores, formas de pago, ventas por día, productos más vendidos y cajeros.
 *
 * @param {Object} d Respuesta de getSalesReport
 * @returns {{indicadores: Array, secciones: Array, notas: Array<string>}} El contenido con formato
 */
function reporteVentas(d) {
  const r = d.resumen
  const parteDelTotal = (total) => formatoPorcentaje(r.total > 0 ? (total / r.total) * 100 : null)

  return {
    indicadores: [
      { etiqueta: 'Total vendido', valor: formatoMoneda(r.total) },
      { etiqueta: 'Ventas cobradas', valor: formatoNumero(r.ventas) },
      { etiqueta: 'Ticket promedio', valor: formatoMoneda(r.promedio) },
      { etiqueta: 'Artículos vendidos', valor: formatoNumero(r.articulos) },
      { etiqueta: 'Ventas canceladas', valor: formatoNumero(r.canceladas), nota: 'No suman al total' },
    ],
    secciones: [
      {
        titulo: 'Por forma de pago',
        columnas: [columna('Forma de pago'), columna('Ventas', 'numero'), columna('Total', 'numero'), columna('% del total', 'numero')],
        filas: d.formas_pago.map((f) => [
          capitalize(f.Forma_Pago ?? 'sin registrar'),
          formatoNumero(f.Ventas),
          formatoMoneda(f.Total),
          parteDelTotal(f.Total),
        ]),
      },
      {
        titulo: 'Por cajero',
        columnas: [columna('Cajero'), columna('Ventas', 'numero'), columna('Total', 'numero')],
        filas: d.cajeros.map((c) => [c.Cajero, formatoNumero(c.Ventas), formatoMoneda(c.Total)]),
      },
      {
        titulo: 'Por día',
        columnas: [columna('Fecha'), columna('Ventas', 'numero'), columna('Total', 'numero')],
        filas: d.dias.map((dia) => [formatoFecha(dia.Fecha), formatoNumero(dia.Ventas), formatoMoneda(dia.Total)]),
      },
      {
        titulo: 'Productos más vendidos',
        columnas: [columna('Producto'), columna('Unidades', 'numero'), columna('Importe', 'numero')],
        filas: d.productos.map((p) => [p.Nombre_Producto, formatoNumero(p.Unidades), formatoMoneda(p.Importe)]),
      },
    ],
    notas: ['Solo cuentan las ventas cobradas. Las canceladas no se cobraron y no suman al total.'],
  }
}

/**
 * Contenido del reporte de surtidos y compras: indicadores, proveedores, productos surtidos y la lista de surtidos.
 *
 * @param {Object} d Respuesta de getRestockReport
 * @returns {{indicadores: Array, secciones: Array, notas: Array<string>}} El contenido con formato
 */
function reporteSurtidos(d) {
  const r = d.resumen

  return {
    indicadores: [
      { etiqueta: 'Total comprado', valor: formatoMoneda(r.total) },
      { etiqueta: 'Surtidos', valor: formatoNumero(r.surtidos) },
      { etiqueta: 'Unidades surtidas', valor: formatoNumero(r.unidades) },
      { etiqueta: 'Productos distintos', valor: formatoNumero(r.productos) },
    ],
    secciones: [
      {
        titulo: 'Compras por proveedor',
        columnas: [columna('Proveedor'), columna('Surtidos', 'numero'), columna('Unidades', 'numero'), columna('Total', 'numero')],
        filas: d.proveedores.map((p) => [p.Proveedor, formatoNumero(p.Surtidos), formatoNumero(p.Unidades), formatoMoneda(p.Total)]),
      },
      {
        titulo: 'Productos surtidos',
        columnas: [columna('Producto'), columna('Unidades', 'numero'), columna('Costo promedio', 'numero'), columna('Total', 'numero')],
        filas: d.productos.map((p) => [
          p.Nombre_Producto,
          formatoNumero(p.Unidades),
          formatoMoneda(p.Costo_Promedio),
          formatoMoneda(p.Total),
        ]),
      },
      {
        titulo: 'Surtidos del periodo',
        columnas: [
          columna('Fecha'),
          columna('Proveedor'),
          columna('Registró'),
          columna('Productos', 'numero'),
          columna('Unidades', 'numero'),
          columna('Total', 'numero'),
        ],
        filas: d.surtidos.map((s) => [
          `${formatoFecha(s.Fecha)} ${String(s.Hora).slice(0, 5)}`,
          s.Nombre_Proveedor ?? 'Sin proveedor',
          s.Registro ?? '—',
          formatoNumero(s.Productos),
          formatoNumero(s.Unidades),
          formatoMoneda(s.Monto),
        ]),
      },
    ],
    notas: ['El total de cada surtido es lo que se pagó al proveedor: cantidad por costo de compra.'],
  }
}

/**
 * Contenido del reporte de stock: valor del inventario, productos por surtir (con lo que costaría surtir
 * lo sugerido), categorías y existencias.
 *
 * @param {Object} d Respuesta de getStockReport
 * @returns {{indicadores: Array, secciones: Array, notas: Array<string>}} El contenido con formato
 */
function reporteStock(d) {
  const r = d.resumen
  // Lo que costaría surtir lo sugerido, con el último costo de compra (en centavos para no acumular errores)
  const conCosto = d.por_surtir.filter((p) => p.Costo_Estimado != null)
  const estimado = conCosto.reduce((suma, p) => suma + Math.round(p.Costo_Estimado * 100), 0) / 100
  const sinCosto = d.por_surtir.length - conCosto.length

  return {
    indicadores: [
      { etiqueta: 'Productos activos', valor: formatoNumero(r.productos) },
      { etiqueta: 'Unidades en existencia', valor: formatoNumero(r.unidades) },
      { etiqueta: 'Valor a precio de venta', valor: formatoMoneda(r.valor_venta) },
      {
        etiqueta: 'Valor a costo',
        valor: formatoMoneda(r.valor_costo),
        nota: r.sin_costo > 0 ? `No incluye ${cuenta(r.sin_costo, 'producto')} sin costo` : null,
      },
      { etiqueta: 'Stock bajo', valor: formatoNumero(r.stock_bajo) },
      { etiqueta: 'Agotados', valor: formatoNumero(r.agotados) },
    ],
    secciones: [
      {
        titulo: 'Productos por surtir',
        columnas: [
          columna('Producto'),
          columna('Proveedor'),
          columna('Existencia', 'numero'),
          columna('Mínimo', 'numero'),
          columna('Estado', 'estado'),
          columna('Sugerido', 'numero'),
          columna('Costo estimado', 'numero'),
        ],
        filas: d.por_surtir.map((p) => [
          p.Nombre_Producto,
          p.Proveedor || '—',
          formatoNumero(p.Cantidad),
          formatoNumero(p.Cantidad_Minima),
          p.Estado,
          formatoNumero(p.Sugerido),
          dineroOGuion(p.Costo_Estimado),
        ]),
        vacio: 'Ningún producto está en stock bajo ni agotado',
        nota:
          d.por_surtir.length === 0
            ? null
            : conCosto.length === 0
              ? 'Ninguno tiene costo de compra registrado: se conoce al surtirlo por primera vez.'
              : `Costo estimado para surtir lo sugerido: ${formatoMoneda(estimado)}` +
                (sinCosto > 0 ? ` (sin contar ${cuenta(sinCosto, 'producto')} sin costo)` : ''),
      },
      {
        titulo: 'Por categoría',
        columnas: [
          columna('Categoría'),
          columna('Productos', 'numero'),
          columna('Unidades', 'numero'),
          columna('Valor a precio', 'numero'),
          columna('Valor a costo', 'numero'),
        ],
        filas: d.categorias.map((c) => [
          c.Categoria,
          formatoNumero(c.Productos),
          formatoNumero(c.Unidades),
          formatoMoneda(c.Valor_Venta),
          dineroOGuion(c.Valor_Costo),
        ]),
      },
      {
        titulo: 'Existencias',
        columnas: [
          columna('Producto'),
          columna('Categoría'),
          columna('Existencia', 'numero'),
          columna('Precio', 'numero'),
          columna('Costo', 'numero'),
          columna('Valor a precio', 'numero'),
          columna('Estado', 'estado'),
        ],
        filas: d.productos.map((p) => [
          p.Nombre_Producto,
          p.Categoria,
          formatoNumero(p.Cantidad),
          formatoMoneda(p.Precio),
          dineroOGuion(p.Costo),
          formatoMoneda(p.Valor_Venta),
          p.Estado,
        ]),
        vacio: 'No hay productos activos',
      },
    ],
    notas: [
      'Sugerido: lo necesario para llegar al doble de la cantidad mínima.',
      'Los costos son el último costo de compra registrado en Surtir.',
    ],
  }
}

/**
 * Contenido del resumen del periodo: ventas contra compras, utilidad estimada, cortes de caja y flujo por día.
 *
 * @param {Object} d Respuesta de getSummaryReport
 * @returns {{indicadores: Array, secciones: Array, notas: Array<string>}} El contenido con formato
 */
function reporteResumen(d) {
  const { utilidad, cajas } = d

  return {
    indicadores: [
      { etiqueta: 'Ventas', valor: formatoMoneda(d.ventas.total), nota: cuenta(d.ventas.ventas, 'venta cobrada', 'ventas cobradas') },
      { etiqueta: 'Compras', valor: formatoMoneda(d.compras.total), nota: cuenta(d.compras.surtidos, 'surtido') },
      { etiqueta: 'Ventas menos compras', valor: formatoMoneda(d.flujo), nota: 'Flujo del periodo' },
      {
        etiqueta: 'Utilidad estimada',
        valor: formatoMoneda(utilidad.estimada),
        nota:
          utilidad.margen == null
            ? 'Sin costos de compra registrados'
            : `Margen ${formatoPorcentaje(utilidad.margen)} · cubre ${formatoPorcentaje(utilidad.cobertura)} de lo vendido`,
      },
      {
        etiqueta: 'Cortes de caja',
        valor: formatoNumero(cajas.cortes),
        nota: `${cuenta(cajas.descuadradas, 'descuadrado')} · diferencia ${formatoMoneda(cajas.diferencia)}`,
      },
    ],
    secciones: [
      {
        titulo: 'Ventas y compras por día',
        columnas: [columna('Fecha'), columna('Ventas', 'numero'), columna('Compras', 'numero'), columna('Ventas menos compras', 'numero')],
        filas: d.dias.map((dia) => [
          formatoFecha(dia.Fecha),
          formatoMoneda(dia.Ventas),
          formatoMoneda(dia.Compras),
          formatoMoneda(dia.Flujo),
        ]),
      },
    ],
    notas: [
      'La utilidad se estima con el último costo de compra de cada producto y solo incluye lo vendido de productos que ya tienen costo; es aproximada.',
      'Los cortes de caja cuentan las cajas cerradas que se abrieron en el periodo.',
    ],
  }
}

const CONSTRUCTORES = { ventas: reporteVentas, surtidos: reporteSurtidos, stock: reporteStock, resumen: reporteResumen }

/**
 * Reporte listo para mostrar en pantalla o en PDF.
 * Cada sección trae { titulo, columnas: [{ titulo, tipo }], filas: [[texto]], vacio, nota }.
 *
 * @param {'ventas'|'surtidos'|'stock'|'resumen'} tipo Tipo de reporte (TIPOS_REPORTE)
 * @param {Object} datos Respuesta del API con desde, hasta y generado
 * @returns {{tipo: string, titulo: string, periodo: string, generado: string, archivo: string,
 *           indicadores: Array, secciones: Array, notas: Array<string>}} El reporte
 * @throws {Error} Si el tipo de reporte no existe
 * @see ReportePDF
 */
export function construirReporte(tipo, datos) {
  const info = TIPOS_REPORTE.find((t) => t.id === tipo)
  if (!info || !CONSTRUCTORES[tipo]) throw new Error(`Tipo de reporte desconocido: ${tipo}`)

  const generado = formatoGenerado(datos.generado)
  const contenido = CONSTRUCTORES[tipo](datos)
  // El de stock es una foto del inventario en este momento; los demás cubren un periodo
  const fechaArchivo = info.sinPeriodo
    ? String(datos.generado ?? '').slice(0, 10)
    : `${datos.desde}_${datos.hasta}`

  return {
    tipo,
    titulo: info.titulo,
    periodo: info.sinPeriodo ? `Existencias al ${generado}` : textoPeriodo(datos.desde, datos.hasta),
    generado,
    archivo: `reporte-${tipo}-${fechaArchivo}`,
    ...contenido,
    secciones: contenido.secciones.map((s) => ({ vacio: 'Sin movimientos en este periodo', nota: null, ...s })),
  }
}
