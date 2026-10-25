import { MinusIcon, PlusIcon } from '@heroicons/react/20/solid'

// Cantidad con botones − y +. El texto se valida afuera: aquí se puede escribir cualquier cosa (P2)
export default function SelectorCantidad({
  id,
  valor,
  onCambiar,
  minimo = 1,
  maximo,
  etiqueta,
  invalido,
  descripcion,
}) {
  const numero = Number(valor)
  const entero = /^\d+$/.test(String(valor).trim())

  return (
    <div className="inline-flex items-stretch rounded-lg border border-gray-300 bg-white">
      <button
        type="button"
        onClick={() => onCambiar(String(numero - 1))}
        disabled={!entero || numero <= minimo}
        aria-label={`Quitar uno de ${etiqueta}`}
        className="px-2 text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:hover:bg-transparent rounded-l-lg"
      >
        <MinusIcon className="w-4 h-4" aria-hidden="true" />
      </button>
      <input
        id={id}
        type="text"
        inputMode="numeric"
        value={valor}
        onChange={(e) => onCambiar(e.target.value)}
        aria-label={`Cantidad de ${etiqueta}`}
        aria-invalid={invalido || undefined}
        aria-describedby={descripcion}
        className="w-14 text-center text-sm border-x border-gray-300 py-1.5 focus:outline-none focus:bg-emerald-50 aria-invalid:bg-red-50"
      />
      <button
        type="button"
        onClick={() => onCambiar(String(numero + 1))}
        disabled={!entero || numero >= maximo}
        aria-label={`Agregar uno de ${etiqueta}`}
        className="px-2 text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:hover:bg-transparent rounded-r-lg"
      >
        <PlusIcon className="w-4 h-4" aria-hidden="true" />
      </button>
    </div>
  )
}
