import { useEffect } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import BarraCategorias from './BarraCategorias'
import Encabezado from './Encabezado'
import Pie from './Pie'

// Estructura de todas las páginas de la tienda
export default function Diseno() {
  const { pathname, search } = useLocation()

  // Cada página nueva (o página de resultados) empieza arriba
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [pathname, search])

  return (
    <div className="min-h-screen flex flex-col">
      <Encabezado />
      <BarraCategorias />
      <main className="flex-1 w-full max-w-6xl mx-auto px-6 py-8">
        <Outlet />
      </main>
      <Pie />
    </div>
  )
}
