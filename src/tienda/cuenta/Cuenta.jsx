import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useTitulo } from '../TiendaContext'
import Cargando from '../componentes/Cargando'
import MisPedidos from './MisPedidos'
import { useSesion } from './SesionContext'

/**
 * Mi cuenta: datos del cliente y cierre de sesión. Sin sesión lleva a Ingresar.
 *
 * @returns {JSX.Element} La página
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Cuenta() {
  const { cliente, cargando, salir } = useSesion()
  const [saliendo, setSaliendo] = useState(false)
  const [error, setError] = useState(null)
  const navigate = useNavigate()
  useTitulo('Mi cuenta')

  if (cargando) return <Cargando />
  if (!cliente && !saliendo) return <Navigate to="/ingresar?siguiente=%2Fcuenta" replace />

  async function cerrarSesion() {
    setSaliendo(true)
    setError(null)
    const res = await salir()
    if (res.ok) navigate('/', { replace: true })
    else {
      setSaliendo(false)
      setError(res.mensaje)
    }
  }

  return (
    <section className="max-w-4xl">
      <h1 className="text-2xl font-bold text-gray-900">Mi cuenta</h1>

      {cliente && (
        <dl className="mt-6 grid gap-4 sm:grid-cols-2 rounded-xl border border-gray-200 bg-white p-6 text-sm">
          <div>
            <dt className="text-gray-500">Nombre</dt>
            <dd className="mt-0.5 font-medium text-gray-900">
              {cliente.nombre} {cliente.apellidos}
            </dd>
          </div>
          <div>
            <dt className="text-gray-500">Correo</dt>
            <dd className="mt-0.5 font-medium text-gray-900">{cliente.correo}</dd>
          </div>
          <div>
            <dt className="text-gray-500">Teléfono</dt>
            <dd className="mt-0.5 font-medium text-gray-900">{cliente.telefono}</dd>
          </div>
        </dl>
      )}

      <h2 className="mt-8 text-lg font-semibold text-gray-900">Mis pedidos</h2>
      {cliente && <MisPedidos />}

      <button
        type="button"
        onClick={cerrarSesion}
        disabled={saliendo}
        className="mt-8 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 disabled:opacity-60"
      >
        {saliendo ? 'Cerrando sesión…' : 'Cerrar sesión'}
      </button>
      {error && (
        <p role="alert" className="mt-2 text-sm text-red-700">
          {error}
        </p>
      )}
    </section>
  )
}
