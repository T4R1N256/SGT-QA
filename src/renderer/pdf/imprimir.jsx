/**
 * @file Tickets (regla 8) y reportes (RF11) en PDF.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { documentoHTML } from './documento'
import TicketPDF, { ESTILOS_TICKET, formatoFolio } from './TicketPDF'
import ReportePDF, { ESTILOS_REPORTE } from './ReportePDF'

/**
 * HTML completo del ticket de una venta.
 *
 * @param {Object} venta Venta finalizada como la regresan completeSale y getVenta
 * @returns {string} El HTML del ticket
 * @see TicketPDF
 */
export function ticketHTML(venta) {
  return documentoHTML(<TicketPDF venta={venta} />, `Ticket ${formatoFolio(venta.ID_Venta)}`, ESTILOS_TICKET)
}

/**
 * HTML completo de un reporte.
 *
 * @param {Object} reporte Reporte armado con construirReporte (utils/reportes.js)
 * @param {Object|null} tienda Configuración de la tienda (Nombre_Tienda)
 * @returns {string} El HTML del reporte
 * @see ReportePDF
 */
export function reporteHTML(reporte, tienda) {
  return documentoHTML(<ReportePDF reporte={reporte} tienda={tienda} />, reporte.titulo, ESTILOS_REPORTE)
}

/**
 * En Electron convierte el HTML a PDF, lo guarda en Documentos/SGT/<carpeta>/<nombre>.pdf y lo abre.
 * En el navegador (desarrollo sin Electron) abre el documento en una ventana nueva.
 *
 * @param {string} html Documento completo
 * @param {{carpeta: string, nombre: string, formato: ('ticket'|'carta')}} destino Carpeta, nombre y tamaño del PDF
 * @param {string} tamanoVentana Tamaño de la ventana del navegador (window.open)
 * @returns {Promise<{ok: boolean, ruta?: (string|null), mensaje?: string}>} { ok, ruta } o { ok: false, mensaje }
 */
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

/**
 * Genera el PDF del ticket (regla 8) y lo abre para imprimir: Documentos/SGT/Tickets/ticket-<folio>.pdf
 *
 * @param {Object} venta Venta finalizada
 * @returns {Promise<{ok: boolean, ruta?: (string|null), mensaje?: string}>} El resultado
 */
export function imprimirTicket(venta) {
  return generarDocumento(
    ticketHTML(venta),
    { carpeta: 'Tickets', nombre: `ticket-${formatoFolio(venta.ID_Venta)}`, formato: 'ticket' },
    'width=360,height=640'
  )
}

/**
 * Exporta un reporte a PDF tamaño carta (RF11): Documentos/SGT/Reportes/reporte-<tipo>-<periodo>.pdf
 *
 * @param {Object} reporte Reporte armado con construirReporte
 * @param {Object|null} tienda Configuración de la tienda
 * @returns {Promise<{ok: boolean, ruta?: (string|null), mensaje?: string}>} El resultado
 */
export function exportarReporte(reporte, tienda) {
  return generarDocumento(
    reporteHTML(reporte, tienda),
    { carpeta: 'Reportes', nombre: reporte.archivo, formato: 'carta' },
    'width=900,height=700'
  )
}
