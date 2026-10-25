import { useTienda } from '../TiendaContext'

export default function Pie() {
  const { nombre, tasaIVA } = useTienda()

  return (
    <footer className="border-t border-gray-200 bg-white">
      <div className="max-w-6xl mx-auto px-6 py-4 text-xs text-gray-500 flex justify-between">
        <span>{nombre}</span>
        <span>Precios en pesos mexicanos con IVA ({tasaIVA} %) incluido · Envío gratis</span>
      </div>
    </footer>
  )
}
