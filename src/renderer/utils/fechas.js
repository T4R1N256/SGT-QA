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
