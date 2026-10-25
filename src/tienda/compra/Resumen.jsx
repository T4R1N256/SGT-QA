import { useRef, useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { confirmarPedido, obtenerEntrega } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useTitulo } from '../TiendaContext'
import Avisos from '../carrito/Avisos'
import { useCarrito } from '../carrito/CarritoContext'
import Totales from '../carrito/Totales'
import { useRevisionCarrito } from '../carrito/useRevisionCarrito'
import Cargando from '../componentes/Cargando'
import ErrorCarga from '../componentes/ErrorCarga'
import DatosEntrega from './DatosEntrega'
import PasosCompra from './PasosCompra'
import TablaProductos from './TablaProductos'
import { nuevaClave } from './validaciones'

// Paso 3 (N9, N10, R13): resumen final con productos, importes, IVA, total y datos de entrega.
// Se puede corregir la entrega o el carrito. Al confirmar se crea el pedido "pendiente de pago" (D4)
export default function Resumen() {
  const { lineas, vaciar } = useCarrito()
  const revision = useRevisionCarrito()
  const entrega = useConsulta(obtenerEntrega)
  const navigate = useNavigate()
  const [confirmando, setConfirmando] = useState(false)
  const [error, setError] = useState(null)
  // Una clave por resumen: si "Confirmar" llega dos veces al servidor, no se duplica el pedido (P12)
  const clave = useRef(nuevaClave())
  // Al confirmar se vacía el carrito y se va al pago: el carrito vacío no debe mandar de regreso al carrito
  const [folio, setFolio] = useState(null)
  useTitulo('Resumen de la compra')

  if (folio) return <Navigate to={`/compra/pago/${encodeURIComponent(folio)}`} replace />
  // P6/P7: sin productos o sin datos de entrega no hay resumen; se regresa al paso que falta
  if (lineas.length === 0) return <Navigate to="/carrito" replace />
  if (entrega.datos && !entrega.datos.confirmada) return <Navigate to="/compra/entrega" replace />

  async function confirmar() {
    if (confirmando) return
    setConfirmando(true)
    setError(null)
    const res = await confirmarPedido(lineas, clave.current)
    setConfirmando(false)

    if (res.ok) {
      setFolio(res.folio)
      vaciar()
    } else if (res.avisos) {
      // T-S5: el carrito cambió (existencias, precios, bajas): el cliente lo ve antes de confirmar otra vez
      revision.aplicar(res)
    } else if (res.paso === 'entrega') {
      navigate('/compra/entrega', { replace: true })
    } else if (res.status === 401) {
      navigate(`/ingresar?siguiente=${encodeURIComponent('/compra/resumen')}`)
    } else {
      setError(res.mensaje)
    }
  }

  const error_ = revision.error ?? entrega.error
  const listo = revision.datos && entrega.datos

  return (
    <section>
      <PasosCompra actual="Resumen" />
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Resumen de tu compra</h1>
      <Avisos avisos={revision.avisos} />

      {error_ ? (
        <ErrorCarga
          mensaje={error_.mensaje}
          onReintentar={() => {
            revision.reintentar()
            entrega.reintentar()
          }}
        />
      ) : !listo ? (
        <Cargando />
      ) : (
        <div className="grid gap-8 lg:grid-cols-[1fr_20rem] items-start">
          <div className="space-y-6">
            <div>
              <div className="flex items-baseline justify-between mb-3">
                <h2 className="font-semibold text-gray-900">Productos</h2>
                <Link to="/carrito" className="text-sm text-emerald-800 hover:underline">
                  Modificar carrito
                </Link>
              </div>
              <TablaProductos lineas={revision.datos.lineas} />
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-6">
              <div className="flex items-baseline justify-between mb-3">
                <h2 className="font-semibold text-gray-900">Entrega</h2>
                <Link to="/compra/entrega" className="text-sm text-emerald-800 hover:underline">
                  Corregir datos
                </Link>
              </div>
              <DatosEntrega entrega={entrega.datos.entrega} />
            </div>
          </div>

          <Totales resumen={revision.datos.resumen} actualizando={revision.cargando}>
            <button
              type="button"
              onClick={confirmar}
              disabled={confirmando || revision.cargando}
              className="mt-5 w-full py-2.5 rounded-lg bg-emerald-700 text-white font-medium hover:bg-emerald-800 disabled:opacity-60"
            >
              {confirmando ? 'Confirmando…' : 'Confirmar pedido'}
            </button>
            <p className="mt-2 text-xs text-gray-500">
              Tu pedido queda pendiente de pago. Las existencias no se apartan hasta pagar.
            </p>
            {error && (
              <p role="alert" className="mt-2 text-sm text-red-700">
                {error}
              </p>
            )}
          </Totales>
        </div>
      )}
    </section>
  )
}
