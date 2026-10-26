import { useSearchParams } from 'react-router-dom'
import { obtenerProductos } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useTitulo } from '../TiendaContext'
import { useCategorias } from './CategoriasContext'
import ListaProductos from './ListaProductos'
import { leerFiltros } from './filtros'

/**
 * N3: resultados de la búsqueda del encabezado. Sin texto muestra todo el catálogo.
 *
 * @returns {JSX.Element} La página
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Busqueda() {
  const [params] = useSearchParams()
  const filtros = leerFiltros(params)
  const consulta = useConsulta(obtenerProductos, filtros)
  const { categorias } = useCategorias()
  const titulo = filtros.q ? `Resultados para «${filtros.q}»` : 'Todos los productos'
  useTitulo(titulo)

  return (
    <section>
      <h1 className="text-2xl font-bold text-gray-900 mb-4">{titulo}</h1>
      <ListaProductos
        filtros={filtros}
        consulta={consulta}
        categorias={categorias}
        vacio={
          filtros.q
            ? `No encontramos productos para «${filtros.q}». Revisa cómo lo escribiste o prueba con otra palabra.`
            : 'No hay productos con estos filtros.'
        }
      />
    </section>
  )
}
