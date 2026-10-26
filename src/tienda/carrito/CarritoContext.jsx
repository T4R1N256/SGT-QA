/**
 * @file Carrito de la tienda en línea: CarritoProvider y useCarrito.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useReducer } from 'react'
import { CLAVE_GUARDADO, leerGuardado, reducer, totalPiezas } from './carrito'

const CarritoContext = createContext(null)

/**
 * Carrito guardado en localStorage al abrir la tienda.
 *
 * @returns {Array<Object>} Las líneas válidas ([] sin localStorage)
 * @see leerGuardado
 */
function cargar() {
  try {
    return leerGuardado(localStorage.getItem(CLAVE_GUARDADO))
  } catch {
    return []
  }
}

/**
 * Carrito compartido por toda la tienda. Se guarda en localStorage (se conserva al recargar, N7)
 * y se mantiene igual en todas las pestañas abiertas (evento storage).
 * useCarrito() da { lineas, piezas, agregar, cambiar, quitar, vaciar, sincronizar }.
 *
 * @param {{children: React.ReactNode}} props
 * @returns {JSX.Element} El proveedor del contexto
 * @see reducer
 */
export function CarritoProvider({ children }) {
  const [lineas, dispatch] = useReducer(reducer, undefined, cargar)

  useEffect(() => {
    try {
      localStorage.setItem(CLAVE_GUARDADO, JSON.stringify(lineas))
    } catch {
      // Sin localStorage (modo privado estricto) el carrito dura mientras la pestaña esté abierta
    }
  }, [lineas])

  useEffect(() => {
    const otraPestana = (e) => {
      if (e.key === CLAVE_GUARDADO)
        dispatch({ tipo: 'reemplazar', lineas: leerGuardado(e.newValue) })
    }
    window.addEventListener('storage', otraPestana)
    return () => window.removeEventListener('storage', otraPestana)
  }, [])

  const agregar = useCallback(
    (producto, cantidad) => dispatch({ tipo: 'agregar', producto, cantidad }),
    []
  )
  const cambiar = useCallback((id, cantidad) => dispatch({ tipo: 'cambiar', id, cantidad }), [])
  const quitar = useCallback((id) => dispatch({ tipo: 'quitar', id }), [])
  const vaciar = useCallback(() => dispatch({ tipo: 'vaciar' }), [])
  const sincronizar = useCallback(
    (lineasServidor) => dispatch({ tipo: 'sincronizar', lineas: lineasServidor }),
    []
  )

  const valor = useMemo(
    () => ({ lineas, piezas: totalPiezas(lineas), agregar, cambiar, quitar, vaciar, sincronizar }),
    [lineas, agregar, cambiar, quitar, vaciar, sincronizar]
  )

  return <CarritoContext.Provider value={valor}>{children}</CarritoContext.Provider>
}

export const useCarrito = () => useContext(CarritoContext)
