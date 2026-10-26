import clsx from 'clsx'
import { forwardRef, useId } from 'react'

/**
 * Campo de formulario con su etiqueta y su mensaje de error, ligados para los lectores de pantalla.
 *
 * @param {Object} props
 * @param {string} props.etiqueta Etiqueta
 * @param {string} [props.error] Mensaje de error
 * @param {string} [props.ayuda] Texto de ayuda (se muestra si no hay error)
 * @param {'input'|'select'|'textarea'} [props.como='input'] Control (select con sus <option> como children)
 * @param {React.Ref} ref Referencia al control (para enfocarlo)
 * @returns {JSX.Element} El campo
 * @see useFormulario
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
const Campo = forwardRef(function Campo(
  { etiqueta, error, ayuda, className, como: Control = 'input', ...props },
  ref
) {
  const id = useId()
  const idMensaje = `${id}-mensaje`

  return (
    <div className={className}>
      <label htmlFor={id} className="block text-sm font-medium text-gray-800 mb-1">
        {etiqueta}
      </label>
      <Control
        ref={ref}
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={error || ayuda ? idMensaje : undefined}
        className={clsx(
          'w-full px-3 py-2 rounded-lg border bg-white text-sm focus:outline-none focus:ring-2',
          error
            ? 'border-red-500 focus:ring-red-200'
            : 'border-gray-300 focus:border-emerald-600 focus:ring-emerald-100'
        )}
        {...props}
      />
      {error ? (
        <p id={idMensaje} className="mt-1 text-xs text-red-700">
          {error}
        </p>
      ) : (
        ayuda && (
          <p id={idMensaje} className="mt-1 text-xs text-gray-500">
            {ayuda}
          </p>
        )
      )}
    </div>
  )
})

export default Campo
