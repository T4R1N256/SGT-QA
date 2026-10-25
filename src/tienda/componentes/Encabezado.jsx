import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { MagnifyingGlassIcon, ShoppingCartIcon, UserCircleIcon } from '@heroicons/react/24/outline'
import { useTienda } from '../TiendaContext'
import { useCarrito } from '../carrito/CarritoContext'
import { useSesion } from '../cuenta/SesionContext'

// Encabezado de todas las páginas: nombre de la tienda, búsqueda, carrito y cuenta
export default function Encabezado() {
  const { nombre } = useTienda()
  const { piezas } = useCarrito()
  const { cliente } = useSesion()
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const [params] = useSearchParams()
  const q = pathname === '/buscar' ? (params.get('q') ?? '') : ''
  const [busqueda, setBusqueda] = useState(q)

  // El cuadro muestra lo que se buscó, también al usar Atrás o Adelante
  useEffect(() => {
    setBusqueda(q)
  }, [q])

  function buscar(e) {
    e.preventDefault()
    const texto = busqueda.trim()
    navigate(texto ? `/buscar?q=${encodeURIComponent(texto)}` : '/buscar')
  }

  return (
    <header className="bg-white border-b border-gray-200">
      <div className="max-w-6xl mx-auto px-6 h-16 flex items-center gap-6">
        <Link to="/" className="text-xl font-bold text-emerald-800 whitespace-nowrap">
          {nombre}
        </Link>

        <form onSubmit={buscar} role="search" className="flex-1 flex">
          <label htmlFor="busqueda" className="sr-only">
            Buscar productos
          </label>
          <input
            id="busqueda"
            type="search"
            value={busqueda}
            onChange={(e) => setBusqueda(e.target.value)}
            placeholder="Buscar productos"
            maxLength={100}
            className="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg text-sm focus:outline-none focus:border-emerald-600"
          />
          <button
            type="submit"
            className="px-4 bg-emerald-700 hover:bg-emerald-800 text-white rounded-r-lg"
            aria-label="Buscar"
          >
            <MagnifyingGlassIcon className="w-5 h-5" />
          </button>
        </form>

        <nav className="flex items-center gap-5 text-sm text-gray-700">
          <Link
            to="/carrito"
            aria-label={
              piezas ? `Carrito: ${piezas} ${piezas === 1 ? 'pieza' : 'piezas'}` : 'Carrito'
            }
            className="relative flex items-center gap-1.5 hover:text-emerald-800"
          >
            <ShoppingCartIcon className="w-6 h-6" aria-hidden="true" />
            Carrito
            {piezas > 0 && (
              <span
                aria-hidden="true"
                className="min-w-5 h-5 px-1 rounded-full bg-emerald-700 text-white text-xs font-semibold inline-flex items-center justify-center"
              >
                {piezas > 99 ? '99+' : piezas}
              </span>
            )}
          </Link>
          <Link
            to={cliente ? '/cuenta' : '/ingresar'}
            className="flex items-center gap-1.5 hover:text-emerald-800"
          >
            <UserCircleIcon className="w-6 h-6" aria-hidden="true" />
            {cliente ? `Hola, ${cliente.nombre}` : 'Ingresar'}
          </Link>
        </nav>
      </div>
    </header>
  )
}
