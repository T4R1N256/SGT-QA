import clsx from 'clsx'
import { Link } from 'react-router-dom'
import { formatoMoneda } from '../../compartido/format'
import Disponibilidad from './Disponibilidad'

/**
 * Producto en una lista: lleva a su página de detalle.
 *
 * @param {{producto: Object}} props Producto público del API
 * @returns {JSX.Element} La tarjeta
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function TarjetaProducto({ producto }) {
  const agotado = producto.disponibilidad === 'agotado'

  return (
    <Link
      to={`/producto/${producto.id}`}
      className="group h-full flex flex-col rounded-xl border border-gray-200 bg-white p-4 hover:border-emerald-600 hover:shadow-sm"
    >
      <span className="text-xs text-gray-500">{producto.categoria.nombre}</span>
      <h3 className="mt-1 font-medium text-gray-900 group-hover:text-emerald-800">
        {producto.nombre}
      </h3>
      <div className="mt-auto pt-4 flex flex-wrap items-center justify-between gap-2">
        <span
          className={clsx('text-lg font-semibold', agotado ? 'text-gray-400' : 'text-gray-900')}
        >
          {formatoMoneda(producto.precio)}
        </span>
        <Disponibilidad valor={producto.disponibilidad} />
      </div>
    </Link>
  )
}
