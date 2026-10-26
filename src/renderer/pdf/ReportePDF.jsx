/**
 * @file Reporte en hoja tamaño carta (RF11). Recibe el reporte ya armado por construirReporte (utils/reportes.js)
 * y los datos de la tienda. Las tablas repiten su encabezado en cada página y los renglones no se parten.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

export const ESTILOS_REPORTE = `
  body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 10px; line-height: 1.35; color: #111; }
  .encabezado { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px;
    border-bottom: 2px solid #1f2937; padding-bottom: 6px; margin-bottom: 12px; }
  .tienda { font-size: 11px; color: #444; }
  h1 { font-size: 18px; margin: 2px 0; }
  .periodo { font-size: 11px; }
  .generado { font-size: 9px; color: #555; text-align: right; white-space: nowrap; }
  .indicadores { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-bottom: 14px; }
  .indicador { border: 1px solid #d1d5db; border-radius: 4px; padding: 6px 8px; break-inside: avoid; }
  .etiqueta { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #555; }
  .valor { font-size: 14px; font-weight: bold; }
  .nota-indicador { font-size: 8px; color: #555; }
  .seccion { margin-bottom: 14px; }
  h2 { font-size: 12px; margin: 0 0 4px; break-after: avoid; }
  table { width: 100%; border-collapse: collapse; }
  thead { display: table-header-group; }
  th { background: #1f2937; color: #fff; font-size: 9px; text-align: left; padding: 3px 5px; }
  td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
  tr { break-inside: avoid; }
  .numero { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .vacio { color: #555; font-style: italic; }
  .nota-seccion { font-size: 9px; color: #333; margin-top: 3px; }
  .notas { font-size: 8px; color: #555; border-top: 1px solid #d1d5db; padding-top: 4px; }
  .notas p { margin: 0 0 2px; }
`

/**
 * Tabla de una sección del reporte; las columnas numéricas se alinean a la derecha.
 *
 * @param {{seccion: {columnas: Array, filas: Array, vacio: string}}} props
 * @returns {JSX.Element} La tabla, o el texto de vacío si no hay filas
 */
function Tabla({ seccion }) {
  if (seccion.filas.length === 0) return <p className="vacio">{seccion.vacio}</p>

  return (
    <table>
      <thead>
        <tr>
          {seccion.columnas.map((c) => (
            <th key={c.titulo} className={c.tipo === 'numero' ? 'numero' : undefined}>
              {c.titulo}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {seccion.filas.map((fila, i) => (
          <tr key={i}>
            {fila.map((celda, j) => (
              <td key={j} className={seccion.columnas[j].tipo === 'numero' ? 'numero' : undefined}>
                {celda}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  )
}

/**
 * Reporte para PDF: encabezado con la tienda, título y periodo, indicadores, secciones y notas.
 *
 * @param {{reporte: Object, tienda: (Object|null)}} props
 * @returns {JSX.Element} El reporte
 */
export default function ReportePDF({ reporte, tienda }) {
  return (
    <div>
      <div className="encabezado">
        <div>
          {tienda?.Nombre_Tienda && <div className="tienda">{tienda.Nombre_Tienda}</div>}
          <h1>{reporte.titulo}</h1>
          <div className="periodo">{reporte.periodo}</div>
        </div>
        <div className="generado">Generado el {reporte.generado}</div>
      </div>

      <div className="indicadores">
        {reporte.indicadores.map((i) => (
          <div key={i.etiqueta} className="indicador">
            <div className="etiqueta">{i.etiqueta}</div>
            <div className="valor">{i.valor}</div>
            {i.nota && <div className="nota-indicador">{i.nota}</div>}
          </div>
        ))}
      </div>

      {reporte.secciones.map((s) => (
        <div key={s.titulo} className="seccion">
          <h2>{s.titulo}</h2>
          <Tabla seccion={s} />
          {s.nota && <p className="nota-seccion">{s.nota}</p>}
        </div>
      ))}

      {reporte.notas?.length > 0 && (
        <div className="notas">
          {reporte.notas.map((n) => (
            <p key={n}>{n}</p>
          ))}
        </div>
      )}
    </div>
  )
}
