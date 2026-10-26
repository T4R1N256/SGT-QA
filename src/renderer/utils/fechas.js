/**
 * @file Fechas en formato AAAA-MM-DD con la hora local (toISOString usa UTC y de noche cambiaría el día).
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

/**
 * Fecha en formato AAAA-MM-DD con la hora local.
 *
 * @param {Date} [fecha=new Date()] Fecha
 * @returns {string} La fecha, por ejemplo 2026-10-25
 */
export function fechaLocal(fecha = new Date()) {
  const anio = fecha.getFullYear()
  const mes = String(fecha.getMonth() + 1).padStart(2, '0')
  const dia = String(fecha.getDate()).padStart(2, '0')
  return `${anio}-${mes}-${dia}`
}

/**
 * Rango de los últimos `dias` días, incluyendo hoy.
 *
 * @param {number} dias Días hacia atrás
 * @param {Date} [hoy=new Date()] Fecha final
 * @returns {{desde: string, hasta: string}} El rango en formato AAAA-MM-DD
 */
export function rangoDias(dias, hoy = new Date()) {
  const desde = new Date(hoy)
  desde.setDate(desde.getDate() - dias)
  return { desde: fechaLocal(desde), hasta: fechaLocal(hoy) }
}

/**
 * Convierte una fecha AAAA-MM-DD en Date local (new Date('2026-10-25') la tomaría en UTC).
 *
 * @param {string} texto Fecha AAAA-MM-DD
 * @returns {Date} La fecha a medianoche, hora local
 */
export function aFecha(texto) {
  const [anio, mes, dia] = String(texto).split('-').map(Number)
  return new Date(anio, mes - 1, dia)
}

/**
 * Periodo de un reporte que contiene a `fecha`: 'dia' es ese día, 'semana' de lunes a domingo
 * y 'mes' del día 1 al último del mes.
 *
 * @param {'dia'|'semana'|'mes'} tipo Tipo de periodo
 * @param {string} fecha Fecha AAAA-MM-DD dentro del periodo
 * @returns {{desde: string, hasta: string}} El periodo en formato AAAA-MM-DD
 */
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

/**
 * Fecha para mostrar: 2026-10-25 → 25/10/2026.
 *
 * @param {string} fecha Fecha AAAA-MM-DD
 * @returns {string} La fecha DD/MM/AAAA ('' si viene vacía)
 */
export function formatoFecha(fecha) {
  const [anio, mes, dia] = String(fecha ?? '').split('-')
  return dia ? `${dia}/${mes}/${anio}` : ''
}

/**
 * Texto del periodo: "25/10/2026" o "Del 01/10/2026 al 31/10/2026".
 *
 * @param {string} desde Fecha inicial AAAA-MM-DD
 * @param {string} hasta Fecha final AAAA-MM-DD
 * @returns {string} El periodo para mostrar
 */
export function textoPeriodo(desde, hasta) {
  return desde === hasta ? formatoFecha(desde) : `Del ${formatoFecha(desde)} al ${formatoFecha(hasta)}`
}
