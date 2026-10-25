import { useEffect, useState } from 'react'

// Llama a la API cuando cambian los filtros; ignora respuestas viejas si los filtros cambiaron antes
// Regresa { cargando, datos, error }
export function useConsulta(apiFn, filtros) {
  const [estado, setEstado] = useState({ cargando: true, datos: null, error: null })
  const clave = JSON.stringify(filtros)

  useEffect(() => {
    let vigente = true
    setEstado((prev) => ({ ...prev, cargando: true }))
    apiFn(filtros).then((res) => {
      if (!vigente) return
      setEstado(res.ok ? { cargando: false, datos: res, error: null } : { cargando: false, datos: null, error: res.mensaje })
    })
    return () => {
      vigente = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [clave])

  return estado
}
