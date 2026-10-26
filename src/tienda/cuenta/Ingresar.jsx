import { Link, Navigate, useSearchParams } from 'react-router-dom'
import { useTitulo } from '../TiendaContext'
import Cargando from '../componentes/Cargando'
import Campo from './Campo'
import { useSesion } from './SesionContext'
import { useFormulario } from './useFormulario'
import { destinoSeguro, validarIngreso } from './validaciones'

/**
 * Inicio de sesión del cliente. Con ?siguiente= regresa a donde iba, por ejemplo, a la compra (T-S2).
 *
 * @returns {JSX.Element} La página
 * @see destinoSeguro
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Ingresar() {
  const { cliente, cargando, ingresar } = useSesion()
  const [params] = useSearchParams()
  const siguiente = destinoSeguro(params.get('siguiente'))
  const form = useFormulario({ correo: '', password: '' }, validarIngreso)
  useTitulo('Ingresar')

  if (cargando) return <Cargando />
  if (cliente) return <Navigate to={siguiente} replace />

  // Al ingresar, la sesión ya tiene cliente y el <Navigate> de arriba regresa a donde iba
  const enviar = form.enviar(ingresar)

  return (
    <section className="max-w-md mx-auto">
      <h1 className="text-2xl font-bold text-gray-900">Ingresar</h1>
      {siguiente.startsWith('/compra') && (
        <p className="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
          Inicia sesión o crea una cuenta para continuar con tu compra. Tu carrito se conserva.
        </p>
      )}

      <form
        onSubmit={enviar}
        noValidate
        className="mt-6 space-y-4 rounded-xl border border-gray-200 bg-white p-6"
      >
        {form.mensaje && (
          <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
            {form.mensaje}
          </p>
        )}
        <Campo
          etiqueta="Correo"
          type="email"
          autoComplete="email"
          maxLength={254}
          {...form.campo('correo')}
        />
        <Campo
          etiqueta="Contraseña"
          type="password"
          autoComplete="current-password"
          maxLength={72}
          {...form.campo('password')}
        />
        <button
          type="submit"
          disabled={form.enviando}
          className="w-full py-2.5 rounded-lg bg-emerald-700 text-white font-medium hover:bg-emerald-800 disabled:opacity-60"
        >
          {form.enviando ? 'Ingresando…' : 'Ingresar'}
        </button>
      </form>

      <p className="mt-4 text-sm text-center text-gray-600">
        ¿No tienes cuenta?{' '}
        <Link
          to={`/registro?siguiente=${encodeURIComponent(siguiente)}`}
          className="text-emerald-800 font-medium hover:underline"
        >
          Crea una
        </Link>
      </p>
    </section>
  )
}
