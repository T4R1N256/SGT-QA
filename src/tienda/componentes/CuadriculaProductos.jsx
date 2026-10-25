import TarjetaProducto from './TarjetaProducto'

export default function CuadriculaProductos({ productos, etiqueta }) {
  return (
    <ul aria-label={etiqueta} className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      {productos.map((producto) => (
        <li key={producto.id}>
          <TarjetaProducto producto={producto} />
        </li>
      ))}
    </ul>
  )
}
