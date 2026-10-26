import { ExclamationTriangleIcon } from '@heroicons/react/24/outline'

/**
 * Avisos del servidor al revisar el carrito: productos retirados o agotados, cantidades ajustadas
 * y precios que cambiaron.
 *
 * @param {{avisos: Array<{id: number, tipo: string, mensaje: string}>}} props
 * @returns {JSX.Element|null} Los avisos, o nada si no hay
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Avisos({ avisos }) {
  if (avisos.length === 0) return null
  return (
    <div role="alert" className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
      <p className="flex items-center gap-2 font-medium text-amber-900">
        <ExclamationTriangleIcon className="w-5 h-5" aria-hidden="true" />
        Revisamos tu carrito
      </p>
      <ul className="mt-2 list-disc pl-6 text-sm text-amber-900 space-y-1">
        {avisos.map((aviso, i) => (
          <li key={`${aviso.id}-${aviso.tipo}-${i}`}>{aviso.mensaje}</li>
        ))}
      </ul>
    </div>
  )
}
