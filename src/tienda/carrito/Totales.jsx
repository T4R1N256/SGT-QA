import clsx from 'clsx'
import { formatoMoneda } from '../../compartido/format'
import { cantidadConUnidad } from '../catalogo/disponibilidad'

/**
 * Totales con el desglose del IVA (R09, R10). Los precios ya lo incluyen (D2) y el envío no se cobra (D3).
 *
 * @param {Object} props
 * @param {Object} props.resumen Resumen del servidor (subtotal, iva, tasa_iva, envio, total)
 * @param {boolean} [props.actualizando=false] Atenúa los montos mientras llega la revisión
 * @param {string} [props.titulo='Resumen'] Título
 * @param {React.ReactNode} props.children Acciones del paso (continuar, confirmar, pagar)
 * @returns {JSX.Element} Los totales
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Totales({ resumen, actualizando = false, titulo = 'Resumen', children }) {
  return (
    <aside
      aria-label="Resumen de la compra"
      className="rounded-xl border border-gray-200 bg-white p-6"
    >
      <h2 className="font-semibold text-gray-900">{titulo}</h2>
      <dl
        className={clsx('mt-4 space-y-2 text-sm', actualizando && 'opacity-60')}
        aria-busy={actualizando}
      >
        <div className="flex justify-between">
          <dt className="text-gray-600">Productos</dt>
          <dd>{cantidadConUnidad(resumen.piezas, 'pieza')}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-gray-600">Subtotal</dt>
          <dd>{formatoMoneda(resumen.subtotal)}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-gray-600">
            IVA{resumen.tasa_iva != null && ` (${resumen.tasa_iva} %)`}
          </dt>
          <dd>{formatoMoneda(resumen.iva)}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-gray-600">Envío</dt>
          <dd className="text-emerald-800 font-medium">Gratis</dd>
        </div>
        <div className="flex justify-between border-t border-gray-200 pt-3 text-base font-semibold text-gray-900">
          <dt>Total</dt>
          <dd>{formatoMoneda(resumen.total)}</dd>
        </div>
      </dl>
      <p className="mt-2 text-xs text-gray-500">Los precios ya incluyen IVA.</p>
      {children}
    </aside>
  )
}
