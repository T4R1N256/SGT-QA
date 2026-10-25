import { useCallback, useEffect, useRef, useState } from 'react'

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
