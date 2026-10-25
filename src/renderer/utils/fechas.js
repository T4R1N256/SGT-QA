// Fechas en formato AAAA-MM-DD con la hora local (toISOString usa UTC y de noche cambiaría el día)

export function fechaLocal(fecha = new Date()) {
  const anio = fecha.getFullYear()
  const mes = String(fecha.getMonth() + 1).padStart(2, '0')
  const dia = String(fecha.getDate()).padStart(2, '0')
  return `${anio}-${mes}-${dia}`
}

// Rango de los últimos `dias` días, incluyendo hoy
export function rangoDias(dias, hoy = new Date()) {
  const desde = new Date(hoy)
  desde.setDate(desde.getDate() - dias)
  return { desde: fechaLocal(desde), hasta: fechaLocal(hoy) }
}

// Fecha AAAA-MM-DD como Date local (new Date('2026-10-25') la tomaría en UTC)
export function aFecha(texto) {
  const [anio, mes, dia] = String(texto).split('-').map(Number)
  return new Date(anio, mes - 1, dia)
}

// Periodo de un reporte que contiene a `fecha` (AAAA-MM-DD):
// 'dia' → ese día, 'semana' → de lunes a domingo, 'mes' → del día 1 al último del mes
export function periodo(tipo, fecha) {
  const base = aFecha(fecha)

  if (tipo === 'semana') {
    const lunes = new Date(base)
    lunes.setDate(base.getDate() - ((base.getDay() + 6) % 7))
    const domingo = new Date(lunes)
    domingo.setDate(lunes.getDate() + 6)
    return { desde: fechaLocal(lunes), hasta: fechaLocal(domingo) }
  }
  if (tipo === 'mes') {
    const primero = new Date(base.getFullYear(), base.getMonth(), 1)
    const ultimo = new Date(base.getFullYear(), base.getMonth() + 1, 0)
    return { desde: fechaLocal(primero), hasta: fechaLocal(ultimo) }
  }
  return { desde: fechaLocal(base), hasta: fechaLocal(base) }
}

// 2026-10-25 → 25/10/2026
export function formatoFecha(fecha) {
  const [anio, mes, dia] = String(fecha ?? '').split('-')
  return dia ? `${dia}/${mes}/${anio}` : ''
}

// Texto del periodo: "25/10/2026" o "Del 01/10/2026 al 31/10/2026"
export function textoPeriodo(desde, hasta) {
  return desde === hasta ? formatoFecha(desde) : `Del ${formatoFecha(desde)} al ${formatoFecha(hasta)}`
}
