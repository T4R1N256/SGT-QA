// Utilidades de formato que usan SGT de escritorio y la tienda en línea

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

const moneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' })

// 1234.5 → $1,234.50
export function formatoMoneda(monto) {
  const n = Number(monto)
  return moneda.format(Number.isFinite(n) ? n : 0)
}
