import { Link } from 'react-router-dom'

export default function NoEncontrada() {
  return (
    <section className="py-16 text-center">
      <h1 className="text-2xl font-semibold text-gray-800">Página no encontrada</h1>
      <p className="mt-2 text-gray-500">La dirección que abriste no existe en la tienda.</p>
      <Link to="/" className="inline-block mt-6 px-4 py-2 rounded-lg bg-emerald-700 text-white hover:bg-emerald-800">
        Ir al inicio
      </Link>
    </section>
  )
}
