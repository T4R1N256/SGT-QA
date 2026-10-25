import { useEffect, useState } from 'react'
import { getSalesHistory, getCashRegisters, getCashRegisterDetail, getVenta } from '../api/api'
import { useHeader } from '../context/HeaderContext.jsx'
import { useNotify } from '../context/NotificationContext.jsx'
import { ActionButton } from '../components'
import { imprimirTicket } from '../pdf/imprimir'
import { formatoFolio, formatoMoneda } from '../pdf/TicketPDF'
import { rangoDias } from '../utils/fechas'
import { capitalize } from '../utils/format'
import { PrinterIcon } from '@heroicons/react/20/solid'

// Historial (solo admin): ventas pasadas con su ticket y cortes de caja con su detalle

const PESTANAS = [
  { id: 'ventas', label: 'Ventas' },
  { id: 'cajas', label: 'Cortes de caja' },
]

const controlCls = 'px-2 py-1.5 rounded-lg border border-gray-300 text-sm bg-white'
const encabezadoCls = 'py-2 px-2 text-center text-xs text-white'

// Llama a la API cuando cambian los filtros; ignora respuestas viejas si los filtros cambiaron antes
function useConsulta(apiFn, filtros) {
  const [estado, setEstado] = useState({ cargando: true, datos: null, error: null })
  const clave = JSON.stringify(filtros)

  useEffect(() => {
    let vigente = true
    setEstado((prev) => ({ ...prev, cargando: true }))
    apiFn(filtros).then((res) => {
      if (!vigente) return
      setEstado(res.ok ? { cargando: false, datos: res, error: null } : { cargando: false, datos: null, error: res.mensaje })
    })
    return () => {
      vigente = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clave])

  return estado
}

function Fechas({ filtros, setFiltros }) {
  return (
    <>
      <label className="flex flex-col gap-1 text-xs font-semibold text-gray-700">
        Desde
        <input
          type="date"
          value={filtros.desde}
          max={filtros.hasta}
          onChange={(e) => setFiltros((f) => ({ ...f, desde: e.target.value, pagina: 1 }))}
          className={controlCls}
        />
      </label>
      <label className="flex flex-col gap-1 text-xs font-semibold text-gray-700">
        Hasta
        <input
          type="date"
          value={filtros.hasta}
          min={filtros.desde}
          onChange={(e) => setFiltros((f) => ({ ...f, hasta: e.target.value, pagina: 1 }))}
          className={controlCls}
        />
      </label>
    </>
  )
}

function EstadoTabla({ cargando, error, vacio, children }) {
  if (cargando) return <p className="text-center text-gray-500 py-6 text-sm">Cargando...</p>
  if (error) return <p className="text-center text-red-600 py-6 text-sm">{error}</p>
  if (vacio) return <p className="text-center text-gray-500 py-6 text-sm">No hay registros en este periodo</p>
  return children
}

// ── Ventas ───────────────────────────────────────────────────────────────────

const VENTAS_COLUMNAS = 'grid grid-cols-[0.8fr_1fr_0.6fr_1.4fr_1fr_0.7fr_1fr_0.9fr]'

function DetalleVenta({ venta }) {
  const notify = useNotify()
  const [ticket, setTicket] = useState(null)

  useEffect(() => {
    if (venta.Estado !== 'finalizada') return
    getVenta(venta.ID_Venta).then((res) => setTicket(res.ok ? res.venta : { error: res.mensaje }))
  }, [venta])

  if (venta.Estado !== 'finalizada') {
    return <p className="text-gray-500">Venta cancelada: no se cobró y no tiene ticket.</p>
  }
  if (!ticket) return <p className="text-gray-500">Cargando...</p>
  if (ticket.error) return <p className="text-red-600">{ticket.error}</p>

  async function reimprimir() {
    const res = await imprimirTicket(ticket)
    if (!res.ok) notify({ type: 'error', message: `No se pudo reimprimir el ticket: ${res.mensaje}` })
  }

  return (
    <div className="flex items-start justify-between gap-6">
      <ul className="flex flex-col gap-1 flex-1">
        {ticket.productos.map((p) => (
          <li key={p.ID_Producto} className="flex justify-between">
            <span>
              {p.Cantidad} × {p.Nombre_Producto} a {formatoMoneda(p.Precio_Unitario)}
            </span>
            <span className="font-semibold">{formatoMoneda(p.Importe)}</span>
          </li>
        ))}
        {ticket.Forma_Pago === 'efectivo' && (
          <li className="text-gray-500">
            Pagó {formatoMoneda(ticket.Pago_Recibido)} · Cambio {formatoMoneda(ticket.Cambio)}
          </li>
        )}
      </ul>
      <ActionButton onClick={reimprimir} className="w-auto rounded-lg font-normal text-xs">
        <span className="flex items-center gap-1.5">
          <PrinterIcon className="w-3.5 h-3.5" />
          Reimprimir ticket
        </span>
      </ActionButton>
    </div>
  )
}

function HistorialVentas() {
  const [filtros, setFiltros] = useState({ ...rangoDias(30), forma_pago: '', estado: 'finalizada', pagina: 1 })
  const [abierta, setAbierta] = useState(null)
  const { cargando, datos, error } = useConsulta(getSalesHistory, filtros)
  const ventas = datos?.ventas ?? []

  const cambiar = (campo) => (e) => setFiltros((f) => ({ ...f, [campo]: e.target.value, pagina: 1 }))

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-end gap-3">
        <Fechas filtros={filtros} setFiltros={setFiltros} />
        <label className="flex flex-col gap-1 text-xs font-semibold text-gray-700">
          Forma de pago
          <select value={filtros.forma_pago} onChange={cambiar('forma_pago')} className={controlCls}>
            <option value="">Todas</option>
            <option value="efectivo">Efectivo</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="transferencia">Transferencia</option>
          </select>
        </label>
        <label className="flex flex-col gap-1 text-xs font-semibold text-gray-700">
          Estado
          <select value={filtros.estado} onChange={cambiar('estado')} className={controlCls}>
            <option value="finalizada">Cobradas</option>
            <option value="cancelada">Canceladas</option>
            <option value="todas">Todas</option>
          </select>
        </label>
        {datos && (
          <p className="ml-auto text-sm text-gray-700">
            <span className="font-semibold">{datos.resumen.ventas}</span> ventas · Total cobrado{' '}
            <span className="font-semibold">{formatoMoneda(datos.resumen.total)}</span>
          </p>
        )}
      </div>

      <div className="border border-gray-300 rounded-lg overflow-hidden bg-white">
        <div className={`${VENTAS_COLUMNAS} bg-gray-800`}>
          {['FOLIO', 'FECHA', 'HORA', 'CAJERO', 'FORMA DE PAGO', 'ARTÍCULOS', 'TOTAL', 'ESTADO'].map((c) => (
            <div key={c} className={encabezadoCls}>
              {c}
            </div>
          ))}
        </div>
        <EstadoTabla cargando={cargando} error={error} vacio={ventas.length === 0}>
          <ul>
            {ventas.map((v) => (
              <li key={v.ID_Venta} className="border-b border-gray-100">
                <button
                  type="button"
                  onClick={() => setAbierta(abierta?.ID_Venta === v.ID_Venta ? null : v)}
                  className={`${VENTAS_COLUMNAS} w-full py-2 text-sm text-gray-700 text-center hover:bg-gray-50`}
                >
                  <span>{formatoFolio(v.ID_Venta)}</span>
                  <span>{v.Fecha}</span>
                  <span>{String(v.Hora).slice(0, 5)}</span>
                  <span className="truncate">{v.Cajero ?? '-'}</span>
                  <span>{capitalize(v.Forma_Pago ?? '-')}</span>
                  <span>{v.Articulos}</span>
                  <span className="font-semibold">{v.Estado === 'finalizada' ? formatoMoneda(v.Monto) : '-'}</span>
                  <span className={v.Estado === 'finalizada' ? 'text-emerald-700' : 'text-red-600'}>
                    {v.Estado === 'finalizada' ? 'Cobrada' : 'Cancelada'}
                  </span>
                </button>
                {abierta?.ID_Venta === v.ID_Venta && (
                  <div className="bg-gray-50 px-6 py-3 text-sm text-gray-700">
                    <DetalleVenta venta={v} />
                  </div>
                )}
              </li>
            ))}
          </ul>
        </EstadoTabla>
      </div>

      {datos && datos.paginas > 1 && (
        <div className="flex items-center justify-end gap-3 text-sm text-gray-700">
          <button
            type="button"
            disabled={filtros.pagina <= 1}
            onClick={() => setFiltros((f) => ({ ...f, pagina: f.pagina - 1 }))}
            className="px-3 py-1 rounded border border-gray-300 bg-white disabled:opacity-40"
          >
            Anterior
          </button>
          <span>
            Página {datos.pagina} de {datos.paginas}
          </span>
          <button
            type="button"
            disabled={filtros.pagina >= datos.paginas}
            onClick={() => setFiltros((f) => ({ ...f, pagina: f.pagina + 1 }))}
            className="px-3 py-1 rounded border border-gray-300 bg-white disabled:opacity-40"
          >
            Siguiente
          </button>
        </div>
      )}
    </div>
  )
}

