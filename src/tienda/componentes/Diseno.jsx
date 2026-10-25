import { Outlet } from 'react-router-dom'
import Encabezado from './Encabezado'
import Pie from './Pie'

// Estructura de todas las páginas de la tienda
export default function Diseno() {
  return (
    <div className="min-h-screen flex flex-col">
      <Encabezado />
      <main className="flex-1 w-full max-w-6xl mx-auto px-6 py-8">
        <Outlet />
      </main>
      <Pie />
    </div>
  )
}
