import { documentoHTML } from './documento'
import TicketPDF, { ESTILOS_TICKET, formatoFolio } from './TicketPDF'

// HTML completo del ticket de una venta
export function ticketHTML(venta) {
  return documentoHTML(<TicketPDF venta={venta} />, `Ticket ${formatoFolio(venta.ID_Venta)}`, ESTILOS_TICKET)
}

// Genera el PDF del ticket (regla 8) y lo abre para imprimir
// En Electron se guarda en Documentos/SGT/Tickets/ticket-<folio>.pdf
// En el navegador (desarrollo sin Electron) se abre el ticket en una ventana nueva
// Regresa { ok, ruta } o { ok: false, mensaje }
export async function imprimirTicket(venta) {
  const html = ticketHTML(venta)

  if (window.electronAPI?.generarPDF) {
    return window.electronAPI.generarPDF({
      html,
      carpeta: 'Tickets',
      nombre: `ticket-${formatoFolio(venta.ID_Venta)}`,
      formato: 'ticket',
    })
  }

  const ventana = window.open('', '_blank', 'width=360,height=640')
  if (!ventana) return { ok: false, mensaje: 'El navegador bloqueó la ventana del ticket' }
  ventana.document.write(html)
  ventana.document.close()
  return { ok: true, ruta: null }
}