// ── Cortes de caja ───────────────────────────────────────────────────────────

const CAJAS_COLUMNAS = 'grid grid-cols-[1fr_0.6fr_0.6fr_1.2fr_1.2fr_0.9fr_0.9fr_0.9fr_0.9fr_1fr]'

function EtiquetaCorte({ caja }) {
  if (caja.Estado === 'abierta') return <span className="text-amber-600">Abierta</span>
  return caja.Estado_Final === 'cuadrada' ? (
    <span className="text-emerald-700">Cuadrada</span>
  ) : (
    <span className="text-red-600">Descuadrada</span>
  )
}

function DetalleCaja({ ID_Caja }) {
  const [detalle, setDetalle] = useState(null)

  useEffect(() => {
    getCashRegisterDetail(ID_Caja).then((res) => setDetalle(res.ok ? res : { error: res.mensaje }))
  }, [ID_Caja])

  if (!detalle) return <p className="text-gray-500">Cargando...</p>
  if (detalle.error) return <p className="text-red-600">{detalle.error}</p>

  const { totales } = detalle
  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-5 gap-3">
        {[
          ['Efectivo', totales.efectivo],
          ['Tarjeta', totales.tarjeta],
          ['Transferencia', totales.transferencia],
          ['Total vendido', totales.total],
          ['Efectivo esperado', detalle.Efectivo_Esperado],
        ].map(([etiqueta, monto]) => (
          <div key={etiqueta} className="bg-white border border-gray-200 rounded-lg p-2">
            <span className="block text-xs font-semibold text-gray-500 uppercase">{etiqueta}</span>
            <span className="font-semibold">{formatoMoneda(monto)}</span>
          </div>
        ))}
      </div>
      <p className="text-gray-500">
        {totales.ventas} ventas cobradas · {detalle.canceladas} canceladas
      </p>
      {detalle.ventas.length > 0 && (
        <ul className="flex flex-col gap-1">
          {detalle.ventas.map((v) => (
            <li key={v.ID_Venta} className="flex justify-between">
              <span>
                Folio {formatoFolio(v.ID_Venta)} · {String(v.Hora).slice(0, 5)} · {capitalize(v.Forma_Pago)}
              </span>
              <span className="font-semibold">{formatoMoneda(v.Monto)}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function HistorialCajas() {
  const [filtros, setFiltros] = useState(rangoDias(30))
  const [abierta, setAbierta] = useState(null)
  const { cargando, datos, error } = useConsulta(getCashRegisters, filtros)
  const cajas = datos?.cajas ?? []
  const dinero = (monto) => (monto == null ? '-' : formatoMoneda(monto))

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-end gap-3">
        <Fechas filtros={filtros} setFiltros={setFiltros} />
      </div>

      <div className="border border-gray-300 rounded-lg overflow-hidden bg-white">
        <div className={`${CAJAS_COLUMNAS} bg-gray-800`}>
          {['FECHA', 'APERTURA', 'CIERRE', 'ABRIÓ', 'CERRÓ', 'INICIAL', 'ESPERADO', 'CONTADO', 'DIFERENCIA', 'ESTADO'].map(
            (c) => (
              <div key={c} className={encabezadoCls}>
                {c}
              </div>
            )
          )}
        </div>
        <EstadoTabla cargando={cargando} error={error} vacio={cajas.length === 0}>
          <ul>
            {cajas.map((c) => (
              <li key={c.ID_Caja} className="border-b border-gray-100">
                <button
                  type="button"
                  onClick={() => setAbierta(abierta === c.ID_Caja ? null : c.ID_Caja)}
                  className={`${CAJAS_COLUMNAS} w-full py-2 text-sm text-gray-700 text-center hover:bg-gray-50`}
                >
                  <span>{c.Fecha}</span>
                  <span>{String(c.Hora).slice(0, 5)}</span>
                  <span>{c.Hora_Final ? String(c.Hora_Final).slice(0, 5) : '-'}</span>
                  <span className="truncate">{c.Abrio ?? '-'}</span>
                  <span className="truncate">{c.Cerro ?? '-'}</span>
                  <span>{dinero(c.Monto_Inicial)}</span>
                  <span>{dinero(c.Monto_Final_Sistema)}</span>
                  <span>{dinero(c.Monto_Final)}</span>
                  <span className={Number(c.Diferencia) < 0 ? 'text-red-600' : ''}>{dinero(c.Diferencia)}</span>
                  <EtiquetaCorte caja={c} />
                </button>
                {abierta === c.ID_Caja && (
                  <div className="bg-gray-50 px-6 py-3 text-sm text-gray-700">
                    <DetalleCaja ID_Caja={c.ID_Caja} />
                  </div>
                )}
              </li>
            ))}
          </ul>
        </EstadoTabla>
      </div>
    </div>
  )
}

export default function Historial() {
  const [pestana, setPestana] = useState('ventas')

  useHeader(
    <div className="flex gap-1 p-1 bg-gray-700 rounded-lg">
      {PESTANAS.map((p) => (
        <button
          key={p.id}
          type="button"
          onClick={() => setPestana(p.id)}
          className={`px-3 py-1 text-xs font-semibold rounded ${
            pestana === p.id ? 'bg-white text-gray-900' : 'text-gray-300 hover:text-white'
          }`}
        >
          {p.label}
        </button>
      ))}
    </div>
  )

  return <div className="w-full">{pestana === 'ventas' ? <HistorialVentas /> : <HistorialCajas />}</div>
}
