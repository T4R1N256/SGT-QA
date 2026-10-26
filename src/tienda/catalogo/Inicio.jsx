import { Link } from 'react-router-dom'
import { obtenerDestacados } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useTienda, useTitulo } from '../TiendaContext'
import { useCategorias } from './CategoriasContext'
import { cuenta } from '../../compartido/format'
import Cargando from '../componentes/Cargando'
import CuadriculaProductos from '../componentes/CuadriculaProductos'
import ErrorCarga from '../componentes/ErrorCarga'

/**
 * N1: el cliente entra y ve las categorías y los productos destacados, sin iniciar sesión.
 *
 * @returns {JSX.Element} La página
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Inicio() {
  const { nombre } = useTienda()
  const categorias = useCategorias()
  const destacados = useConsulta(obtenerDestacados)
  useTitulo(null)

  return (
    <div className="space-y-12">
      <section>
        <h1 className="text-3xl font-bold text-gray-900">Bienvenido a {nombre}</h1>
        <p className="mt-2 text-gray-600">
          Explora el catálogo por categoría o busca lo que necesitas. Los precios ya incluyen IVA y
          el envío es gratis.
        </p>
      </section>

      <section aria-labelledby="titulo-categorias">
        <h2 id="titulo-categorias" className="text-xl font-semibold text-gray-900 mb-4">
          Categorías
        </h2>
        {categorias.error ? (
          <ErrorCarga mensaje={categorias.error.mensaje} onReintentar={categorias.reintentar} />
        ) : categorias.cargando && categorias.categorias.length === 0 ? (
          <Cargando />
        ) : (
          <ul className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {categorias.categorias.map((categoria) => (
              <li key={categoria.id}>
                <Link
                  to={`/categoria/${categoria.id}`}
                  className="h-full flex flex-col rounded-xl border border-gray-200 bg-white px-4 py-5 hover:border-emerald-600 hover:shadow-sm"
                >
                  <span className="font-medium text-gray-900">{categoria.nombre}</span>
                  <span className="mt-1 text-sm text-gray-500">
                    {cuenta(categoria.productos, 'producto')}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section aria-labelledby="titulo-destacados">
        <div className="flex items-baseline justify-between mb-4">
          <h2 id="titulo-destacados" className="text-xl font-semibold text-gray-900">
            Productos destacados
          </h2>
          <Link to="/buscar" className="text-sm text-emerald-800 hover:underline">
            Ver todos los productos
          </Link>
        </div>
        {destacados.error ? (
          <ErrorCarga mensaje={destacados.error.mensaje} onReintentar={destacados.reintentar} />
        ) : destacados.cargando && !destacados.datos ? (
          <Cargando />
        ) : (
          <CuadriculaProductos
            productos={destacados.datos.productos}
            etiqueta="Productos destacados"
          />
        )}
      </section>
    </div>
  )
}
