/**
 * @file Validación de los datos de entrega. Son las mismas reglas que aplica el servidor
 * (backend/models/entrega.php); el servidor siempre vuelve a validar.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

export const ENTIDADES = [
  'Aguascalientes',
  'Baja California',
  'Baja California Sur',
  'Campeche',
  'Chiapas',
  'Chihuahua',
  'Ciudad de México',
  'Coahuila',
  'Colima',
  'Durango',
  'Estado de México',
  'Guanajuato',
  'Guerrero',
  'Hidalgo',
  'Jalisco',
  'Michoacán',
  'Morelos',
  'Nayarit',
  'Nuevo León',
  'Oaxaca',
  'Puebla',
  'Querétaro',
  'Quintana Roo',
  'San Luis Potosí',
  'Sinaloa',
  'Sonora',
  'Tabasco',
  'Tamaulipas',
  'Tlaxcala',
  'Veracruz',
  'Yucatán',
  'Zacatecas',
]

// Campo: [obligatorio, máximo, mensaje si falta, etiqueta, verbo]
export const CAMPOS_ENTREGA = {
  recibe: [true, 150, 'Escribe quién recibe el pedido', 'El nombre de quien recibe', 'puede'],
  telefono: [true, 20, 'Escribe un teléfono de contacto', 'El teléfono', 'puede'],
  calle: [true, 100, 'Escribe la calle', 'La calle', 'puede'],
  numero_exterior: [true, 10, 'Escribe el número exterior', 'El número exterior', 'puede'],
  numero_interior: [false, 10, '', 'El número interior', 'puede'],
  colonia: [true, 100, 'Escribe la colonia', 'La colonia', 'puede'],
  codigo_postal: [true, 5, 'Escribe el código postal', 'El código postal', 'puede'],
  ciudad: [true, 100, 'Escribe la ciudad o municipio', 'La ciudad o municipio', 'puede'],
  entidad: [true, 40, 'Elige la entidad', 'La entidad', 'puede'],
  referencias: [false, 255, '', 'Las referencias', 'pueden'],
}

const NOMBRE = /^\p{L}[\p{L}\p{M} '.-]*$/u
// Número exterior o interior: 123, 123-B, S/N, #12, Depto. 4
const NUMERO = /^(?=.*[\p{L}\d])[\p{L}\d#][\p{L}\d \-/#.]*$/u

const limpiar = (texto) =>
  String(texto ?? '')
    .replace(/\s+/gu, ' ')
    .trim()

/**
 * Valida los datos de entrega: obligatorios, largo máximo y formato (nombre, teléfono de 10 dígitos,
 * números exterior e interior, código postal de 5 dígitos y entidad de la lista).
 *
 * @param {Object} datos Valores del formulario (los campos de CAMPOS_ENTREGA)
 * @returns {Object<string, string>} { campo: mensaje } con los campos inválidos ({} si todo está bien)
 * @see Entrega::validar
 */
export function validarEntrega(datos) {
  const errores = {}
  const valores = {}

  for (const [campo, [obligatorio, maximo, falta, etiqueta, puede]] of Object.entries(
    CAMPOS_ENTREGA
  )) {
    let valor = limpiar(datos[campo])
    if (campo === 'telefono') valor = valor.replace(/[\s()-]/g, '')
    valores[campo] = valor

    if (valor === '') {
      if (obligatorio) errores[campo] = falta
    } else if ([...valor].length > maximo) {
      errores[campo] = `${etiqueta} no ${puede} tener más de ${maximo} caracteres`
    }
  }

  const formato = (campo, valido, mensaje) => {
    if (!errores[campo] && valores[campo] !== '' && !valido) errores[campo] = mensaje
  }
  formato(
    'recibe',
    NOMBRE.test(valores.recibe),
    'El nombre de quien recibe solo puede tener letras, espacios, apóstrofos y guiones'
  )
  formato('telefono', /^\d{10}$/.test(valores.telefono), 'El teléfono debe tener 10 dígitos')
  formato(
    'numero_exterior',
    NUMERO.test(valores.numero_exterior),
    'El número exterior solo puede tener letras, números, espacios y - / # .'
  )
  formato(
    'numero_interior',
    NUMERO.test(valores.numero_interior),
    'El número interior solo puede tener letras, números, espacios y - / # .'
  )
  formato(
    'codigo_postal',
    /^\d{5}$/.test(valores.codigo_postal),
    'El código postal debe tener 5 dígitos'
  )
  formato('entidad', ENTIDADES.includes(valores.entidad), 'Elige una entidad de la lista')

  return errores
}

/**
 * Clave de cada confirmación: si el mismo envío llega dos veces (doble clic, P12) el servidor
 * regresa el pedido que ya creó. getRandomValues funciona también fuera de HTTPS (randomUUID no).
 *
 * @returns {string} 32 caracteres hexadecimales al azar
 * @see Pedido::folioPorClave
 */
export function nuevaClave() {
  const bytes = crypto.getRandomValues(new Uint8Array(16))
  return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
}

export const ESTADOS_PEDIDO = { pendiente_pago: 'Pendiente de pago' }
