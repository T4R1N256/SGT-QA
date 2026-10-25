import { useCallback, useEffect, useRef, useState } from 'react'
import { ActionButton, FormAlert, FormModal } from '../components'
import { useAuth } from '../context/AuthContext'
import { useNotify } from '../context/NotificationContext.jsx'
import { openCaja, closeCaja, getAllVentas, getMontoFinal } from '../api/api'
import { calcularCambio } from '../utils/money'
import { LockClosedIcon, LockOpenIcon } from '@heroicons/react/20/solid'

const VENTA_COLUMNS = [
  { label: 'FECHA' },
  { label: 'HORA' },
  { label: 'FORMA DE PAGO' },
  { label: 'MONTO' },
]

const GRID_COLS = 'grid grid-cols-[1fr_1fr_1fr_1fr]'

const TOTALES_VACIOS = { efectivo: 0, tarjeta: 0, transferencia: 0, total: 0, ventas: 0 }

function formatMoney(n) {
  const valor = Number(n)
  return `$${(Number.isFinite(valor) ? valor : 0).toFixed(2)}`
}

export default function Caja() {
  const { caja, setCaja } = useAuth()
  const notify = useNotify()
  const [estado, setEstado] = useState(caja ? 'abierta' : 'cerrada')
  const [saldoInicial, setSaldoInicial] = useState(caja ? Number(caja.Monto_Inicial) : 0)

  const [ventas, setVentas] = useState([])
  // Totales por forma de pago y efectivo esperado: los calcula el backend (el cuadre solo cuenta efectivo)
  const [totales, setTotales] = useState(TOTALES_VACIOS)
  const [efectivoEsperado, setEfectivoEsperado] = useState(saldoInicial)
  const [loadingVentas, setLoadingVentas] = useState(false)

  const [montoApertura, setMontoApertura] = useState('')

  const [montoFinal, setMontoFinal] = useState('')
  const [cerrando, setCerrando] = useState(false)
  const [cierreError, setCierreError] = useState(null)
  const cierreModalRef = useRef(null)

  const [response, setResponse] = useState(null)

  // Efectivo contado menos efectivo esperado (en centavos exactos)
  const diferencia = calcularCambio(parseFloat(montoFinal) || 0, efectivoEsperado)

  const fetchVentas = useCallback(async () => {
    setLoadingVentas(true)
    const [ventasRes, montoRes] = await Promise.all([getAllVentas(), getMontoFinal()])
    setLoadingVentas(false)

    // La caja se cerró en otra sesión: se sincroniza la pantalla
    if (montoRes.status === 409) {
      setCaja(null)
      setEstado('cerrada')
      return
    }

    setVentas(ventasRes.ok ? (ventasRes.ventas ?? []) : [])
    if (montoRes.ok) {
      setTotales(montoRes.totales ?? TOTALES_VACIOS)
      setSaldoInicial(Number(montoRes.Monto_Inicial))
      setEfectivoEsperado(Number(montoRes.Efectivo_Esperado))
    }
  }, [setCaja])

  useEffect(() => {
    if (estado === 'abierta') fetchVentas()
  }, [estado, fetchVentas])

  async function abrirCaja() {
    const monto = parseFloat(montoApertura)
    if (!Number.isFinite(monto) || monto < 0) return
    setResponse(null)
    const res = await openCaja({ Monto_Inicial: monto })
    if (!res.ok) {
      // Ya hay una caja abierta (por ejemplo, desde otra sesión): se muestra esa caja
      if (res.status === 409) {
        const abierta = await getMontoFinal()
        if (abierta.ok) {
          setCaja({ Monto_Inicial: abierta.Monto_Inicial })
          setSaldoInicial(Number(abierta.Monto_Inicial))
          setEstado('abierta')
          return
        }
      }
      setResponse({ type: 'error', message: res.mensaje ?? 'No se pudo abrir la caja' })
      return
    }
    setCaja(res.caja ?? { Monto_Inicial: monto })
    setSaldoInicial(monto)
    setEstado('abierta')
    setMontoApertura('')
  }

  function abrirModalCierre() {
    setMontoFinal(efectivoEsperado.toFixed(2))
    setCierreError(null)
    cierreModalRef.current?.showModal()
  }

  async function confirmarCierre() {
    const monto = parseFloat(montoFinal)
    if (!Number.isFinite(monto) || monto < 0) {
      setCierreError('Ingresa un monto final válido')
      return
    }
    setCerrando(true)
    setCierreError(null)
    const res = await closeCaja({ Monto_Final: monto })
    setCerrando(false)
    if (!res.ok) {
      setCierreError(res.mensaje ?? 'No se pudo cerrar la caja')
      return
    }
    cierreModalRef.current?.close()
    setCaja(null)
    const corte = res.corte
    notify({
      title: `Corte de caja: ${corte.Estado_Final}`,
      type: corte.Estado_Final === 'cuadrada' ? 'success' : 'warning',
      message:
        `Saldo inicial: ${formatMoney(corte.Monto_Inicial)}\n` +
        `Ventas: ${corte.totales.ventas} (${formatMoney(corte.totales.total)})\n` +
        `Efectivo: ${formatMoney(corte.totales.efectivo)} · Tarjeta: ${formatMoney(corte.totales.tarjeta)} · Transferencia: ${formatMoney(corte.totales.transferencia)}\n` +
        `Efectivo esperado: ${formatMoney(corte.Efectivo_Esperado)}\n` +
        `Efectivo contado: ${formatMoney(corte.Monto_Final)}\n` +
        `Diferencia: ${formatMoney(corte.Diferencia)}`,
    })
    setEstado('cerrada')
    setSaldoInicial(0)
    setVentas([])
    setTotales(TOTALES_VACIOS)
    setEfectivoEsperado(0)
    setMontoFinal('')
  }

  if (estado === 'cerrada') {
    return (
      <div className="flex flex-1 items-center justify-center">
        <div className="bg-white rounded-lg shadow border border-gray-300 p-8 w-full max-w-md">
          <div className="flex items-center gap-2 mb-2">
            <LockClosedIcon className="w-5 h-5 text-gray-600" />
            <h2 className="text-lg font-semibold text-gray-800">Caja cerrada</h2>
          </div>
          <p className="text-sm text-gray-500 mb-6">
            Ingresa el saldo inicial para abrir la caja del turno.
          </p>

          <div className="mb-3">
            <FormAlert response={response} />
          </div>

          <label className="block text-xs font-semibold text-gray-700 mb-1">Saldo inicial</label>
          <input
            type="text"
            inputMode="decimal"
            value={montoApertura}
            onChange={(e) => setMontoApertura(e.target.value)}
            placeholder="0.00"
            className="w-full mb-4 px-3 py-2 rounded-lg border border-gray-300 text-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />

          <ActionButton
            onClick={abrirCaja}
            disabled={!montoApertura}
            className="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed font-semibold"
          >
            <span className="flex items-center gap-2 justify-center">
              <LockOpenIcon className="w-5 h-5" />
              Abrir caja
            </span>
          </ActionButton>
        </div>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4 w-full h-full">
      {response && <FormAlert response={response} />}

      <div className="grid grid-cols-5 gap-4">
        <StatCard label="Saldo inicial" value={saldoInicial} />
        <StatCard label="Ventas en efectivo" value={totales.efectivo} tone="emerald" />
        <StatCard label="Tarjeta y transferencia" value={totales.tarjeta + totales.transferencia} />
        <StatCard label="# de ventas" value={totales.ventas} isMoney={false} />
        <StatCard label="Efectivo esperado" value={efectivoEsperado} bold />
      </div>

      <div className="flex justify-between items-center px-1">
        <h3 className="text-sm font-semibold text-gray-700 uppercase">Ventas del turno</h3>
        <button
          onClick={fetchVentas}
          disabled={loadingVentas}
          className="text-xs text-gray-600 hover:text-gray-900 underline disabled:opacity-50"
        >
          {loadingVentas ? 'Cargando...' : 'Refrescar'}
        </button>
      </div>

      <div className="flex flex-col w-full flex-1 border border-gray-300 bg-white shadow rounded-lg overflow-hidden">
        <div className={`w-full bg-gray-800 ${GRID_COLS}`}>
          {VENTA_COLUMNS.map(({ label }, i) => (
            <div
              key={label}
              className={`py-2 text-xs text-center border-b-2 border-gray-300 text-white ${i === 0 ? 'pl-4' : ''} ${i === VENTA_COLUMNS.length - 1 ? 'pr-4' : ''}`}
            >
              {label}
            </div>
          ))}
        </div>
        <div className="flex flex-col w-full overflow-y-auto flex-1">
          {loadingVentas ? (
            <p className="w-full text-center text-gray-500 py-6 text-sm">Cargando ventas...</p>
          ) : ventas.length === 0 ? (
            <p className="w-full text-center text-gray-500 py-6 text-sm">
              Aún no hay ventas registradas en este turno
            </p>
          ) : (
            <ul>
              {ventas.map((v, i) => (
                <VentaRow key={i} venta={v} />
              ))}
            </ul>
          )}
        </div>
      </div>

      <div className="flex justify-end">
        <ActionButton
          onClick={abrirModalCierre}
          className="w-auto rounded-lg bg-red-600 hover:bg-red-700 font-semibold"
        >
          <span className="flex items-center gap-2 justify-center">
            <LockClosedIcon className="w-5 h-5" />
            Cerrar caja
          </span>
        </ActionButton>
      </div>

      <FormModal
        ref={cierreModalRef}
        title="Cerrar caja"
        busy={cerrando}
        submitLabel="Confirmar cierre"
        submitClass="bg-red-600 hover:bg-red-700"
        onSubmit={confirmarCierre}
        onClose={() => {
          setCierreError(null)
        }}
      >
        <div className="mb-3">
          <FormAlert response={cierreError ? { type: 'error', message: cierreError } : null} />
        </div>

        <div className="grid grid-cols-2 gap-3 mb-4 text-sm">
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <span className="block text-xs font-semibold text-gray-600 uppercase">
              Efectivo esperado
            </span>
            <span className="text-lg font-bold text-gray-900">{formatMoney(efectivoEsperado)}</span>
          </div>
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <span className="block text-xs font-semibold text-gray-600 uppercase">Diferencia</span>
            <span
              className={`text-lg font-bold ${
                diferencia === 0 ? 'text-gray-900' : diferencia > 0 ? 'text-emerald-600' : 'text-red-600'
              }`}
            >
              {formatMoney(diferencia)}
            </span>
          </div>
        </div>

        <label className="block text-xs font-semibold text-gray-700 mb-1">
          Efectivo contado *
        </label>
        <input
          type="text"
          inputMode="decimal"
          value={montoFinal}
          onChange={(e) => {
            setCierreError(null)
            setMontoFinal(e.target.value)
          }}
          placeholder="0.00"
          className="w-full px-3 py-2 rounded-lg border border-gray-300 text-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500"
        />
      </FormModal>
    </div>
  )
}

function StatCard({ label, value, tone, bold, isMoney = true }) {
  const toneCls =
    tone === 'emerald' ? 'text-emerald-600' : tone === 'red' ? 'text-red-600' : 'text-gray-900'
  return (
    <div className="bg-white border border-gray-300 shadow rounded-lg p-4">
      <span className="text-xs font-semibold text-gray-600 uppercase">{label}</span>
      <p className={`text-2xl ${bold ? 'font-bold' : 'font-semibold'} ${toneCls} mt-1`}>
        {isMoney ? formatMoney(value) : value}
      </p>
    </div>
  )
}

const rowCell =
  'py-2 text-xs text-center text-gray-700 truncate px-2 flex items-center justify-center'

function VentaRow({ venta }) {
  return (
    <li className={`${GRID_COLS} border-b border-gray-100 hover:bg-gray-50`}>
      <span className={`${rowCell} pl-4`}>{venta.Fecha ?? '-'}</span>
      <span className={rowCell}>{venta.Hora ?? '-'}</span>
      <span className={`${rowCell} capitalize`}>{venta.Forma_Pago ?? '-'}</span>
      <span className={`${rowCell} font-semibold text-emerald-600 pr-4`}>
        {formatMoney(Number(venta.Monto))}
      </span>
    </li>
  )
}
