/**
 * @file Contenido extra del encabezado de cada página (por ejemplo, el botón de una acción):
 * la página lo pone con useHeader y PageHeader lo muestra con useHeaderContent.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'

const HeaderContext = createContext(null)

/**
 * Guarda el contenido del encabezado en una referencia y avisa a PageHeader cuando cambia,
 * sin volver a renderizar toda la app.
 *
 * @param {{children: React.ReactNode}} props
 * @returns {JSX.Element} El proveedor del contexto
 */
export function HeaderProvider({ children }) {
  const contentRef = useRef(null)
  const notifyRef = useRef(null)

  const setHeaderContent = useCallback((content) => {
    contentRef.current = content
    notifyRef.current?.()
  }, [])

  return (
    <HeaderContext.Provider value={{ contentRef, notifyRef, setHeaderContent }}>
      {children}
    </HeaderContext.Provider>
  )
}

/**
 * Pone el contenido del encabezado mientras la página está montada y lo quita al salir.
 *
 * @param {React.ReactNode} [content=null] Contenido para el encabezado
 * @returns {void}
 * @throws {Error} Si se usa fuera de HeaderProvider
 */
export function useHeader(content = null) {
  const ctx = useContext(HeaderContext)
  if (!ctx) throw new Error('useHeader debe usarse dentro de HeaderProvider')

  const { setHeaderContent } = ctx

  useEffect(() => {
    setHeaderContent(content)
  })

  useEffect(() => {
    return () => setHeaderContent(null)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])
}

/**
 * Contenido actual del encabezado; el componente se vuelve a renderizar cuando una página lo cambia.
 *
 * @returns {React.ReactNode} El contenido, o null
 * @throws {Error} Si se usa fuera de HeaderProvider
 * @see PageHeader
 */
export function useHeaderContent() {
  const ctx = useContext(HeaderContext)
  if (!ctx) throw new Error('useHeaderContent debe usarse dentro de HeaderProvider')

  const { contentRef, notifyRef } = ctx
  const [, forceUpdate] = useState(0)

  useEffect(() => {
    notifyRef.current = () => forceUpdate((n) => n + 1)
    return () => { notifyRef.current = null }
  }, [notifyRef])

  return contentRef.current
}
