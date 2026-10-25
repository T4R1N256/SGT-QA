import { documentoHTML } from './documento'
import TicketPDF, { ESTILOS_TICKET, formatoFolio } from './TicketPDF'
import ReportePDF, { ESTILOS_REPORTE } from './ReportePDF'

// HTML completo del ticket de una venta
export function ticketHTML(venta) {
  return documentoHTML(<TicketPDF venta={venta} />, `Ticket ${formatoFolio(venta.ID_Venta)}`, ESTILOS_TICKET)
}

// HTML completo de un reporte armado con construirReporte (utils/reportes.js)
export function reporteHTML(reporte, tienda) {
  return documentoHTML(<ReportePDF reporte={reporte} tienda={tienda} />, reporte.titulo, ESTILOS_REPORTE)
}

// En Electron convierte el HTML a PDF, lo guarda en Documentos/SGT/<carpeta>/<nombre>.pdf y lo abre
// En el navegador (desarrollo sin Electron) abre el documento en una ventana nueva
// Regresa { ok, ruta } o { ok: false, mensaje }
async function generarDocumento(html, { carpeta, nombre, formato }, tamanoVentana) {
  if (window.electronAPI?.generarPDF) {
    return window.electronAPI.generarPDF({ html, carpeta, nombre, formato })
  }

  const ventana = window.open('', '_blank', tamanoVentana)
  if (!ventana) return { ok: false, mensaje: 'El navegador bloqueó la ventana del documento' }
  ventana.document.write(html)
  ventana.document.close()
  return { ok: true, ruta: null }
}

// Genera el PDF del ticket (regla 8) y lo abre para imprimir: Documentos/SGT/Tickets/ticket-<folio>.pdf
export function imprimirTicket(venta) {
  return generarDocumento(
    ticketHTML(venta),
    { carpeta: 'Tickets', nombre: `ticket-${formatoFolio(venta.ID_Venta)}`, formato: 'ticket' },
    'width=360,height=640'
  )
}

// Exporta un reporte a PDF tamaño carta (RF11): Documentos/SGT/Reportes/reporte-<tipo>-<periodo>.pdf
export function exportarReporte(reporte, tienda) {
  return generarDocumento(
    reporteHTML(reporte, tienda),
    { carpeta: 'Reportes', nombre: reporte.archivo, formato: 'carta' },
    'width=900,height=700'
  )
}
