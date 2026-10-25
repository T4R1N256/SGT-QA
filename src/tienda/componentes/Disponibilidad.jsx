import clsx from 'clsx'
import { CheckCircleIcon, ExclamationTriangleIcon, XCircleIcon } from '@heroicons/react/20/solid'
import { DISPONIBILIDAD } from '../catalogo/disponibilidad'

const ICONOS = {
  disponible: CheckCircleIcon,
  pocas_piezas: ExclamationTriangleIcon,
  agotado: XCircleIcon,
}

// Etiqueta de disponibilidad: icono y texto, no solo color
export default function Disponibilidad({ valor }) {
  const { etiqueta, clase } = DISPONIBILIDAD[valor] ?? DISPONIBILIDAD.agotado
  const Icono = ICONOS[valor] ?? XCircleIcon

  return (
    <span
      className={clsx(
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
        clase
      )}
    >
      <Icono className="w-3.5 h-3.5" aria-hidden="true" />
      {etiqueta}
    </span>
  )
}
