/**
 * @file Documento HTML para convertir a PDF (tickets y reportes).
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { renderToStaticMarkup } from 'react-dom/server'

/**
 * Documento HTML independiente para convertir a PDF: estilos en línea y fuentes del sistema,
 * sin recursos externos (funciona sin internet). La política de contenido bloquea scripts.
 *
 * @param {React.ReactElement} elemento Contenido (TicketPDF o ReportePDF)
 * @param {string} titulo Título del documento (se escapa)
 * @param {string} [estilos=''] CSS del documento
 * @returns {string} El HTML completo
 */
export function documentoHTML(elemento, titulo, estilos = '') {
  const cuerpo = renderToStaticMarkup(elemento)
  const tituloSeguro = renderToStaticMarkup(titulo)

  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src data:">
<title>${tituloSeguro}</title>
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #fff; color: #000; }
  ${estilos}
</style>
</head>
<body>${cuerpo}</body>
</html>`
}
