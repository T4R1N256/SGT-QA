/**
 * @file Hook para las consultas al API de la tienda en línea.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { useCallback, useEffect, useState } from 'react'

/**
 * Llama al API cuando cambian los argumentos y permite reintentar si falló (P15).
 * Ignora las respuestas viejas si los argumentos cambiaron antes de que llegaran.
 *
 * @param {function(...*): Promise<Object>} apiFn Función de api/tienda.js
 * @param {...*} args Argumentos de la función (se comparan como JSON)
 * @returns {{cargando: boolean, datos: (Object|null), error: (Object|null), reintentar: function(): void}}
 *          error es la respuesta fallida ({ status, mensaje })
 */
export function useConsulta(apiFn, ...args) {
  const [estado, setEstado] = useState({ cargando: true, datos: null, error: null })
  const [intento, setIntento] = useState(0)
  const clave = JSON.stringify(args)

  useEffect(() => {
    let vigente = true
    setEstado((prev) => ({ ...prev, cargando: true }))
    apiFn(...args).then((res) => {
      if (!vigente) return
      setEstado(
        res.ok
          ? { cargando: false, datos: res, error: null }
          : { cargando: false, datos: null, error: res }
      )
    })
    return () => {
      vigente = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clave, intento])

  const reintentar = useCallback(() => setIntento((n) => n + 1), [])

  return { ...estado, reintentar }
}
