import { Outlet, Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { HeaderProvider } from '../context/HeaderContext'
import SideNav from './SideNav'
import PageHeader from './PageHeader'

/**
 * Estructura de las páginas con sesión: menú lateral, encabezado y la página actual.
 * Sin sesión regresa al login.
 *
 * @returns {JSX.Element} El diseño o la redirección
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function AppLayout() {
  const { usuario } = useAuth()

  if (!usuario) return <Navigate to="/" replace />

  return (
    <HeaderProvider>
      <div className="flex h-screen font-sans bg-gray-200">
        <SideNav />
        <div className="flex-1 flex flex-col overflow-hidden">
          <PageHeader />
          <main className="h-full flex-1 overflow-auto flex flex-col p-4">
            <Outlet />
          </main>
        </div>
      </div>
    </HeaderProvider>
  )
}
