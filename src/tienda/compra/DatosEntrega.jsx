/**
 * Datos de entrega en el resumen y en el pago.
 *
 * @param {{entrega: Object}} props Datos de entrega (recibe, teléfono, dirección y referencias)
 * @returns {JSX.Element} Los datos
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function DatosEntrega({ entrega }) {
  const interior = entrega.numero_interior ? `, int. ${entrega.numero_interior}` : ''

  return (
    <address className="not-italic text-sm text-gray-700 space-y-0.5">
      <p className="font-medium text-gray-900">{entrega.recibe}</p>
      <p>
        {entrega.calle} {entrega.numero_exterior}
        {interior}
      </p>
      <p>
        Col. {entrega.colonia}, C.P. {entrega.codigo_postal}
      </p>
      <p>
        {entrega.ciudad}, {entrega.entidad}
      </p>
      <p>Tel. {entrega.telefono}</p>
      {entrega.referencias && <p className="text-gray-500">Referencias: {entrega.referencias}</p>}
    </address>
  )
}
