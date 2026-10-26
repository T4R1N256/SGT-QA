import { Link } from 'react-router-dom'
import { useTitulo } from '../TiendaContext'

/**
 * Dirección que no existe (también producto o categoría inexistentes, P14).
 *
 * @param {Object} props
 * @param {string} [props.titulo='Página no encontrada'] Título
 * @param {string} [props.texto] Explicación
 * @returns {JSX.Element} La página
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */
export default function NoEncontrada({
  titulo = 'Página no encontrada',
  texto = 'La dirección que abriste no existe en la tienda.',
}) {
  useTitulo(titulo)

  return (
    <section className="py-16 text-center">
      <h1 className="text-2xl font-semibold text-gray-800">{titulo}</h1>
      <p className="mt-2 text-gray-500">{texto}</p>
      <div className="mt-6 flex justify-center gap-3">
        <Link
          to="/"
          className="px-4 py-2 rounded-lg bg-emerald-700 text-white hover:bg-emerald-800"
        >
          Ir al inicio
        </Link>
        <Link
          to="/buscar"
          className="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100"
        >
          Ver todos los productos
        </Link>
      </div>
    </section>
  )
}
