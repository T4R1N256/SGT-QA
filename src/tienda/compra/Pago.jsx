import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  BanknotesIcon,
  BuildingLibraryIcon,
  CreditCardIcon,
  InformationCircleIcon,
} from '@heroicons/react/24/outline'
import { obtenerPedido, pagarPedido } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useTitulo } from '../TiendaContext'
import { formatoMoneda } from '../../compartido/format'
import Totales from '../carrito/Totales'
import Cargando from '../componentes/Cargando'
import ErrorCarga from '../componentes/ErrorCarga'
import NoEncontrada from '../componentes/NoEncontrada'
import DatosEntrega from './DatosEntrega'
import PasosCompra from './PasosCompra'
import TablaProductos from './TablaProductos'
import { ESTADOS_PEDIDO } from './validaciones'

const METODOS = [
  { valor: 'tarjeta', etiqueta: 'Tarjeta de crédito o débito', Icono: CreditCardIcon },
  { valor: 'transferencia', etiqueta: 'Transferencia bancaria (SPEI)', Icono: BuildingLibraryIcon },
  { valor: 'efectivo', etiqueta: 'Efectivo en tienda de conveniencia', Icono: BanknotesIcon },
]

/**
 * Paso 4 (N11, R14, D6): métodos de pago y el botón "Pagar", que avisa que el pago en línea no está
 * disponible. No hay campos de tarjeta ni conexión con bancos o pasarelas; el pedido sigue pendiente de pago.
 *
 * @returns {JSX.Element} La página
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Pago() {
  const { folio } = useParams()
  const { datos, error, reintentar } = useConsulta(obtenerPedido, folio)
  const [metodo, setMetodo] = useState('')
  const [pagando, setPagando] = useState(false)
  const [respuesta, setRespuesta] = useState(null)
  useTitulo(`Pago del pedido ${folio}`)

  if (error?.status === 404) {
    return (
      <NoEncontrada titulo="Pedido no encontrado" texto="No encontramos ese pedido en tu cuenta." />
    )
  }
  if (error) return <ErrorCarga mensaje={error.mensaje} onReintentar={reintentar} />
  if (!datos) return <Cargando />

  const { pedido } = datos

  async function pagar(e) {
    e.preventDefault()
    if (pagando) return
    if (!metodo) return setRespuesta({ tipo: 'error', mensaje: 'Elige un método de pago' })
    setPagando(true)
    setRespuesta(null)
    const res = await pagarPedido(pedido.folio, metodo)
    setPagando(false)
    setRespuesta(
      res.ok ? { tipo: 'aviso', mensaje: res.mensaje } : { tipo: 'error', mensaje: res.mensaje }
    )
  }

  return (
    <section>
      <PasosCompra actual="Pago" />
      <h1 className="text-2xl font-bold text-gray-900">Pedido {pedido.folio}</h1>
      <p className="mt-1 text-sm text-gray-600">
        Registrado el {pedido.fecha} a las {pedido.hora} ·{' '}
        <span className="font-medium text-amber-800">
          {ESTADOS_PEDIDO[pedido.estado] ?? pedido.estado}
        </span>
      </p>

      <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_20rem] items-start">
        <div className="space-y-6">
          <form
            onSubmit={pagar}
            noValidate
            className="rounded-xl border border-gray-200 bg-white p-6"
          >
            <fieldset>
              <legend className="font-semibold text-gray-900">Método de pago</legend>
              <div className="mt-4 grid gap-3">
                {METODOS.map(({ valor, etiqueta, Icono }) => (
                  <label
                    key={valor}
                    className="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 cursor-pointer hover:bg-gray-50 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50"
                  >
                    <input
                      type="radio"
                      name="metodo"
                      value={valor}
                      checked={metodo === valor}
                      onChange={() => {
                        setMetodo(valor)
                        setRespuesta(null)
                      }}
                      className="accent-emerald-700"
                    />
                    <Icono className="w-5 h-5 text-gray-500" aria-hidden="true" />
                    <span className="text-sm text-gray-800">{etiqueta}</span>
                  </label>
                ))}
              </div>
            </fieldset>
            <p className="mt-4 text-xs text-gray-500">
              No se piden ni se guardan datos de tarjeta.
            </p>

            <button
              type="submit"
              disabled={pagando}
              className="mt-4 w-full py-2.5 rounded-lg bg-emerald-700 text-white font-medium hover:bg-emerald-800 disabled:opacity-60"
            >
              {pagando ? 'Procesando…' : `Pagar ${formatoMoneda(pedido.resumen.total)}`}
            </button>

            {respuesta && (
              <div
                role="alert"
                className={
                  respuesta.tipo === 'aviso'
                    ? 'mt-4 flex gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900'
                    : 'mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800'
                }
              >
                {respuesta.tipo === 'aviso' && (
                  <InformationCircleIcon className="w-5 h-5 shrink-0" aria-hidden="true" />
                )}
                <div>
                  <p>{respuesta.mensaje}</p>
                  {respuesta.tipo === 'aviso' && (
                    <Link to="/cuenta" className="mt-1 inline-block font-medium underline">
                      Ver mis pedidos
                    </Link>
                  )}
                </div>
              </div>
            )}
          </form>

          <div>
            <h2 className="font-semibold text-gray-900 mb-3">Productos</h2>
            <TablaProductos lineas={pedido.lineas} />
          </div>
          <div className="rounded-xl border border-gray-200 bg-white p-6">
            <h2 className="font-semibold text-gray-900 mb-3">Entrega</h2>
            <DatosEntrega entrega={pedido.entrega} />
          </div>
        </div>

        <Totales resumen={pedido.resumen} titulo="Total a pagar" />
      </div>
    </section>
  )
}
