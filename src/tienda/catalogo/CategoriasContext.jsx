import { createContext, useContext } from 'react'
import { obtenerCategorias } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'

// Categorías de la tienda: se piden una vez y las usan la barra de categorías, el inicio y la búsqueda
const CategoriasContext = createContext(null)

export function CategoriasProvider({ children }) {
  const consulta = useConsulta(obtenerCategorias)
  return <CategoriasContext.Provider value={consulta}>{children}</CategoriasContext.Provider>
}

// { cargando, categorias, error, reintentar }
export function useCategorias() {
  const { datos, ...consulta } = useContext(CategoriasContext)
  return { ...consulta, categorias: datos?.categorias ?? [] }
}
