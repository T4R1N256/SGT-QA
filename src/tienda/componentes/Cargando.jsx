export default function Cargando({ texto = 'Cargando…' }) {
  return (
    <p role="status" className="py-12 text-center text-sm text-gray-500">
      {texto}
    </p>
  )
}
