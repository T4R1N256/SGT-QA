// Ticket de venta (regla 8) para imprimir en papel de 80 mm
// Recibe los datos que regresa el backend en completeSale y getVenta: venta, productos, cajero y tienda

import { formatoMoneda } from '../../compartido/format'

export const ESTILOS_TICKET = `
  body { width: 80mm; padding: 4mm 4mm 6mm; font-family: 'DejaVu Sans Mono', Consolas, 'Courier New', monospace; font-size: 11px; line-height: 1.35; }
  .centro { text-align: center; }
  .tienda { font-size: 14px; font-weight: bold; }
  .separador { border-top: 1px dashed #000; margin: 6px 0; }
  .fila { display: flex; justify-content: space-between; gap: 8px; }
  .producto { margin-bottom: 3px; }
  .detalle { padding-left: 2ch; color: #333; }
  .total { font-size: 13px; font-weight: bold; }
`


// Folio de 6 dígitos: 000123
export function formatoFolio(ID_Venta) {
  return String(ID_Venta ?? '').padStart(6, '0')
}

// 2026-10-25 + 14:03:59 → 25/10/2026 14:03
export function formatoFechaHora(fecha, hora) {
  const [anio, mes, dia] = String(fecha ?? '').split('-')
  return `${dia}/${mes}/${anio} ${String(hora ?? '').slice(0, 5)}`
}

const FORMAS_PAGO = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia' }

export default function TicketPDF({ venta }) {
  const tienda = venta.tienda ?? {}
  const productos = venta.productos ?? []
  const enEfectivo = venta.Forma_Pago === 'efectivo'

  return (
    <div>
      <div className="centro">
        <div className="tienda">{tienda.Nombre_Tienda}</div>
        {tienda.Direccion && <div>{tienda.Direccion}</div>}
        {tienda.Telefono && <div>Tel. {tienda.Telefono}</div>}
        {tienda.RFC && <div>RFC {tienda.RFC}</div>}
      </div>

      <div className="separador" />
      <div className="fila">
        <span>Folio {formatoFolio(venta.ID_Venta)}</span>
        <span>{formatoFechaHora(venta.Fecha, venta.Hora)}</span>
      </div>
      {venta.Cajero && <div>Cajero: {venta.Cajero}</div>}

      <div className="separador" />
      {productos.map((p) => (
        <div className="producto" key={p.ID_Producto}>
          <div className="fila">
            <span>
              {p.Cantidad} {p.Nombre_Producto}
            </span>
            <span>{formatoMoneda(p.Importe)}</span>
          </div>
          {Number(p.Cantidad) > 1 && (
            <div className="detalle">
              {p.Cantidad} x {formatoMoneda(p.Precio_Unitario)}
            </div>
          )}
        </div>
      ))}

      <div className="separador" />
      <div className="fila total">
        <span>TOTAL</span>
        <span>{formatoMoneda(venta.Monto)}</span>
      </div>
      <div className="fila">
        <span>Forma de pago</span>
        <span>{FORMAS_PAGO[venta.Forma_Pago] ?? venta.Forma_Pago}</span>
      </div>
      {enEfectivo && (
        <>
          <div className="fila">
            <span>Pago</span>
            <span>{formatoMoneda(venta.Pago_Recibido)}</span>
          </div>
          <div className="fila">
            <span>Cambio</span>
            <span>{formatoMoneda(venta.Cambio)}</span>
          </div>
        </>
      )}

      <div className="separador" />
      <div className="centro">Precios con impuestos incluidos</div>
      {tienda.Mensaje_Ticket && <div className="centro">{tienda.Mensaje_Ticket}</div>}
    </div>
  )
}
