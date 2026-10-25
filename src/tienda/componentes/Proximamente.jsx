// Página que todavía no existe: indica en qué fase del plan (PLAN_TIENDA.md) se agrega
export default function Proximamente({ titulo, fase }) {
  return (
    <section className="py-16 text-center">
      <h1 className="text-2xl font-semibold text-gray-800">{titulo}</h1>
      <p className="mt-2 text-gray-500">Esta sección se agrega en la fase {fase}.</p>
    </section>
  )
}
