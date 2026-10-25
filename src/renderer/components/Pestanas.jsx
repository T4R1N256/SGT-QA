// Pestañas para el encabezado de la página (con useHeader). opciones: [{ id, label }]
export default function Pestanas({ opciones, activa, onCambiar }) {
  return (
    <div className="flex gap-1 p-1 bg-gray-700 rounded-lg" role="tablist">
      {opciones.map((p) => (
        <button
          key={p.id}
          type="button"
          role="tab"
          aria-selected={activa === p.id}
          onClick={() => onCambiar(p.id)}
          className={`px-3 py-1 text-xs font-semibold rounded ${
            activa === p.id ? 'bg-white text-gray-900' : 'text-gray-300 hover:text-white'
          }`}
        >
          {p.label}
        </button>
      ))}
    </div>
  )
}
