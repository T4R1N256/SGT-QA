import { Link } from 'react-router-dom'
import { obtenerPedidos } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { formatoMoneda } from '../../compartido/format'
import { cantidadConUnidad } from '../catalogo/disponibilidad'
import Cargando from '../componentes/Cargando'
import ErrorCarga from '../componentes/ErrorCarga'
import { ESTADOS_PEDIDO } from '../compra/validaciones'

// Mis pedidos (T-S6): los pendientes de pago se pueden retomar desde aquí
export default function MisPedidos() {
  const { datos, error, reintentar } = useConsulta(obtenerPedidos)

  if (error) return <ErrorCarga mensaje={error.mensaje} onReintentar={reintentar} />
  if (!datos) return <Cargando />
  if (datos.pedidos.length === 0)
    return <p className="mt-2 text-sm text-gray-600">Aún no tienes pedidos.</p>

  return (
    <table className="mt-3 w-full text-sm bg-white rounded-xl border border-gray-200 overflow-hidden">
      <caption className="sr-only">Mis pedidos</caption>
      <thead className="bg-gray-50 text-left text-gray-600">
        <tr>
          <th scope="col" className="px-4 py-3 font-medium">
            Folio
          </th>
          <th scope="col" className="px-4 py-3 font-medium">
            Fecha
          </th>
          <th scope="col" className="px-4 py-3 font-medium">
            Productos
          </th>
          <th scope="col" className="px-4 py-3 font-medium text-right">
            Total
          </th>
          <th scope="col" className="px-4 py-3 font-medium">
            Estado
          </th>
          <th scope="col" className="px-4 py-3">
            <span className="sr-only">Acciones</span>
          </th>
        </tr>
      </thead>
      <tbody className="divide-y divide-gray-100">
        {datos.pedidos.map((pedido) => (
          <tr key={pedido.folio}>
            <td className="px-4 py-3 font-medium text-gray-900">{pedido.folio}</td>
            <td className="px-4 py-3 whitespace-nowrap">
              {pedido.fecha} {pedido.hora}
            </td>
            <td className="px-4 py-3">{cantidadConUnidad(pedido.piezas, 'pieza')}</td>
            <td className="px-4 py-3 text-right whitespace-nowrap">
              {formatoMoneda(pedido.total)}
            </td>
            <td className="px-4 py-3 text-amber-800">
              {ESTADOS_PEDIDO[pedido.estado] ?? pedido.estado}
            </td>
            <td className="px-4 py-3 text-right">
              {pedido.estado === 'pendiente_pago' && (
                <Link
                  to={`/compra/pago/${encodeURIComponent(pedido.folio)}`}
                  aria-label={`Ir al pago del pedido ${pedido.folio}`}
                  className="text-emerald-800 font-medium hover:underline whitespace-nowrap"
                >
                  Ir al pago
                </Link>
              )}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
