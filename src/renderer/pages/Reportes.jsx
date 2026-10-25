import { useEffect, useState } from 'react'
import { useConsulta } from '../hooks/useConsulta'
import { getConfig, getRestockReport, getSalesReport, getStockReport, getSummaryReport } from '../api/api'
import { useHeader } from '../context/HeaderContext.jsx'
import { useNotify } from '../context/NotificationContext.jsx'
import { ActionButton, Pestanas } from '../components'
import { exportarReporte } from '../pdf/imprimir'
import { fechaLocal, periodo } from '../utils/fechas'
import { construirReporte, TIPOS_REPORTE } from '../utils/reportes'
import { DocumentArrowDownIcon } from '@heroicons/react/20/solid'

// Reportes (RF11, solo admin; regla 5): ventas, surtidos y compras, stock y resumen del periodo
// Lo que se ve en pantalla es lo mismo que se exporta a PDF (construirReporte)

const API = { ventas: getSalesReport, surtidos: getRestockReport, stock: getStockReport, resumen: getSummaryReport }

// La respuesta lleva su tipo: al cambiar de pestaña no se arma un reporte con los datos de otra
const cargar = ({ tipo, ...params }) => API[tipo](params).then((res) => ({ ...res, tipo }))

const PERIODOS = [
  { id: 'dia', label: 'Día' },
  { id: 'semana', label: 'Semana' },
  { id: 'mes', label: 'Mes' },
  { id: 'rango', label: 'Rango' },
]

const controlCls = 'px-2 py-1.5 rounded-lg border border-gray-300 text-sm bg-white'
const etiquetaCls = 'flex flex-col gap-1 text-xs font-semibold text-gray-700'

const ESTADO_CLS = { Agotado: 'text-red-600', 'Stock bajo': 'text-amber-600', Disponible: 'text-emerald-700' }

const esNegativo = (texto) => String(texto).startsWith('-')

function SelectorPeriodo({ seleccion, setSeleccion }) {
  const cambiar = (campo) => (e) => e.target.value && setSeleccion((s) => ({ ...s, [campo]: e.target.value }))

  return (
    <>
      <label className={etiquetaCls}>
        Periodo
        <select value={seleccion.tipo} onChange={cambiar('tipo')} className={controlCls}>
          {PERIODOS.map((p) => (
            <option key={p.id} value={p.id}>
              {p.label}
            </option>
          ))}
        </select>
      </label>

      {seleccion.tipo === 'mes' && (
        <label className={etiquetaCls}>
          Mes
          <input
            type="month"
            value={seleccion.fecha.slice(0, 7)}
            onChange={(e) => e.target.value && setSeleccion((s) => ({ ...s, fecha: `${e.target.value}-01` }))}
            className={controlCls}
          />
        </label>
      )}

      {(seleccion.tipo === 'dia' || seleccion.tipo === 'semana') && (
        <label className={etiquetaCls}>
          {seleccion.tipo === 'dia' ? 'Fecha' : 'Cualquier día de la semana'}
          <input type="date" value={seleccion.fecha} onChange={cambiar('fecha')} className={controlCls} />
        </label>
      )}

      {seleccion.tipo === 'rango' && (
        <>
          <label className={etiquetaCls}>
            Desde
            <input
              type="date"
              value={seleccion.desde}
              max={seleccion.hasta}
              onChange={cambiar('desde')}
              className={controlCls}
            />
          </label>
          <label className={etiquetaCls}>
            Hasta
            <input
              type="date"
              value={seleccion.hasta}
              min={seleccion.desde}
              onChange={cambiar('hasta')}
              className={controlCls}
            />
          </label>
        </>
      )}
    </>
  )
}

function Indicador({ indicador }) {
  return (
    <div className="bg-white border border-gray-200 rounded-lg p-3">
      <span className="block text-xs font-semibold text-gray-500 uppercase">{indicador.etiqueta}</span>
      <span
        className={`block text-xl font-bold tabular-nums ${esNegativo(indicador.valor) ? 'text-red-600' : 'text-gray-900'}`}
      >
        {indicador.valor}
      </span>
      {indicador.nota && <span className="block text-xs text-gray-500 mt-0.5">{indicador.nota}</span>}
    </div>
  )
}

