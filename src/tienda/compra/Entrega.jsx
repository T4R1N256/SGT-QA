import { Navigate, useNavigate } from 'react-router-dom'
import { guardarEntrega, obtenerEntrega } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useTitulo } from '../TiendaContext'
import { useCarrito } from '../carrito/CarritoContext'
import Cargando from '../componentes/Cargando'
import ErrorCarga from '../componentes/ErrorCarga'
import Campo from '../cuenta/Campo'
import { useFormulario } from '../cuenta/useFormulario'
import PasosCompra from './PasosCompra'
import { CAMPOS_ENTREGA, ENTIDADES, validarEntrega } from './validaciones'

/**
 * Paso 2 (N9, R11, R12): datos de entrega, validados aquí y en el servidor con un mensaje por campo.
 * Se prellenan con lo capturado antes en esta compra o con la dirección del último pedido (T-S4).
 *
 * @returns {JSX.Element} La página
 * @see validarEntrega
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Entrega() {
  const { lineas } = useCarrito()
  const consulta = useConsulta(obtenerEntrega)
  useTitulo('Datos de entrega')

  // P6/P7: sin productos no hay compra que entregar
  if (lineas.length === 0) return <Navigate to="/carrito" replace />

  return (
    <section className="max-w-3xl">
      <PasosCompra actual="Entrega" />
      <h1 className="text-2xl font-bold text-gray-900">Datos de entrega</h1>
      <p className="mt-2 text-sm text-gray-600">
        ¿A dónde te llevamos tu pedido? El envío es gratis.
      </p>

      {consulta.error ? (
        <div className="mt-6">
          <ErrorCarga mensaje={consulta.error.mensaje} onReintentar={consulta.reintentar} />
        </div>
      ) : !consulta.datos ? (
        <Cargando />
      ) : (
        <FormularioEntrega inicial={consulta.datos.entrega} />
      )}
    </section>
  )
}

function FormularioEntrega({ inicial }) {
  const navigate = useNavigate()
  const form = useFormulario(
    Object.fromEntries(Object.keys(CAMPOS_ENTREGA).map((campo) => [campo, inicial[campo] ?? ''])),
    validarEntrega
  )
  const max = (campo) => CAMPOS_ENTREGA[campo][1]

  const enviar = form.enviar(async (valores) => {
    const res = await guardarEntrega(valores)
    if (res.ok) navigate('/compra/resumen')
    return res
  })

  return (
    <form
      onSubmit={enviar}
      noValidate
      className="mt-6 grid gap-4 sm:grid-cols-6 rounded-xl border border-gray-200 bg-white p-6"
    >
      {form.mensaje && (
        <p
          role="alert"
          className="sm:col-span-6 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"
        >
          {form.mensaje}
        </p>
      )}
      <Campo
        className="sm:col-span-4"
        etiqueta="Nombre de quien recibe"
        autoComplete="name"
        maxLength={max('recibe')}
        {...form.campo('recibe')}
      />
      <Campo
        className="sm:col-span-2"
        etiqueta="Teléfono de contacto"
        type="tel"
        inputMode="tel"
        autoComplete="tel-national"
        maxLength={max('telefono')}
        ayuda="10 dígitos"
        {...form.campo('telefono')}
      />
      <Campo
        className="sm:col-span-4"
        etiqueta="Calle"
        autoComplete="address-line1"
        maxLength={max('calle')}
        {...form.campo('calle')}
      />
      <Campo
        className="sm:col-span-1"
        etiqueta="Núm. exterior"
        maxLength={max('numero_exterior')}
        {...form.campo('numero_exterior')}
      />
      <Campo
        className="sm:col-span-1"
        etiqueta="Núm. interior"
        maxLength={max('numero_interior')}
        ayuda="Opcional"
        {...form.campo('numero_interior')}
      />
      <Campo
        className="sm:col-span-4"
        etiqueta="Colonia"
        maxLength={max('colonia')}
        {...form.campo('colonia')}
      />
      <Campo
        className="sm:col-span-2"
        etiqueta="Código postal"
        inputMode="numeric"
        autoComplete="postal-code"
        maxLength={max('codigo_postal')}
        {...form.campo('codigo_postal')}
      />
      <Campo
        className="sm:col-span-3"
        etiqueta="Ciudad o municipio"
        autoComplete="address-level2"
        maxLength={max('ciudad')}
        {...form.campo('ciudad')}
      />
      <Campo
        className="sm:col-span-3"
        etiqueta="Entidad"
        como="select"
        autoComplete="address-level1"
        {...form.campo('entidad')}
      >
        <option value="">Elige la entidad</option>
        {ENTIDADES.map((entidad) => (
          <option key={entidad} value={entidad}>
            {entidad}
          </option>
        ))}
      </Campo>
      <Campo
        className="sm:col-span-6"
        etiqueta="Referencias"
        como="textarea"
        rows={2}
        maxLength={max('referencias')}
        ayuda="Opcional: entre qué calles, color de la casa, etc."
        {...form.campo('referencias')}
      />

      <div className="sm:col-span-6 flex flex-wrap justify-between gap-3 pt-2">
        <button
          type="button"
          onClick={() => navigate('/carrito')}
          className="px-4 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100"
        >
          Regresar al carrito
        </button>
        <button
          type="submit"
          disabled={form.enviando}
          className="px-6 py-2.5 rounded-lg bg-emerald-700 text-white font-medium hover:bg-emerald-800 disabled:opacity-60"
        >
          {form.enviando ? 'Guardando…' : 'Continuar al resumen'}
        </button>
      </div>
    </form>
  )
}
