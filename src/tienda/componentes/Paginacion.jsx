import clsx from 'clsx'
import { Link } from 'react-router-dom'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/react/20/solid'
import { paginasVisibles } from '../catalogo/filtros'

const base = 'min-w-9 h-9 px-2 inline-flex items-center justify-center rounded-lg text-sm'

/**
 * Enlaces a las páginas de una lista.
 *
 * @param {Object} props
 * @param {number} props.pagina Página actual
 * @param {number} props.paginas Total de páginas
 * @param {function(number): string} props.enlace Regresa la dirección de la página n
 * @returns {JSX.Element|null} Los enlaces, o nada si hay una sola página
 * @see paginasVisibles
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Paginacion({ pagina, paginas, enlace }) {
  if (paginas <= 1) return null

  return (
    <nav aria-label="Páginas" className="mt-8 flex items-center justify-center gap-1">
      {pagina > 1 ? (
        <Link
          to={enlace(pagina - 1)}
          className={clsx(base, 'text-gray-700 hover:bg-gray-100')}
          aria-label="Página anterior"
        >
          <ChevronLeftIcon className="w-5 h-5" aria-hidden="true" />
          Anterior
        </Link>
      ) : null}

      {paginasVisibles(pagina, paginas).map((n, i) =>
        n === '…' ? (
          <span key={`salto-${i}`} className={clsx(base, 'text-gray-400')}>
            …
          </span>
        ) : (
          <Link
            key={n}
            to={enlace(n)}
            aria-current={n === pagina ? 'page' : undefined}
            aria-label={`Página ${n}`}
            className={clsx(
              base,
              n === pagina ? 'bg-emerald-700 text-white' : 'text-gray-700 hover:bg-gray-100'
            )}
          >
            {n}
          </Link>
        )
      )}

      {pagina < paginas ? (
        <Link
          to={enlace(pagina + 1)}
          className={clsx(base, 'text-gray-700 hover:bg-gray-100')}
          aria-label="Página siguiente"
        >
          Siguiente
          <ChevronRightIcon className="w-5 h-5" aria-hidden="true" />
        </Link>
      ) : null}
    </nav>
  )
}
