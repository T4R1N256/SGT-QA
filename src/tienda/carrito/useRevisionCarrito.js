/**
 * @file Revisión del carrito en el servidor (páginas Carrito y Resumen de la compra).
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { useCallback, useEffect, useState } from 'react'
import { revisarCarrito } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useCarrito } from './CarritoContext'

/**
 * Revisa el carrito en el servidor cada vez que cambia. Lo que ajustó el servidor se aplica al carrito
 * guardado; los avisos se quedan hasta el siguiente cambio del cliente.
 *
 * @returns {{cargando: boolean, datos: (Object|null), error: (Object|null), reintentar: function(): void,
 *           avisos: Array<Object>, setAvisos: function(Array): void, aplicar: function(Object): void}}
 *          La consulta de useConsulta, los avisos y aplicar(res), que muestra los avisos de una respuesta
 *          al continuar o al confirmar y deja el carrito como lo dejó el servidor
 * @see Carrito::revisar
 */
export function useRevisionCarrito() {
  const { lineas, sincronizar } = useCarrito()
  const revision = useConsulta(revisarCarrito, lineas)
  const [avisos, setAvisos] = useState([])

  const { datos } = revision
  useEffect(() => {
    if (!datos) return
    if (datos.avisos.length) setAvisos(datos.avisos)
    sincronizar(datos.lineas)
  }, [datos, sincronizar])

  // Respuesta con avisos al continuar o al confirmar: se muestran y el carrito queda como lo dejó el servidor
  const aplicar = useCallback(
    (res) => {
      setAvisos(res.avisos)
      sincronizar(res.lineas)
    },
    [sincronizar]
  )

  return { ...revision, avisos, setAvisos, aplicar }
}
