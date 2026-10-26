/**
 * @file Sesión del usuario de SGT y caja abierta: AuthProvider y useAuth.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { createContext, useContext, useEffect, useState } from 'react'

const SESSION_KEY = 'usuario'
const CAJA_KEY = 'caja'

/**
 * Usuario guardado en sessionStorage (sobrevive a recargar la ventana).
 *
 * @returns {{id: string, puesto: string}|null} El usuario, o null si no hay sesión
 */
function leerSesion() {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

/**
 * Caja abierta guardada en sessionStorage.
 *
 * @returns {Object|null} La caja, o null si no hay caja abierta
 */
function leerCaja() {
  try {
    const raw = sessionStorage.getItem(CAJA_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

const AuthContext = createContext(null)

/**
 * Guarda el usuario con sesión, sus datos y la caja abierta. Avisa al proceso principal de Electron
 * si hay caja abierta (regla 4: no deja cerrar la ventana mientras haya una caja abierta).
 *
 * @param {{children: React.ReactNode}} props
 * @returns {JSX.Element} El proveedor del contexto
 */
export function AuthProvider({ children }) {
  const [usuario, setUsuario] = useState(leerSesion())
  const [userInfo, setUserInfo] = useState(null)
  const [caja, setCajaState] = useState(leerCaja())

  function guardarSesion(data) {
    const u = { id: data.id, puesto: data.puesto }

    sessionStorage.setItem(SESSION_KEY, JSON.stringify(u))

    setUsuario(u)
    setCaja(data.caja ?? null)
  }

  function setCaja(c) {
    if (c) sessionStorage.setItem(CAJA_KEY, JSON.stringify(c))
    else sessionStorage.removeItem(CAJA_KEY)
    setCajaState(c)
  }

  function cerrarSesion() {
    sessionStorage.removeItem(SESSION_KEY)
    sessionStorage.removeItem(CAJA_KEY)
    sessionStorage.removeItem('venta-pendiente')

    setUsuario(null)
    setUserInfo(null)
    setCajaState(null)
  }

  // Regla 4: el proceso principal de Electron no deja cerrar la ventana mientras haya una caja abierta
  useEffect(() => {
    window.electronAPI?.setCajaAbierta?.(Boolean(caja))
  }, [caja])

  const esAdmin = usuario?.puesto === 'admin'

  return (
    <AuthContext.Provider
      value={{
        usuario,
        esAdmin,
        guardarSesion,
        cerrarSesion,
        userInfo,
        setUserInfo,
        caja,
        setCaja,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

/**
 * Estado de la sesión: { usuario, esAdmin, guardarSesion, cerrarSesion, userInfo, setUserInfo, caja, setCaja }.
 *
 * @returns {Object} El contexto de AuthProvider
 * @throws {Error} Si se usa fuera de AuthProvider
 */
export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) throw new Error('useAuth must be used within an AuthProvider')

  return context
}
