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

  return <TiendaContext.Provider value={tienda}>{children}</TiendaContext.Provider>
}

export const useTienda = () => useContext(TiendaContext)

// Título de la pestaña: "<página> · <tienda>", o solo el nombre de la tienda en el inicio
export function useTitulo(titulo) {
  const { nombre } = useTienda()
  useEffect(() => {
    document.title = titulo ? `${titulo} · ${nombre}` : nombre
  }, [titulo, nombre])
}
