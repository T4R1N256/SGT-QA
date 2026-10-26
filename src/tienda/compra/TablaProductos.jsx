import { formatoMoneda } from '../../compartido/format'

/**
 * Productos de la compra, sin editar: cantidad, precio con IVA incluido e importe.
 *
 * @param {{lineas: Array<Object>}} props Líneas del carrito revisado o del pedido
 * @returns {JSX.Element} La tabla
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function TablaProductos({ lineas }) {
  return (
    <table className="w-full text-sm bg-white rounded-xl border border-gray-200 overflow-hidden">
      <caption className="sr-only">Productos de tu compra</caption>
      <thead className="bg-gray-50 text-left text-gray-600">
        <tr>
          <th scope="col" className="px-4 py-3 font-medium">
            Producto
          </th>
          <th scope="col" className="px-4 py-3 font-medium text-right">
            Cantidad
          </th>
          <th scope="col" className="px-4 py-3 font-medium text-right">
            Precio
          </th>
          <th scope="col" className="px-4 py-3 font-medium text-right">
            Importe
          </th>
        </tr>
      </thead>
      <tbody className="divide-y divide-gray-100">
        {lineas.map((linea) => (
          <tr key={linea.id}>
            <td className="px-4 py-3 text-gray-900">{linea.nombre}</td>
            <td className="px-4 py-3 text-right">{linea.cantidad}</td>
            <td className="px-4 py-3 text-right whitespace-nowrap">
              {formatoMoneda(linea.precio)}
            </td>
            <td className="px-4 py-3 text-right font-medium whitespace-nowrap">
              {formatoMoneda(linea.importe)}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
