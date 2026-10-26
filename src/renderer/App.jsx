import { useEffect } from 'react'
import { useAuth } from './context/AuthContext'
import { HashRouter, Routes, Route, Navigate, useNavigate } from 'react-router-dom'
import {
  Login,
  Dashboard,
  Users,
  UserInfo,
  Sales,
  Caja,
  Inventory,
  Categorias,
  Proveedores,
  Configuracion,
  Surtir,
  Historial,
  Reportes,
} from './pages'
import AppLayout from './components/AppLayout'

/**
 * Cierra la sesión y regresa al login cuando el API responde 401 (evento 'sesion-expirada' de api.js).
 *
 * @returns {null} No dibuja nada
 */
function SesionGuard() {
  const { usuario, cerrarSesion } = useAuth()
  const navigate = useNavigate()

  useEffect(() => {
    function handleExpirada() {
      if (!usuario) return
      cerrarSesion()
      navigate('/', { replace: true })
    }
    window.addEventListener('sesion-expirada', handleExpirada)
    return () => window.removeEventListener('sesion-expirada', handleExpirada)
  }, [usuario, cerrarSesion, navigate])

  return null
}

/**
 * Ruta solo para el admin: sin sesión regresa al login y un empleado va al inicio.
 *
 * @param {{children: React.ReactNode}} props
 * @returns {JSX.Element} La página o la redirección
 */
function RutaAdmin({ children }) {
  const { usuario, esAdmin } = useAuth()

  if (!usuario) return <Navigate to="/" replace />
  if (!esAdmin) return <Navigate to="/dashboard" replace />

  return children
}

/**
 * Ruta sin sesión (el login): con la sesión iniciada va al inicio.
 *
 * @param {{children: React.ReactNode}} props
 * @returns {JSX.Element} La página o la redirección
 */
function RutaPublica({ children }) {
  const { usuario } = useAuth()

  return usuario ? <Navigate to="/dashboard" replace /> : children
}

/**
 * Rutas de SGT de escritorio (HashRouter). Las de administración (Historial, Reportes, Categorías,
 * Proveedores, Inventario, Surtir, Usuarios y Configuración) usan RutaAdmin.
 *
 * @returns {JSX.Element} La aplicación
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function App() {
  return (
    <HashRouter>
      <SesionGuard />
      <Routes>
        <Route path="/" element={<RutaPublica><Login /></RutaPublica>} />

        <Route element={<AppLayout />}>
          <Route path="/dashboard"  element={<Dashboard />} />
          <Route path="/sales"      element={<Sales />} />
          <Route path="/user-info"  element={<UserInfo />} />
          <Route path="/caja"       element={<Caja />} />
          <Route path="/historial"  element={<RutaAdmin><Historial /></RutaAdmin>} />
          <Route path="/report"     element={<RutaAdmin><Reportes /></RutaAdmin>} />
          <Route path="/categorias" element={<RutaAdmin><Categorias /></RutaAdmin>} />
          <Route path="/proveedores" element={<RutaAdmin><Proveedores /></RutaAdmin>} />
          <Route path="/inventory"  element={<RutaAdmin><Inventory /></RutaAdmin>} />
          <Route path="/surtir"     element={<RutaAdmin><Surtir /></RutaAdmin>} />
          <Route path="/users"      element={<RutaAdmin><Users /></RutaAdmin>} />
          <Route path="/configuracion" element={<RutaAdmin><Configuracion /></RutaAdmin>} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </HashRouter>
  )
}
