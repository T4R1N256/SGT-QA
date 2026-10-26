import clsx from 'clsx'
import { useSearchParams } from 'react-router-dom'
import { cuenta } from '../../compartido/format'
import Cargando from '../componentes/Cargando'
import CuadriculaProductos from '../componentes/CuadriculaProductos'
import ErrorCarga from '../componentes/ErrorCarga'
import Paginacion from '../componentes/Paginacion'
import { ORDENES, cambiarFiltros } from './filtros'

/**
 * Lista de productos con filtros, orden y paginación. Los cambios se escriben en la dirección;
 * la página que la usa vuelve a pedir los productos. Los controles no son controlados: cambian al instante
 * y, con la key, se reinician si la dirección cambia por otro lado (Atrás, Adelante o un enlace).
 *
 * @param {Object} props
 * @param {Object} props.filtros Filtros leídos de la dirección (leerFiltros)
 * @param {Object} props.consulta Resultado de useConsulta con los productos
 * @param {Array<Object>|null} [props.categorias=null] Categorías para filtrar (en la búsqueda)
 * @param {React.ReactNode} props.vacio Qué mostrar si no hay productos
 * @returns {JSX.Element} La lista
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function ListaProductos({ filtros, consulta, categorias = null, vacio }) {
  const [params, setParams] = useSearchParams()
  const { datos, error, cargando, reintentar } = consulta

  const cambiar = (cambios) => setParams(cambiarFiltros(params, cambios))
  const enlacePagina = (pagina) => ({ search: cambiarFiltros(params, { pagina }).toString() })

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
        <p className="text-gray-600 mr-auto" aria-live="polite">
          {datos ? cuenta(datos.total, 'producto') : ' '}
        </p>

        {categorias && (
          <label className="flex items-center gap-2">
            Categoría
            <select
              key={filtros.categoria}
              defaultValue={filtros.categoria ?? ''}
              onChange={(e) => cambiar({ categoria: e.target.value || null })}
              className="px-2 py-1.5 border border-gray-300 rounded-lg bg-white"
            >
              <option value="">Todas</option>
              {categorias.map((categoria) => (
                <option key={categoria.id} value={categoria.id}>
                  {categoria.nombre}
                </option>
              ))}
            </select>
          </label>
        )}

        <label className="flex items-center gap-2">
          <input
            key={String(filtros.disponibles)}
            type="checkbox"
            defaultChecked={filtros.disponibles}
            onChange={(e) => cambiar({ disponibles: e.target.checked })}
            className="w-4 h-4 accent-emerald-700"
          />
          Solo disponibles
        </label>

        <label className="flex items-center gap-2">
          Ordenar por
          <select
            key={filtros.orden}
            defaultValue={filtros.orden}
            onChange={(e) => cambiar({ orden: e.target.value })}
            className="px-2 py-1.5 border border-gray-300 rounded-lg bg-white"
          >
            {ORDENES.map((orden) => (
              <option key={orden.valor} value={orden.valor}>
                {orden.etiqueta}
              </option>
            ))}
          </select>
        </label>
      </div>

      {error ? (
        <ErrorCarga mensaje={error.mensaje} onReintentar={reintentar} />
      ) : !datos ? (
        <Cargando />
      ) : datos.productos.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-600">
          {datos.total > 0 ? 'Esta página no tiene productos.' : vacio}
        </div>
      ) : (
        <div className={clsx(cargando && 'opacity-60 transition-opacity')} aria-busy={cargando}>
          <CuadriculaProductos productos={datos.productos} etiqueta="Productos" />
          <Paginacion pagina={datos.pagina} paginas={datos.paginas} enlace={enlacePagina} />
        </div>
      )}
    </div>
  )
}
