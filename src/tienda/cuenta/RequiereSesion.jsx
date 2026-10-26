import { Navigate, Outlet, useLocation } from 'react-router-dom'
import Cargando from '../componentes/Cargando'
import { useSesion } from './SesionContext'

/**
 * Rutas que necesitan una cuenta (Mi cuenta y los pasos de la compra, D5).
 * Sin sesión lleva a Ingresar y, al entrar, regresa a la misma página (P7).
 *
 * @returns {JSX.Element} La página, la espera o la redirección
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function RequiereSesion() {
  const { cliente, cargando } = useSesion()
  const { pathname, search } = useLocation()

  if (cargando) return <Cargando />
  if (!cliente)
    return <Navigate to={`/ingresar?siguiente=${encodeURIComponent(pathname + search)}`} replace />
  return <Outlet />
}
