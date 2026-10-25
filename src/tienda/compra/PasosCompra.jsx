import clsx from 'clsx'
import { CheckIcon } from '@heroicons/react/20/solid'

const PASOS = ['Carrito', 'Entrega', 'Resumen', 'Pago']

// Dónde va el cliente en la compra: carrito → datos de entrega → resumen → pago
export default function PasosCompra({ actual }) {
  const indice = PASOS.indexOf(actual)

  return (
    <nav aria-label="Pasos de la compra" className="mb-6">
      <ol className="flex flex-wrap items-center gap-2 text-sm">
        {PASOS.map((paso, i) => (
          <li key={paso} className="flex items-center gap-2">
            {i > 0 && <span aria-hidden="true" className="w-6 h-px bg-gray-300" />}
            <span
              aria-current={i === indice ? 'step' : undefined}
              className={clsx(
                'inline-flex items-center gap-1.5',
                i === indice
                  ? 'font-semibold text-emerald-800'
                  : i < indice
                    ? 'text-gray-700'
                    : 'text-gray-400'
              )}
            >
              <span
                className={clsx(
                  'w-6 h-6 rounded-full inline-flex items-center justify-center text-xs',
                  i < indice
                    ? 'bg-emerald-700 text-white'
                    : i === indice
                      ? 'border-2 border-emerald-700'
                      : 'border border-gray-300'
                )}
              >
                {i < indice ? <CheckIcon className="w-4 h-4" aria-hidden="true" /> : i + 1}
              </span>
              {paso}
            </span>
          </li>
        ))}
      </ol>
    </nav>
  )
}