function Seccion({ seccion }) {
  const ancha = seccion.columnas.length > 4

  return (
    <section className={`bg-white border border-gray-300 rounded-lg overflow-hidden ${ancha ? 'xl:col-span-2' : ''}`}>
      <h2 className="px-3 py-2 text-sm font-semibold text-gray-800 border-b border-gray-200">{seccion.titulo}</h2>
      {seccion.filas.length === 0 ? (
        <p className="text-center text-gray-500 py-6 text-sm">{seccion.vacio}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-800">
              <tr>
                {seccion.columnas.map((c) => (
                  <th
                    key={c.titulo}
                    scope="col"
                    className={`py-2 px-3 text-xs font-semibold text-white uppercase ${
                      c.tipo === 'numero' ? 'text-right' : 'text-left'
                    }`}
                  >
                    {c.titulo}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {seccion.filas.map((fila, i) => (
                <tr key={i} className="border-b border-gray-100 last:border-0">
                  {fila.map((celda, j) => {
                    const { tipo } = seccion.columnas[j]
                    // Un solo color por celda: el del estado, rojo si es negativo o el gris de siempre
                    const color =
                      (tipo === 'estado' && ESTADO_CLS[celda]) ||
                      (tipo === 'numero' && esNegativo(celda) && 'text-red-600') ||
                      'text-gray-700'
                    const alineacion = tipo === 'numero' ? 'text-right tabular-nums whitespace-nowrap' : ''
                    return (
                      <td key={j} className={`py-1.5 px-3 ${color} ${alineacion}`}>
                        {celda}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {seccion.nota && <p className="px-3 py-2 text-xs text-gray-600 border-t border-gray-200">{seccion.nota}</p>}
    </section>
  )
}

function ContenidoReporte({ reporte, actualizando }) {
  return (
    <div className={`flex flex-col gap-4 transition-opacity ${actualizando ? 'opacity-60' : ''}`}>
      <div className="grid gap-3 grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]">
        {reporte.indicadores.map((i) => (
          <Indicador key={i.etiqueta} indicador={i} />
        ))}
      </div>

      <div className="grid gap-4 xl:grid-cols-2 items-start">
        {reporte.secciones.map((s) => (
          <Seccion key={s.titulo} seccion={s} />
        ))}
      </div>

      <div className="text-xs text-gray-500 flex flex-col gap-0.5">
        {reporte.notas.map((n) => (
          <p key={n}>{n}</p>
        ))}
      </div>
    </div>
  )
}

export default function Reportes() {
  const notify = useNotify()
  const [tipo, setTipo] = useState('ventas')
  const [seleccion, setSeleccion] = useState(() => {
    const hoy = fechaLocal()
    return { tipo: 'mes', fecha: hoy, desde: periodo('mes', hoy).desde, hasta: hoy }
  })
  const [tienda, setTienda] = useState(null)

  useEffect(() => {
    getConfig().then((res) => res.ok && setTienda(res.configuracion))
  }, [])

  useHeader(<Pestanas opciones={TIPOS_REPORTE} activa={tipo} onCambiar={setTipo} />)

  const info = TIPOS_REPORTE.find((t) => t.id === tipo)
  const rango =
    seleccion.tipo === 'rango' ? { desde: seleccion.desde, hasta: seleccion.hasta } : periodo(seleccion.tipo, seleccion.fecha)
  const { cargando, datos, error } = useConsulta(cargar, info.sinPeriodo ? { tipo } : { tipo, ...rango })
  const reporte = datos?.tipo === tipo ? construirReporte(tipo, datos) : null

  async function exportar() {
    const res = await exportarReporte(reporte, tienda)
    if (res.ok) {
      notify({
        title: 'Reporte exportado',
        type: 'success',
        message: res.ruta ? `Se guardó en ${res.ruta}` : 'Se abrió en una ventana nueva',
      })
    } else {
      notify({ type: 'error', message: `No se pudo exportar el reporte: ${res.mensaje}` })
    }
  }

  return (
    <div className="w-full flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-3">
        {info.sinPeriodo ? (
          <p className="text-sm text-gray-600">Existencias en este momento: este reporte no depende del periodo.</p>
        ) : (
          <SelectorPeriodo seleccion={seleccion} setSeleccion={setSeleccion} />
        )}
        {reporte && !info.sinPeriodo && <p className="text-sm font-semibold text-gray-700 pb-1.5">{reporte.periodo}</p>}
        <div className="ml-auto">
          <ActionButton
            onClick={exportar}
            className="w-auto rounded-lg font-normal text-xs"
            {...((!reporte || cargando) && { disabled: true })}
          >
            <span className="flex items-center gap-1.5">
              <DocumentArrowDownIcon className="w-3.5 h-3.5" />
              Exportar PDF
            </span>
          </ActionButton>
        </div>
      </div>

      {cargando && !reporte ? (
        <p className="text-center text-gray-500 py-6 text-sm">Cargando...</p>
      ) : error ? (
        <p className="text-center text-red-600 py-6 text-sm">{error}</p>
      ) : (
        reporte && <ContenidoReporte reporte={reporte} actualizando={cargando} />
      )}
    </div>
  )
}
