import clsx from 'clsx'
import { NavLink } from 'react-router-dom'
import { useCategorias } from '../catalogo/CategoriasContext'

// Accesos a las categorías bajo el encabezado. Si no cargan, no se muestra: cada página avisa del error
export default function BarraCategorias() {
  const { categorias } = useCategorias()
  if (categorias.length === 0) return null

  return (
    <nav aria-label="Categorías" className="bg-white border-b border-gray-200">
      <ul className="max-w-6xl mx-auto px-6 py-2 flex flex-wrap gap-x-1 gap-y-1 text-sm">
        {categorias.map((categoria) => (
          <li key={categoria.id}>
            <NavLink
              to={`/categoria/${categoria.id}`}
              className={({ isActive }) =>
                clsx(
                  'block px-3 py-1 rounded-full',
                  isActive ? 'bg-emerald-700 text-white' : 'text-gray-700 hover:bg-gray-100'
                )
              }
            >
              {categoria.nombre}
            </NavLink>
          </li>
        ))}
      </ul>
    </nav>
  )
}
