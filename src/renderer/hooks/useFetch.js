/**
 * @file Hook para cargar datos del API de SGT al abrir una página.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * Llama al API al montar el componente y guarda la respuesta, el estado de carga y el error.
 * Usa siempre la función más reciente sin volver a cargar en cada render (las páginas pasan funciones en línea).
 *
 * @param {function(): Promise<Object>} apiFn Función de api.js que regresa {ok, ...}
 * @returns {{data: (Object|null), loading: boolean, error: (string|null), refetch: function(): Promise<void>}}
 *          La respuesta, si está cargando, el mensaje de error y una función para volver a cargar
 */
export function useFetch(apiFn) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  // La función más reciente sin volver a cargar en cada render (las páginas pasan funciones en línea)
  const apiRef = useRef(apiFn)
  useEffect(() => {
    apiRef.current = apiFn
  })

  const run = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await apiRef.current()
    setLoading(false)
    if (res.ok) setData(res)
    else setError(res.mensaje ?? 'Error al cargar los datos')
  }, [])

  useEffect(() => {
    run()
  }, [run])

  return { data, loading, error, refetch: run }
}
