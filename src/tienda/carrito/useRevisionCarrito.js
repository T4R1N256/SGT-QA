import { useCallback, useEffect, useState } from 'react'
import { revisarCarrito } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useCarrito } from './CarritoContext'

// Revisión del carrito en el servidor cada vez que cambia (carrito y resumen de la compra)
// Lo que ajustó el servidor se aplica al carrito guardado; los avisos se quedan hasta el siguiente cambio del cliente
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
