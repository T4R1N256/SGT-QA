import { useMemo, useState } from 'react'
import { getProducts, getProveedores, createRestock, getRestocks, getRestock } from '../api/api'
import { useFetch } from '../hooks/useFetch'
import { useHeader } from '../context/HeaderContext.jsx'
import { useNotify } from '../context/NotificationContext.jsx'
import { ActionButton, FormAlert } from '../components'
import { aCentavos } from '../utils/money'
import { cantidadSugerida, filtrarProductos, necesitanSurtido } from '../utils/productos'
import { PlusIcon, TrashIcon, SparklesIcon, TruckIcon } from '@heroicons/react/20/solid'

// Surtir (RF05): registrar lo que llega de un proveedor con su costo de compra.
// Suma al inventario, guarda el último costo de cada producto y queda en el historial de surtidos.

function formatMoney(n) {
  const valor = Number(n)
  return `$${(Number.isFinite(valor) ? valor : 0).toFixed(2)}`
}

const inputCls =
  'w-full px-2 py-1 rounded border border-gray-300 text-sm text-right focus:outline-none focus:ring-2 focus:ring-emerald-500'

const COLUMNAS = 'grid grid-cols-[2fr_0.8fr_0.8fr_1fr_1fr_1fr_0.4fr]'
const HISTORIAL_COLUMNAS = 'grid grid-cols-[1fr_0.7fr_2fr_0.8fr_0.8fr_1fr]'

