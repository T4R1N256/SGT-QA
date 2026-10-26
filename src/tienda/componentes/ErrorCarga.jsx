import { ExclamationTriangleIcon } from '@heroicons/react/24/outline'

/**
 * El API no respondió o falló (P15): se explica y se puede reintentar, sin dejar la pantalla en blanco.
 *
 * @param {Object} props
 * @param {string} props.mensaje Mensaje del error
 * @param {function(): void} props.onReintentar Vuelve a pedir los datos
 * @returns {JSX.Element} El aviso
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function ErrorCarga({ mensaje, onReintentar }) {
  return (
    <div
      role="alert"
      className="rounded-xl border border-amber-200 bg-amber-50 px-6 py-8 text-center"
    >
      <ExclamationTriangleIcon className="mx-auto w-8 h-8 text-amber-600" aria-hidden="true" />
      <p className="mt-2 font-medium text-gray-900">No se pudo cargar esta sección</p>
      <p className="mt-1 text-sm text-gray-600">{mensaje}</p>
      {onReintentar && (
        <button
          type="button"
          onClick={onReintentar}
          className="mt-4 px-4 py-2 rounded-lg bg-emerald-700 text-white text-sm hover:bg-emerald-800"
        >
          Reintentar
        </button>
      )}
    </div>
  )
}
