import { describe, expect, it } from 'vitest'
import { ticketHTML } from './imprimir'
import { formatoFechaHora, formatoFolio, formatoMoneda } from './TicketPDF'

const venta = {
  ID_Venta: 42,
  Fecha: '2026-10-25',
  Hora: '14:03:59',
  Monto: '110.90',
  Pago_Recibido: '200.00',
  Cambio: '89.10',
  Forma_Pago: 'efectivo',
  Cajero: 'Empleado Demo',
  productos: [
    { ID_Producto: 3, Nombre_Producto: 'Aceite vegetal 1 L', Cantidad: 1, Precio_Unitario: '45.90', Importe: '45.90' },
    { ID_Producto: 1, Nombre_Producto: 'Arroz <1 kg>', Cantidad: 2, Precio_Unitario: '32.50', Importe: '65.00' },
  ],
  tienda: {
    Nombre_Tienda: 'Abarrotes "La bamba"',
    Direccion: 'Av. Juárez 100',
    Telefono: '6561234567',
    RFC: '',
    Mensaje_Ticket: '¡Gracias por su compra!',
  },
}

describe('formatos del ticket', () => {
  it('folio con 6 dígitos', () => {
    expect(formatoFolio(42)).toBe('000042')
  })

  it('fecha y hora en formato local', () => {
    expect(formatoFechaHora('2026-10-25', '14:03:59')).toBe('25/10/2026 14:03')
  })

  it('moneda en pesos', () => {
    expect(formatoMoneda('1234.5')).toBe('$1,234.50')
    expect(formatoMoneda(null)).toBe('$0.00')
  })
})

describe('ticketHTML', () => {
  const html = ticketHTML(venta)

  it('incluye tienda, folio, cajero, productos, total y cambio', () => {
    for (const texto of [
      'Abarrotes &quot;La bamba&quot;',
      'Av. Juárez 100',
      'Folio 000042',
      '25/10/2026 14:03',
      'Cajero: Empleado Demo',
      'Aceite vegetal 1 L',
      '$110.90',
      '$200.00',
      '$89.10',
      '2 x $32.50',
      'Precios con impuestos incluidos',
      '¡Gracias por su compra!',
    ]) {
      expect(html).toContain(texto)
    }
  })

  it('escapa el texto de los productos', () => {
    expect(html).toContain('Arroz &lt;1 kg&gt;')
    expect(html).not.toContain('<1 kg>')
  })

  it('omite los datos vacíos de la tienda', () => {
    expect(html).not.toContain('RFC')
  })

  it('con tarjeta no muestra pago ni cambio', () => {
    const conTarjeta = ticketHTML({ ...venta, Forma_Pago: 'tarjeta', Pago_Recibido: '110.90', Cambio: '0.00' })
    expect(conTarjeta).toContain('Tarjeta')
    expect(conTarjeta).not.toContain('Cambio')
  })

  it('es un documento completo que bloquea scripts', () => {
    expect(html.startsWith('<!doctype html>')).toBe(true)
    expect(html).toContain("default-src 'none'")
    expect(html).toContain('<title>Ticket 000042</title>')
  })
})
