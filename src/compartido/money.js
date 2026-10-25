// Montos en centavos enteros: las sumas con decimales (0.1 + 0.2) no acumulan errores
// y el total coincide con el que calcula el backend con los precios de la base de datos

export function aCentavos(monto) {
  const n = Number(monto)
  return Number.isFinite(n) ? Math.round(n * 100) : 0
}

// Total del carrito: suma de precio × cantidad de cada producto
export function totalCarrito(cart) {
  const centavos = cart.reduce(
    (suma, item) => suma + aCentavos(item.Precio) * (Number(item.cantidad) || 0),
    0
  )
  return centavos / 100
}

// Cambio a entregar; negativo si el pago no alcanza
export function calcularCambio(pago, total) {
  return (aCentavos(pago) - aCentavos(total)) / 100
}

// Desglose de un precio que ya incluye IVA (D2): subtotal = total ÷ (1 + tasa), redondeado a centavos,
// y el IVA es la diferencia, para que subtotal + IVA siempre dé el total exacto
export function desglosarIVA(total, tasa) {
  const centavos = aCentavos(total)
  const factor = 1 + (Number(tasa) || 0) / 100
  const subtotal = Math.round(centavos / factor)
  return { subtotal: subtotal / 100, iva: (centavos - subtotal) / 100 }
}
