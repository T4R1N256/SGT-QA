import { useParams, useSearchParams } from 'react-router-dom'
import { obtenerProductos } from '../api/tienda'
import { useConsulta } from '../hooks/useConsulta'
import { useTitulo } from '../TiendaContext'
import NoEncontrada from '../componentes/NoEncontrada'
import ListaProductos from './ListaProductos'
import { enteroPositivo, leerFiltros } from './filtros'

const NO_ENCONTRADA = {
  titulo: 'Categoría no encontrada',
  texto: 'La categoría que buscas no existe o ya no tiene productos a la venta.',
}

/**
 * N2: los productos de una categoría, paginados, con precio y disponibilidad.
 *
 * @returns {JSX.Element} La página
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function Categoria() {
  const id = enteroPositivo(useParams().id)
  return id ? <ProductosDeCategoria key={id} id={id} /> : <NoEncontrada {...NO_ENCONTRADA} />
}

function ProductosDeCategoria({ id }) {
  const [params] = useSearchParams()
  const filtros = { ...leerFiltros(params), q: '', categoria: id }
  const consulta = useConsulta(obtenerProductos, filtros)
  const categoria = consulta.datos?.categoria
  useTitulo(categoria?.nombre ?? 'Categoría')

  if (consulta.error?.status === 404) return <NoEncontrada {...NO_ENCONTRADA} />

  return (
    <section>
      <h1 className="text-2xl font-bold text-gray-900 mb-4">{categoria?.nombre ?? ' '}</h1>
      <ListaProductos
        filtros={filtros}
        consulta={consulta}
        vacio="No hay productos disponibles en esta categoría."
      />
    </section>
  )
}
