import { Link, useParams } from 'react-router-dom'
import { obtenerProducto } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useTienda, useTitulo } from '../TiendaContext'
import { formatoMoneda } from '../../compartido/format'
import { desglosarIVA } from '../../compartido/money'
import Cargando from '../componentes/Cargando'
import Disponibilidad from '../componentes/Disponibilidad'
import ErrorCarga from '../componentes/ErrorCarga'
import NoEncontrada from '../componentes/NoEncontrada'
import { enteroPositivo } from './filtros'
import { textoExistencia } from './disponibilidad'

const NO_ENCONTRADO = {
  titulo: 'Producto no encontrado',
  texto: 'El producto que buscas no existe o ya no está a la venta.',
}

// N4: nombre, descripción, categoría, precio con IVA incluido y disponibilidad. P14: producto inexistente
export default function Producto() {
  const id = enteroPositivo(useParams().id)
  return id ? <DetalleProducto key={id} id={id} /> : <NoEncontrada {...NO_ENCONTRADO} />
}

function DetalleProducto({ id }) {
  const { tasaIVA } = useTienda()
  const { datos, error, reintentar } = useConsulta(obtenerProducto, id)
  const producto = datos?.producto
  useTitulo(producto?.nombre ?? 'Producto')

  if (error?.status === 404) return <NoEncontrada {...NO_ENCONTRADO} />
  if (error) return <ErrorCarga mensaje={error.mensaje} onReintentar={reintentar} />
  if (!producto) return <Cargando />

  const { iva } = desglosarIVA(producto.precio, tasaIVA)

  return (
    <article>
      <nav aria-label="Ruta" className="text-sm text-gray-500">
        <ol className="flex flex-wrap gap-1">
          <li>
            <Link to="/" className="hover:text-emerald-800 hover:underline">
              Inicio
            </Link>
            <span aria-hidden="true"> /</span>
          </li>
          <li>
            <Link
              to={`/categoria/${producto.categoria.id}`}
              className="hover:text-emerald-800 hover:underline"
            >
              {producto.categoria.nombre}
            </Link>
            <span aria-hidden="true"> /</span>
          </li>
          <li aria-current="page" className="text-gray-700">
            {producto.nombre}
          </li>
        </ol>
      </nav>

      <div className="mt-6 grid gap-8 md:grid-cols-[1fr_20rem]">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">{producto.nombre}</h1>
          <p className="mt-2 text-sm text-gray-600">
            Categoría:{' '}
            <Link
              to={`/categoria/${producto.categoria.id}`}
              className="text-emerald-800 hover:underline"
            >
              {producto.categoria.nombre}
            </Link>
          </p>

          <h2 className="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">
            Descripción
          </h2>
          <p className="mt-2 text-gray-800 whitespace-pre-line">
            {producto.descripcion || 'Este producto todavía no tiene descripción.'}
          </p>
        </div>

        <aside
          aria-label="Precio y disponibilidad"
          className="self-start rounded-xl border border-gray-200 bg-white p-6"
        >
          <p className="text-3xl font-bold text-gray-900">{formatoMoneda(producto.precio)}</p>
          <p className="mt-1 text-sm text-gray-600">
            Precio por {producto.unidad}, IVA incluido ({formatoMoneda(iva)})
          </p>

          <div className="mt-5 flex flex-col items-start gap-2">
            <Disponibilidad valor={producto.disponibilidad} />
            <p className="text-sm text-gray-600">{textoExistencia(producto)}</p>
          </div>
        </aside>
      </div>
    </article>
  )
}
