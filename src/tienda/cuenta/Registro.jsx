import { Link, Navigate, useSearchParams } from 'react-router-dom'
import { useTitulo } from '../TiendaContext'
import Cargando from '../componentes/Cargando'
import Campo from './Campo'
import { useSesion } from './SesionContext'
import { useFormulario } from './useFormulario'
import { destinoSeguro, validarRegistro } from './validaciones'

const VACIO = { nombre: '', apellidos: '', correo: '', telefono: '', password: '', confirmar: '' }

/**
 * Registro del cliente (D5). Cada campo se valida aquí y en el servidor, con su propio mensaje (P8–P10).
 *
 * @returns {JSX.Element} La página
 * @see validarRegistro
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Registro() {
  const { cliente, cargando, registrar } = useSesion()
  const [params] = useSearchParams()
  const siguiente = destinoSeguro(params.get('siguiente'))
  const form = useFormulario(VACIO, validarRegistro)
  useTitulo('Crear cuenta')

  if (cargando) return <Cargando />
  if (cliente) return <Navigate to={siguiente} replace />

  // La confirmación solo se revisa aquí. Al crear la cuenta, el <Navigate> de arriba regresa a donde iba
  // eslint-disable-next-line no-unused-vars
  const enviar = form.enviar(({ confirmar, ...datos }) => registrar(datos))

  return (
    <section className="max-w-lg mx-auto">
      <h1 className="text-2xl font-bold text-gray-900">Crear cuenta</h1>
      <p className="mt-2 text-sm text-gray-600">
        Necesitas una cuenta para comprar. Todos los campos son obligatorios.
      </p>

      <form
        onSubmit={enviar}
        noValidate
        className="mt-6 grid gap-4 sm:grid-cols-2 rounded-xl border border-gray-200 bg-white p-6"
      >
        {form.mensaje && (
          <p
            role="alert"
            className="sm:col-span-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"
          >
            {form.mensaje}
          </p>
        )}
        <Campo
          etiqueta="Nombre"
          autoComplete="given-name"
          maxLength={100}
          {...form.campo('nombre')}
        />
        <Campo
          etiqueta="Apellidos"
          autoComplete="family-name"
          maxLength={100}
          {...form.campo('apellidos')}
        />
        <Campo
          className="sm:col-span-2"
          etiqueta="Correo"
          type="email"
          autoComplete="email"
          maxLength={254}
          {...form.campo('correo')}
        />
        <Campo
          className="sm:col-span-2"
          etiqueta="Teléfono"
          type="tel"
          inputMode="tel"
          autoComplete="tel-national"
          maxLength={20}
          ayuda="10 dígitos, por ejemplo 656 123 4567"
          {...form.campo('telefono')}
        />
        <Campo
          etiqueta="Contraseña"
          type="password"
          autoComplete="new-password"
          maxLength={72}
          ayuda="8 caracteres o más, con letras y números"
          {...form.campo('password')}
        />
        <Campo
          etiqueta="Confirmar contraseña"
          type="password"
          autoComplete="new-password"
          maxLength={72}
          {...form.campo('confirmar')}
        />
        <button
          type="submit"
          disabled={form.enviando}
          className="sm:col-span-2 w-full py-2.5 rounded-lg bg-emerald-700 text-white font-medium hover:bg-emerald-800 disabled:opacity-60"
        >
          {form.enviando ? 'Creando cuenta…' : 'Crear cuenta'}
        </button>
      </form>

      <p className="mt-4 text-sm text-center text-gray-600">
        ¿Ya tienes cuenta?{' '}
        <Link
          to={`/ingresar?siguiente=${encodeURIComponent(siguiente)}`}
          className="text-emerald-800 font-medium hover:underline"
        >
          Ingresa
        </Link>
      </p>
    </section>
  )
}
