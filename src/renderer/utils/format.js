export function capitalize(str) {
  if (typeof str !== 'string' || str.length === 0) return ''
  return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase()
}

const numero = new Intl.NumberFormat('es-MX')

// 1250 → 1,250
export function formatoNumero(valor) {
  const n = Number(valor)
  return numero.format(Number.isFinite(n) ? n : 0)
}

// 1 producto, 2 productos
export function cuenta(n, singular, plural = `${singular}s`) {
  return `${formatoNumero(n)} ${Number(n) === 1 ? singular : plural}`
}
