import { createContext, useContext, useEffect, useState } from 'react'
import { obtenerConfiguracion } from './api/tienda'

// Datos generales de la tienda (nombre y tasa de IVA) que se piden una sola vez al API
const TiendaContext = createContext({ nombre: 'Tienda', tasaIVA: 16 })

export function TiendaProvider({ children }) {
  const [tienda, setTienda] = useState({ nombre: 'Tienda', tasaIVA: 16 })

  useEffect(() => {
    obtenerConfiguracion().then((res) => {
      if (res.ok) setTienda({ nombre: res.tienda.nombre, tasaIVA: Number(res.tienda.tasa_iva) })
    })
  }, [])

  useEffect(() => {
    document.title = tienda.nombre
  }, [tienda.nombre])

  return <TiendaContext.Provider value={tienda}>{children}</TiendaContext.Provider>
}

export const useTienda = () => useContext(TiendaContext)
