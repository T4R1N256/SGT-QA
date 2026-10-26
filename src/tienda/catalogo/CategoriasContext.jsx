/**
 * @file Categorías de la tienda en línea: CategoriasProvider y useCategorias.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { createContext, useContext } from 'react'
import { obtenerCategorias } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'

const CategoriasContext = createContext(null)

/**
 * Pide las categorías una sola vez; las usan la barra de categorías, el inicio y la búsqueda.
 *
 * @param {{children: React.ReactNode}} props
 * @returns {JSX.Element} El proveedor del contexto
 */
export function CategoriasProvider({ children }) {
  const consulta = useConsulta(obtenerCategorias)
  return <CategoriasContext.Provider value={consulta}>{children}</CategoriasContext.Provider>
}

/**
 * Categorías con productos a la venta.
 *
 * @returns {{cargando: boolean, categorias: Array<Object>, error: (Object|null), reintentar: function(): void}}
 *          La consulta con la lista de categorías ([] mientras carga)
 */
export function useCategorias() {
  const { datos, ...consulta } = useContext(CategoriasContext)
  return { ...consulta, categorias: datos?.categorias ?? [] }
}