export default function Surtir() {
  const notify = useNotify()
  const { data: productosData, refetch: refetchProductos } = useFetch(getProducts)
  const { data: proveedoresData } = useFetch(getProveedores)
  const { data: historialData, refetch: refetchHistorial } = useFetch(() => getRestocks())

  const [proveedor, setProveedor] = useState('')
  const [soloProveedor, setSoloProveedor] = useState(true)
  const [busqueda, setBusqueda] = useState('')
  const [lineas, setLineas] = useState([])
  const [notas, setNotas] = useState('')
  const [response, setResponse] = useState(null)
  const [detalle, setDetalle] = useState(null)

  const productos = useMemo(() => {
    const todos = productosData?.productos ?? []
    return soloProveedor && proveedor ? todos.filter((p) => String(p.ID_Proveedor) === String(proveedor)) : todos
  }, [productosData, soloProveedor, proveedor])

  const resultados = useMemo(
    () =>
      filtrarProductos(productos, busqueda, 8, { incluirAgotados: true }).filter(
        (p) => !lineas.some((l) => l.ID_Producto === p.ID_Producto)
      ),
    [productos, busqueda, lineas]
  )

  const totalCentavos = lineas.reduce(
    (suma, l) => suma + aCentavos(l.costo) * (parseInt(l.cantidad, 10) || 0),
    0
  )

  useHeader(<span className="text-sm text-gray-300">Compras a proveedores que reabastecen el inventario</span>)

  function agregar(producto, cantidad = 1) {
    setResponse(null)
    setLineas((prev) =>
      prev.some((l) => l.ID_Producto === producto.ID_Producto)
        ? prev
        : [
            ...prev,
            {
              ID_Producto: producto.ID_Producto,
              Nombre_Producto: producto.Nombre_Producto,
              Existencia: Number(producto.Cantidad),
              Cantidad_Minima: Number(producto.Cantidad_Minima),
              cantidad: String(cantidad),
              // Por defecto, el último costo de compra registrado
              costo: producto.Costo != null ? String(producto.Costo) : '',
            },
          ]
    )
    setBusqueda('')
  }

  function sugerir() {
    const pendientes = necesitanSurtido(productos).filter((p) => !lineas.some((l) => l.ID_Producto === p.ID_Producto))
    if (pendientes.length === 0) {
      setResponse({ type: 'success', message: 'No hay productos con stock bajo o agotados para este proveedor' })
      return
    }
    pendientes.forEach((p) => agregar(p, cantidadSugerida(p)))
  }

  function actualizar(id, campo, valor) {
    setResponse(null)
    setLineas((prev) => prev.map((l) => (l.ID_Producto === id ? { ...l, [campo]: valor } : l)))
  }

  function quitar(id) {
    setLineas((prev) => prev.filter((l) => l.ID_Producto !== id))
  }

  function validar() {
    if (!proveedor) return 'Elige el proveedor'
    if (lineas.length === 0) return 'Agrega al menos un producto'
    for (const l of lineas) {
      const cantidad = Number(l.cantidad)
      if (!Number.isInteger(cantidad) || cantidad < 1) return `Revisa la cantidad de ${l.Nombre_Producto}`
      if (l.costo === '' || !Number.isFinite(Number(l.costo)) || Number(l.costo) < 0)
        return `Revisa el costo de ${l.Nombre_Producto}`
    }
    return null
  }

  async function registrar() {
    const error = validar()
    if (error) {
      setResponse({ type: 'error', message: error })
      return
    }

    const res = await createRestock({
      ID_Proveedor: Number(proveedor),
      Notas: notas,
      productos: lineas.map((l) => ({
        ID_Producto: l.ID_Producto,
        Cantidad: Number(l.cantidad),
        Costo: Number(Number(l.costo).toFixed(2)),
      })),
    })

    if (!res.ok) {
      setResponse({ type: 'error', message: res.mensaje ?? 'No se pudo registrar el surtido' })
      return
    }

    setLineas([])
    setNotas('')
    refetchProductos()
    refetchHistorial()
    notify({
      title: 'Surtido registrado',
      type: 'success',
      message: `${res.surtido.Nombre_Proveedor}\n${res.surtido.productos.length} productos · Total ${formatMoney(res.surtido.Monto)}`,
    })
  }

  async function verDetalle(id) {
    if (detalle?.ID_Surtir === id) {
      setDetalle(null)
      return
    }
    const res = await getRestock(id)
    if (res.ok) setDetalle(res.surtido)
  }

  return (
    <div className="flex flex-col gap-4 w-full">
      <section className="bg-white border border-gray-300 shadow rounded-lg p-4 flex flex-col gap-3">
        <div className="grid grid-cols-[1fr_auto_1fr_auto] gap-3 items-end">
          <label className="flex flex-col gap-1 text-xs font-semibold text-gray-700">
            Proveedor *
            <select
              value={proveedor}
              onChange={(e) => {
                setProveedor(e.target.value)
                setResponse(null)
              }}
              className="px-2 py-1.5 rounded-lg border border-gray-300 text-sm"
            >
              <option value="">Elige un proveedor</option>
              {(proveedoresData?.proveedores ?? []).map((p) => (
                <option key={p.ID_Proveedor} value={p.ID_Proveedor}>
                  {p.Nombre_Proveedor}
                </option>
              ))}
            </select>
          </label>

          <label className="flex items-center gap-2 text-xs text-gray-700 pb-2">
            <input type="checkbox" checked={soloProveedor} onChange={(e) => setSoloProveedor(e.target.checked)} />
            Solo productos de este proveedor
          </label>

          <div className="relative">
            <label className="flex flex-col gap-1 text-xs font-semibold text-gray-700">
              Agregar producto
              <input
                type="text"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && resultados[0]) agregar(resultados[0])
                }}
                placeholder="Buscar por nombre o categoría..."
                className="px-2 py-1.5 rounded-lg border border-gray-300 text-sm font-normal"
              />
            </label>
            {busqueda && (
              <ul className="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto bg-white border border-gray-300 rounded-lg shadow-lg">
                {resultados.length === 0 ? (
                  <li className="px-3 py-2 text-sm text-gray-500">Sin resultados</li>
                ) : (
                  resultados.map((p) => (
                    <li
                      key={p.ID_Producto}
                      onClick={() => agregar(p)}
                      className="flex justify-between items-center px-3 py-2 text-sm text-gray-700 cursor-pointer hover:bg-emerald-50 border-b border-gray-100 last:border-b-0"
                    >
                      <span className="truncate flex-1">{p.Nombre_Producto}</span>
                      <span className="text-xs text-gray-500 ml-2">
                        {p.Cantidad} · {p.Estado}
                      </span>
                      <PlusIcon className="w-4 h-4 ml-2 text-emerald-600 shrink-0" />
                    </li>
                  ))
                )}
              </ul>
            )}
          </div>

          <ActionButton onClick={sugerir} disabled={!proveedor && soloProveedor} className="w-auto rounded-lg font-normal text-sm">
            <span className="flex items-center gap-1.5">
              <SparklesIcon className="w-4 h-4" />
              Sugerir stock bajo
            </span>
          </ActionButton>
        </div>

        <FormAlert response={response} />

        <div className="border border-gray-300 rounded-lg overflow-hidden">
          <div className={`${COLUMNAS} bg-gray-800 text-white text-xs`}>
            {['PRODUCTO', 'EXISTENCIA', 'MÍNIMO', 'CANTIDAD', 'COSTO UNITARIO', 'IMPORTE', ''].map((c) => (
              <div key={c} className="py-2 px-2 text-center">
                {c}
              </div>
            ))}
          </div>
          {lineas.length === 0 ? (
            <p className="text-center text-gray-500 py-6 text-sm">
              Elige el proveedor y agrega los productos que llegaron, o usa &quot;Sugerir stock bajo&quot;
            </p>
          ) : (
            <ul>
              {lineas.map((l) => (
                <li key={l.ID_Producto} className={`${COLUMNAS} items-center border-b border-gray-100 text-sm text-gray-700`}>
                  <span className="px-2 truncate">{l.Nombre_Producto}</span>
                  <span className="px-2 text-center">{l.Existencia}</span>
                  <span className="px-2 text-center">{l.Cantidad_Minima}</span>
                  <span className="px-2 py-1">
                    <input
                      type="number"
                      min="1"
                      step="1"
                      aria-label={`Cantidad de ${l.Nombre_Producto}`}
                      value={l.cantidad}
                      onChange={(e) => actualizar(l.ID_Producto, 'cantidad', e.target.value)}
                      className={inputCls}
                    />
                  </span>
                  <span className="px-2 py-1">
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      aria-label={`Costo de ${l.Nombre_Producto}`}
                      value={l.costo}
                      placeholder="0.00"
                      onChange={(e) => actualizar(l.ID_Producto, 'costo', e.target.value)}
                      className={inputCls}
                    />
                  </span>
                  <span className="px-2 text-right font-semibold">
                    {formatMoney((aCentavos(l.costo) * (parseInt(l.cantidad, 10) || 0)) / 100)}
                  </span>
                  <button
                    type="button"
                    onClick={() => quitar(l.ID_Producto)}
                    aria-label={`Quitar ${l.Nombre_Producto}`}
                    className="flex justify-center text-gray-500 hover:text-red-600"
                  >
                    <TrashIcon className="w-4 h-4" />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="grid grid-cols-[1fr_auto_auto] gap-4 items-end">
          <label className="flex flex-col gap-1 text-xs font-semibold text-gray-700">
            Notas (factura, observaciones)
            <input
              type="text"
              value={notas}
              maxLength={255}
              onChange={(e) => setNotas(e.target.value)}
              className="px-2 py-1.5 rounded-lg border border-gray-300 text-sm font-normal"
            />
          </label>
          <div className="flex flex-col items-end">
            <span className="text-xs font-semibold text-gray-600 uppercase">Total de la compra</span>
            <span className="text-2xl font-bold text-gray-900">{formatMoney(totalCentavos / 100)}</span>
          </div>
          <ActionButton
            onClick={registrar}
            disabled={lineas.length === 0}
            className="w-auto rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 font-semibold"
          >
            <span className="flex items-center gap-2">
              <TruckIcon className="w-5 h-5" />
              Registrar surtido
            </span>
          </ActionButton>
        </div>
      </section>

      <section className="bg-white border border-gray-300 shadow rounded-lg overflow-hidden">
        <h3 className="text-sm font-semibold text-gray-700 uppercase px-4 py-2">Surtidos de los últimos 30 días</h3>
        <div className={`${HISTORIAL_COLUMNAS} bg-gray-800 text-white text-xs`}>
          {['FECHA', 'HORA', 'PROVEEDOR', 'PRODUCTOS', 'UNIDADES', 'TOTAL'].map((c) => (
            <div key={c} className="py-2 px-2 text-center">
              {c}
            </div>
          ))}
        </div>
        {(historialData?.surtidos ?? []).length === 0 ? (
          <p className="text-center text-gray-500 py-6 text-sm">Todavía no hay surtidos registrados</p>
        ) : (
          <ul>
            {historialData.surtidos.map((s) => (
              <li key={s.ID_Surtir} className="border-b border-gray-100">
                <button
                  type="button"
                  onClick={() => verDetalle(s.ID_Surtir)}
                  className={`${HISTORIAL_COLUMNAS} w-full text-sm text-gray-700 hover:bg-gray-50 py-2`}
                >
                  <span>{s.Fecha}</span>
                  <span>{String(s.Hora).slice(0, 5)}</span>
                  <span className="truncate">{s.Nombre_Proveedor}</span>
                  <span>{s.Productos}</span>
                  <span>{s.Unidades}</span>
                  <span className="font-semibold">{formatMoney(s.Monto)}</span>
                </button>
                {detalle?.ID_Surtir === s.ID_Surtir && (
                  <div className="bg-gray-50 px-6 py-3 text-sm text-gray-700">
                    {detalle.Notas && <p className="mb-2 text-gray-500">Notas: {detalle.Notas}</p>}
                    <p className="mb-2 text-gray-500">Registró: {detalle.Registro ?? '-'}</p>
                    <ul className="flex flex-col gap-1">
                      {detalle.productos.map((p) => (
                        <li key={p.ID_Producto} className="flex justify-between">
                          <span>
                            {p.Cantidad} × {p.Nombre_Producto} a {formatMoney(p.Costo)}
                          </span>
                          <span className="font-semibold">{formatMoney(p.Importe)}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
