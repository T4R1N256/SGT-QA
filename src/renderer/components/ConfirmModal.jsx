import { forwardRef } from 'react'
import { ExclamationCircleIcon, CheckCircleIcon } from '@heroicons/react/20/solid'

const VARIANTS = {
  danger: { Icon: ExclamationCircleIcon, iconClass: 'text-red-500' },
  success: { Icon: CheckCircleIcon, iconClass: 'text-green-600' },
}

/**
 * Diálogo para confirmar una acción (por ejemplo, eliminar) o avisar que se hizo.
 * Se abre con ref.current.showModal().
 *
 * @param {Object} props
 * @param {string} props.title Título
 * @param {React.ReactNode} props.children Mensaje
 * @param {string} [props.error] Error de la última acción
 * @param {boolean} [props.busy=false] Deshabilita los botones mientras se procesa
 * @param {'danger'|'success'} [props.variant='danger'] Icono y color
 * @param {function(): void} [props.onConfirm] Acción al confirmar (sin ella solo hay botón para cerrar)
 * @param {function(): void} [props.onClose] Al cerrar
 * @param {React.Ref<HTMLDialogElement>} ref Referencia al dialog
 * @returns {JSX.Element} El dialog
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
const ConfirmModal = forwardRef(function ConfirmModal(
  {
    title,
    children,
    error,
    busy = false,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    busyLabel = 'Procesando...',
    confirmClass = 'bg-red-600 hover:bg-red-700',
    variant = 'danger',
    onConfirm,
    onClose,
  },
  ref
) {
  const { Icon, iconClass } = VARIANTS[variant]

  function close() {
    ref.current?.close()
    onClose?.()
  }

  return (
    <dialog ref={ref} className="modal" onClick={(e) => e.stopPropagation()}>
      <div className="modal-box bg-white">
        <div className="flex items-center gap-2 mb-2">
          <Icon className={`w-6 shrink-0 ${iconClass}`} />
          <h3 className="font-bold text-lg text-gray-900">{title}</h3>
        </div>
        <div className="text-gray-600 text-sm">{children}</div>
        {error && (
          <p className="mt-3 text-sm text-red-600 bg-red-50 border border-red-300 rounded-lg px-3 py-2">
            {error}
          </p>
        )}
        <div className="modal-action">
          <button
            className="btn bg-gray-200 hover:bg-gray-300 text-gray-800 border-none"
            onClick={close}
            disabled={busy}
          >
            {cancelLabel}
          </button>
          {onConfirm && (
            <button
              className={`btn ${confirmClass} text-white border-none`}
              onClick={onConfirm}
              disabled={busy}
            >
              {busy ? busyLabel : confirmLabel}
            </button>
          )}
        </div>
      </div>
    </dialog>
  )
})

export default ConfirmModal
