import { forwardRef } from 'react'
import ActionButton from './ActionButton'

/**
 * Diálogo con un formulario y los botones Cancelar y Guardar. Se abre con ref.current.showModal().
 *
 * @param {Object} props
 * @param {string} props.title Título
 * @param {React.ReactNode} props.children Campos del formulario
 * @param {boolean} [props.busy=false] Deshabilita los botones mientras se guarda
 * @param {boolean} [props.canSubmit=true] Si se puede guardar
 * @param {function(): void} [props.onSubmit] Acción de guardar
 * @param {function(): void} [props.onClose] Al cerrar
 * @param {React.Ref<HTMLDialogElement>} ref Referencia al dialog
 * @returns {JSX.Element} El dialog
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
const FormModal = forwardRef(function FormModal(
  {
    title,
    busy = false,
    canSubmit = true,
    submitLabel = 'Guardar',
    submitClass = 'bg-gray-800 hover:bg-gray-900',
    cancelLabel = 'Cancelar',
    onSubmit,
    onClose,
    children,
  },
  ref
) {
  function close() {
    ref.current?.close()
    onClose?.()
  }
  return (
    <dialog ref={ref} className="modal" onClick={(e) => e.stopPropagation()}>
      <div className="modal-box bg-white max-w-lg">
        <h3 className="font-bold text-lg text-gray-900 mb-4">{title}</h3>
        {children}
        <div className="modal-action">
          <button
            className="btn bg-gray-200 hover:bg-gray-300 text-gray-800 border-none"
            onClick={close}
            disabled={busy}
          >
            {cancelLabel}
          </button>
          {onSubmit && (
            <ActionButton
              className={`w-auto px-4 rounded-lg ${submitClass}`}
              onClick={onSubmit}
              disabled={busy || !canSubmit}
            >
              {submitLabel}
            </ActionButton>
          )}
        </div>
      </div>
    </dialog>
  )
})

export default FormModal
