import { useEffect, useState } from 'react'
import { getConfig, updateConfig } from '../api/api'
import { ActionButton, FormAlert, fieldInputClass } from '../components'
import { imprimirTicket } from '../pdf/imprimir'
import { DocumentTextIcon } from '@heroicons/react/20/solid'

const CAMPOS = [
  { name: 'Nombre_Tienda', label: 'Nombre de la tienda *', maxLength: 100, required: true },
  { name: 'Direccion', label: 'Dirección', maxLength: 255 },
  { name: 'Telefono', label: 'Teléfono', maxLength: 20 },
  { name: 'RFC', label: 'RFC', maxLength: 13 },
  { name: 'Mensaje_Ticket', label: 'Mensaje al final del ticket', maxLength: 255 },
]

const VACIO = { Nombre_Tienda: '', Direccion: '', Telefono: '', RFC: '', Mensaje_Ticket: '' }

// Venta de ejemplo para revisar cómo se ve el ticket con los datos capturados
function ventaDeEjemplo(tienda) {
  const hoy = new Date()
  const fecha = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`
  return {
    ID_Venta: 0,
    Fecha: fecha,
    Hora: hoy.toTimeString().slice(0, 8),
    Monto: '78.40',
    Pago_Recibido: '100.00',
    Cambio: '21.60',
    Forma_Pago: 'efectivo',
    Cajero: 'Vista previa',
    productos: [
      { ID_Producto: 1, Nombre_Producto: 'Producto de ejemplo', Cantidad: 1, Precio_Unitario: '45.90', Importe: '45.90' },
      { ID_Producto: 2, Nombre_Producto: 'Otro producto', Cantidad: 2, Precio_Unitario: '16.25', Importe: '32.50' },
    ],
    tienda,
  }
}

/**
 * Configuración (solo admin): datos de la tienda que aparecen en el ticket (nombre, dirección,
 * teléfono, RFC y mensaje), con un ticket de ejemplo para revisar cómo se ve.
 *
 * @returns {JSX.Element} La página
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Configuracion() {
  const [form, setForm] = useState(VACIO)
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [response, setResponse] = useState(null)

  useEffect(() => {
    getConfig()
      .then((res) => {
        if (res.ok) setForm({ ...VACIO, ...res.configuracion })
        else setResponse({ type: 'error', message: res.mensaje ?? 'No se pudo cargar la configuración' })
      })
      .finally(() => setCargando(false))
  }, [])

  function onChange(name) {
    return (e) => {
      setResponse(null)
      setForm((prev) => ({ ...prev, [name]: e.target.value }))
    }
  }

  // Se llama desde el botón (ActionButton ya cancela el evento) y al presionar Enter en el formulario
  async function guardar(e) {
    e?.preventDefault()
    if (!form.Nombre_Tienda.trim()) {
      setResponse({ type: 'error', message: 'El nombre de la tienda es obligatorio', field: 'Nombre_Tienda' })
      return
    }
    setGuardando(true)
    const res = await updateConfig(form)
    setGuardando(false)
    if (res.ok) {
      setForm({ ...VACIO, ...res.configuracion })
      setResponse({ type: 'success', message: 'Configuración guardada' })
    } else {
      setResponse({ type: 'error', message: res.mensaje ?? 'No se pudo guardar la configuración' })
    }
  }

  async function vistaPrevia() {
    const res = await imprimirTicket(ventaDeEjemplo(form))
    if (!res.ok) setResponse({ type: 'error', message: `No se pudo generar la vista previa: ${res.mensaje}` })
  }

  if (cargando) return <p className="text-gray-400 text-sm p-4">Cargando...</p>

  return (
    <div className="flex flex-1 items-start justify-center">
      <form
        onSubmit={guardar}
        className="bg-white rounded-lg shadow border border-gray-300 p-6 w-full max-w-xl flex flex-col gap-3"
      >
        <div>
          <h2 className="text-lg font-semibold text-gray-800">Datos de la tienda</h2>
          <p className="text-sm text-gray-500">Aparecen en el encabezado y al final de cada ticket de venta.</p>
        </div>

        <FormAlert response={response} />

        {CAMPOS.map(({ name, label, maxLength, required }) => (
          <label key={name} className="flex flex-col gap-1 text-xs font-semibold text-gray-700">
            {label}
            <input
              name={name}
              type="text"
              value={form[name]}
              maxLength={maxLength}
              onChange={onChange(name)}
              className={fieldInputClass(response, name, required)}
            />
          </label>
        ))}

        <div className="flex justify-end gap-2 mt-2">
          <ActionButton onClick={vistaPrevia} className="w-auto rounded-lg font-normal">
            <span className="flex items-center gap-1.5">
              <DocumentTextIcon className="w-4 h-4" />
              Vista previa del ticket
            </span>
          </ActionButton>
          <ActionButton
            onClick={guardar}
            disabled={guardando}
            className="w-auto rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 font-semibold"
          >
            {guardando ? 'Guardando...' : 'Guardar'}
          </ActionButton>
        </div>
      </form>
    </div>
  )
}
