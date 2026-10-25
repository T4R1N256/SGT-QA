import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { ingresarCliente, obtenerCuenta, registrarCliente, salirCliente } from '../api/tienda'

// Sesión del cliente: se pregunta al servidor al cargar la tienda y se actualiza al ingresar, registrarse o salir
const SesionContext = createContext(null)

export function SesionProvider({ children }) {
  const [estado, setEstado] = useState({ cargando: true, cliente: null })

  useEffect(() => {
    obtenerCuenta().then((res) =>
      setEstado({ cargando: false, cliente: res.ok ? res.cliente : null })
    )
  }, [])

  const conCliente = useCallback(
    (apiFn) => async (datos) => {
      const res = await apiFn(datos)
      if (res.ok) setEstado({ cargando: false, cliente: res.cliente })
      return res
    },
    []
  )

  const salir = useCallback(async () => {
    const res = await salirCliente()
    if (res.ok) setEstado({ cargando: false, cliente: null })
    return res
  }, [])

  const valor = useMemo(
    () => ({
      ...estado,
      ingresar: conCliente(ingresarCliente),
      registrar: conCliente(registrarCliente),
      salir,
    }),
    [estado, conCliente, salir]
  )

  return <SesionContext.Provider value={valor}>{children}</SesionContext.Provider>
}

export const useSesion = () => useContext(SesionContext)
