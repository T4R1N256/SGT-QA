import { useId, useState } from 'react'
import { Link } from 'react-router-dom'
import { ShoppingCartIcon } from '@heroicons/react/24/outline'
import { cantidadConUnidad } from '../catalogo/disponibilidad'
import { useCarrito } from './CarritoContext'
import SelectorCantidad from './SelectorCantidad'
import { validarCantidad } from './carrito'

/**
 * Elegir cuántas unidades y agregarlas al carrito (N5). Agotado: el botón no se puede usar (P3).
 *
 * @param {{producto: Object}} props Producto público del API
 * @returns {JSX.Element} El selector y el botón
 * @see validarCantidad
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function AgregarAlCarrito({ producto }) {
  const { lineas, agregar } = useCarrito()
  const [texto, setTexto] = useState('1')
  const [mensaje, setMensaje] = useState(null)
  const idMensaje = useId()

  if (producto.disponibilidad === 'agotado') {
    return (
      <button
        type="button"
        disabled
        className="w-full py-2.5 rounded-lg bg-gray-200 text-gray-500 font-medium cursor-not-allowed"
      >
        Agotado
      </button>
    )
  }

  const enCarrito = lineas.find((l) => l.id === producto.id)?.cantidad ?? 0

  function cambiar(valor) {
    setTexto(valor)
    setMensaje(null)
  }

  function enviar(e) {
    e.preventDefault()
    const { cantidad, mensaje: error } = validarCantidad(texto, {
      existencia: producto.existencia,
      enCarrito,
    })
    if (error) {
      setMensaje({ tipo: 'error', texto: error })
      return
    }
    agregar(producto, cantidad)
    setTexto('1')
    setMensaje({
      tipo: 'ok',
      texto: `Agregaste ${cantidadConUnidad(cantidad, producto.unidad)} al carrito.`,
    })
  }

  return (
    <form onSubmit={enviar} noValidate className="space-y-3">
      <div className="flex items-center gap-3">
        <label htmlFor={`${idMensaje}-cantidad`} className="text-sm text-gray-700">
          Cantidad
        </label>
        <SelectorCantidad
          id={`${idMensaje}-cantidad`}
          valor={texto}
          onCambiar={cambiar}
          maximo={producto.existencia - enCarrito}
          etiqueta={producto.nombre}
          invalido={mensaje?.tipo === 'error'}
          descripcion={mensaje ? idMensaje : undefined}
        />
      </div>

      <button
        type="submit"
        className="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-lg bg-emerald-700 text-white font-medium hover:bg-emerald-800"
      >
        <ShoppingCartIcon className="w-5 h-5" aria-hidden="true" />
        Agregar al carrito
      </button>

      {mensaje && (
        <p
          id={idMensaje}
          role={mensaje.tipo === 'error' ? 'alert' : 'status'}
          className={mensaje.tipo === 'error' ? 'text-sm text-red-700' : 'text-sm text-emerald-800'}
        >
          {mensaje.texto}{' '}
          {mensaje.tipo === 'ok' && (
            <Link to="/carrito" className="font-medium underline">
              Ver carrito
            </Link>
          )}
        </p>
      )}
      {enCarrito > 0 && !mensaje && (
        <p className="text-sm text-gray-600">
          Ya tienes {cantidadConUnidad(enCarrito, producto.unidad)} en tu carrito.
        </p>
      )}
    </form>
  )
}
