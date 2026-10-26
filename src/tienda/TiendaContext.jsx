/**
 * @file Datos generales de la tienda en línea: TiendaProvider, useTienda y useTitulo.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { createContext, useContext, useEffect, useState } from 'react'
import { obtenerConfiguracion } from './api/tienda'

const TiendaContext = createContext({ nombre: 'Tienda', tasaIVA: 16 })

/**
 * Pide una sola vez al API el nombre de la tienda y la tasa de IVA.
 * Mientras llegan usa "Tienda" y 16 %.
 *
 * @param {{children: React.ReactNode}} props
 * @returns {JSX.Element} El proveedor del contexto
 */
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

/**
 * Título de la pestaña: "<página> · <tienda>", o solo el nombre de la tienda en el inicio.
 *
 * @param {string} [titulo] Título de la página
 * @returns {void}
 */
export function useTitulo(titulo) {
  const { nombre } = useTienda()
  useEffect(() => {
    document.title = titulo ? `${titulo} · ${nombre}` : nombre
  }, [titulo, nombre])
}
