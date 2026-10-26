/**
 * @file Sesión del cliente de la tienda en línea: SesionProvider y useSesion.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { ingresarCliente, obtenerCuenta, registrarCliente, salirCliente } from '../api/tienda'

const SesionContext = createContext(null)

/**
 * Pregunta al servidor por la sesión al cargar la tienda y la actualiza al ingresar, registrarse o salir.
 * useSesion() da { cargando, cliente, ingresar, registrar, salir }; las tres funciones regresan la respuesta del API.
 *
 * @param {{children: React.ReactNode}} props
 * @returns {JSX.Element} El proveedor del contexto
 */
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
