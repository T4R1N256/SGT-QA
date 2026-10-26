import clsx from 'clsx'
import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ShoppingCartIcon, TrashIcon } from '@heroicons/react/24/outline'
import { revisarCarrito } from '../api/tienda'
import { useTitulo } from '../TiendaContext'
import { formatoMoneda } from '../../compartido/format'
import { cantidadConUnidad } from '../catalogo/disponibilidad'
import Cargando from '../componentes/Cargando'
import ErrorCarga from '../componentes/ErrorCarga'
import { useSesion } from '../cuenta/SesionContext'
import PasosCompra from '../compra/PasosCompra'
import Avisos from './Avisos'
import { useCarrito } from './CarritoContext'
import SelectorCantidad from './SelectorCantidad'
import Totales from './Totales'
import { validarCantidad } from './carrito'
import { useRevisionCarrito } from './useRevisionCarrito'

/**
 * Carrito (N5–N8): cada cambio se revisa en el servidor, que da los importes, el IVA y el total con
 * los precios y existencias vigentes, y avisa si algo se ajustó (P4, P5).
 *
 * @returns {JSX.Element} La página
 * @see useRevisionCarrito
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Carrito() {
  const { lineas, cambiar, quitar, vaciar } = useCarrito()
  const { cliente } = useSesion()
  const revision = useRevisionCarrito()
  const { datos, avisos, setAvisos, aplicar } = revision
  const [continuando, setContinuando] = useState(false)
  const [errorContinuar, setErrorContinuar] = useState(null)
  const navigate = useNavigate()
  useTitulo('Carrito')

  // Cualquier acción del cliente reemplaza los avisos anteriores
  const accion =
    (fn) =>
    (...args) => {
      setAvisos([])
      setErrorContinuar(null)
      fn(...args)
    }

  // Las existencias pueden cambiar mientras el cliente decide: se revisa otra vez antes de continuar (R07)
  async function continuar() {
    setContinuando(true)
    setErrorContinuar(null)
    const res = await revisarCarrito(lineas)
    setContinuando(false)
    if (!res.ok) return setErrorContinuar(res.mensaje)
    if (res.avisos.length) return aplicar(res)
    if (!res.lineas.length) return
    // Se compra con cuenta (D5): sin sesión se pide ingresar y luego se regresa aquí; el carrito se conserva (T-S2)
    navigate(
      cliente ? '/compra/entrega' : `/ingresar?siguiente=${encodeURIComponent('/compra/entrega')}`
    )
  }

  if (lineas.length === 0) {
    return (
      <section>
        <h1 className="text-2xl font-bold text-gray-900 mb-6">Tu carrito</h1>
        <Avisos avisos={avisos} />
        <div className="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
          <ShoppingCartIcon className="mx-auto w-10 h-10 text-gray-400" aria-hidden="true" />
          <p className="mt-3 font-medium text-gray-900">Tu carrito está vacío</p>
          <p className="mt-1 text-sm text-gray-600">
            Agrega productos para continuar con la compra.
          </p>
          <Link
            to="/"
            className="inline-block mt-5 px-4 py-2 rounded-lg bg-emerald-700 text-white hover:bg-emerald-800"
          >
            Explorar la tienda
          </Link>
        </div>
      </section>
    )
  }

  return (
    <section>
      <PasosCompra actual="Carrito" />
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Tu carrito</h1>
      <Avisos avisos={avisos} />

      {revision.error ? (
        <ErrorCarga mensaje={revision.error.mensaje} onReintentar={revision.reintentar} />
      ) : !datos ? (
        <Cargando texto="Revisando tu carrito…" />
      ) : (
        <div className="grid gap-8 lg:grid-cols-[1fr_20rem] items-start">
          <div
            className={clsx(revision.cargando && 'opacity-60 transition-opacity')}
            aria-busy={revision.cargando}
          >
            <table className="w-full text-sm bg-white rounded-xl border border-gray-200 overflow-hidden">
              <caption className="sr-only">Productos en tu carrito</caption>
              <thead className="bg-gray-50 text-left text-gray-600">
                <tr>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Producto
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium text-right">
                    Precio
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Cantidad
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium text-right">
                    Importe
                  </th>
                  <th scope="col" className="px-2 py-3">
                    <span className="sr-only">Quitar</span>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {datos.lineas.map((linea) => (
                  <LineaCarrito
                    key={linea.id}
                    linea={linea}
                    onCambiar={accion(cambiar)}
                    onQuitar={accion(quitar)}
                  />
                ))}
              </tbody>
            </table>
            <VaciarCarrito onVaciar={accion(vaciar)} />
          </div>

          <Totales resumen={datos.resumen} actualizando={revision.cargando}>
            <button
              type="button"
              onClick={continuar}
              disabled={continuando || revision.cargando}
              className="mt-5 w-full py-2.5 rounded-lg bg-emerald-700 text-white font-medium hover:bg-emerald-800 disabled:opacity-60"
            >
              {continuando ? 'Revisando…' : 'Continuar con la compra'}
            </button>
            {errorContinuar && (
              <p role="alert" className="mt-2 text-sm text-red-700">
                {errorContinuar}
              </p>
            )}
          </Totales>
        </div>
      )}
    </section>
  )
}

// Una línea del carrito. La cantidad se puede escribir: si no es válida no se cambia (P1, P2)
// y al salir del campo se regresa a la última cantidad válida
function LineaCarrito({ linea, onCambiar, onQuitar }) {
  const [texto, setTexto] = useState(String(linea.cantidad))
  const [error, setError] = useState(null)
  const idError = `cantidad-${linea.id}-error`

  // Si el servidor ajustó la cantidad, el campo la muestra
  useEffect(() => {
    setTexto(String(linea.cantidad))
    setError(null)
  }, [linea.cantidad])

  function cambiar(valor) {
    setTexto(valor)
    const { cantidad, mensaje } = validarCantidad(valor, { existencia: linea.existencia })
    setError(mensaje ?? null)
    if (cantidad && cantidad !== linea.cantidad) onCambiar(linea.id, cantidad)
  }

  function salir() {
    if (!error) return
    setTexto(String(linea.cantidad))
    setError(`${error}. Se conservó la cantidad anterior: ${linea.cantidad}.`)
  }

  return (
    <tr>
      <td className="px-4 py-3">
        <Link
          to={`/producto/${linea.id}`}
          className="font-medium text-gray-900 hover:text-emerald-800"
        >
          {linea.nombre}
        </Link>
        {linea.disponibilidad === 'pocas_piezas' && (
          <p className="text-xs text-amber-800">
            Quedan {cantidadConUnidad(linea.existencia, linea.unidad)}
          </p>
        )}
      </td>
      <td className="px-4 py-3 text-right whitespace-nowrap">{formatoMoneda(linea.precio)}</td>
      <td className="px-4 py-3" onBlur={salir}>
        <SelectorCantidad
          valor={texto}
          onCambiar={cambiar}
          maximo={linea.existencia}
          etiqueta={linea.nombre}
          invalido={Boolean(error)}
          descripcion={error ? idError : undefined}
        />
        {error && (
          <p id={idError} role="alert" className="mt-1 text-xs text-red-700 max-w-48">
            {error}
          </p>
        )}
      </td>
      <td className="px-4 py-3 text-right font-medium whitespace-nowrap">
        {formatoMoneda(linea.importe)}
      </td>
      <td className="px-2 py-3 text-right">
        <button
          type="button"
          onClick={() => onQuitar(linea.id)}
          aria-label={`Quitar ${linea.nombre} del carrito`}
          className="p-1.5 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-700"
        >
          <TrashIcon className="w-5 h-5" aria-hidden="true" />
        </button>
      </td>
    </tr>
  )
}

function VaciarCarrito({ onVaciar }) {
  const [confirmando, setConfirmando] = useState(false)

  if (!confirmando) {
    return (
      <button
        type="button"
        onClick={() => setConfirmando(true)}
        className="mt-4 text-sm text-gray-600 hover:text-red-700 hover:underline"
      >
        Vaciar carrito
      </button>
    )
  }
  return (
    <div className="mt-4 flex flex-wrap items-center gap-3 text-sm">
      <span className="text-gray-700">¿Quitar todos los productos del carrito?</span>
      <button
        type="button"
        onClick={onVaciar}
        className="px-3 py-1.5 rounded-lg bg-red-700 text-white hover:bg-red-800"
      >
        Sí, vaciar
      </button>
      <button
        type="button"
        onClick={() => setConfirmando(false)}
        className="px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-100"
      >
        Cancelar
      </button>
    </div>
  )
}
