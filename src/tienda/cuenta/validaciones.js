/**
 * @file Validación de los formularios de cuenta. Son las mismas reglas que aplica el servidor
 * (backend/models/cliente.php); el servidor siempre vuelve a validar.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

// Letras (con acentos), espacios, apóstrofos, puntos y guiones: "María José", "O'Connor", "Pérez-Díaz"
const NOMBRE = /^\p{L}[\p{L}\p{M} '.-]*$/u
const CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const largo = (texto) => [...texto].length

/**
 * Valida el formulario de registro: nombre y apellidos, correo, teléfono de 10 dígitos y contraseña
 * de 8 a 72 caracteres con letras y números.
 *
 * @param {{nombre: string, apellidos: string, correo: string, telefono: string, password: string, confirmar: string}} datos
 *        Valores del formulario
 * @returns {Object<string, string>} { campo: mensaje } con los campos inválidos ({} si todo está bien)
 * @see Cliente::validar
 */
export function validarRegistro({
  nombre = '',
  apellidos = '',
  correo = '',
  telefono = '',
  password = '',
  confirmar = '',
}) {
  const errores = {}

  const nombres = [
    ['nombre', nombre.trim(), 'tu nombre', 'El nombre', 'puede'],
    ['apellidos', apellidos.trim(), 'tus apellidos', 'Los apellidos', 'pueden'],
  ]
  for (const [campo, valor, falta, etiqueta, puede] of nombres) {
    if (valor === '') errores[campo] = `Escribe ${falta}`
    else if (largo(valor) > 100)
      errores[campo] = `${etiqueta} no ${puede} tener más de 100 caracteres`
    else if (!NOMBRE.test(valor))
      errores[campo] = `${etiqueta} solo ${puede} tener letras, espacios, apóstrofos y guiones`
  }

  const email = correo.trim()
  if (email === '') errores.correo = 'Escribe tu correo'
  else if (largo(email) > 254) errores.correo = 'El correo no puede tener más de 254 caracteres'
  else if (!CORREO.test(email)) errores.correo = 'Escribe un correo válido, como nombre@ejemplo.com'

  const digitos = telefono.replace(/[\s()-]/g, '')
  if (digitos === '') errores.telefono = 'Escribe tu teléfono'
  else if (!/^\d{10}$/.test(digitos)) errores.telefono = 'El teléfono debe tener 10 dígitos'

  if (password === '') errores.password = 'Escribe una contraseña'
  else if (largo(password) < 8) errores.password = 'La contraseña debe tener al menos 8 caracteres'
  else if (new TextEncoder().encode(password).length > 72)
    errores.password = 'La contraseña no puede tener más de 72 caracteres'
  else if (!/\p{L}/u.test(password) || !/\d/.test(password))
    errores.password = 'La contraseña debe tener letras y números'

  if (!errores.password && confirmar !== password)
    errores.confirmar = 'Las contraseñas no coinciden'

  return errores
}

/**
 * Valida el formulario de ingreso: correo y contraseña obligatorios.
 *
 * @param {{correo: string, password: string}} datos Valores del formulario
 * @returns {Object<string, string>} { campo: mensaje } con los campos que faltan
 */
export function validarIngreso({ correo = '', password = '' }) {
  const errores = {}
  if (correo.trim() === '') errores.correo = 'Escribe tu correo'
  if (password === '') errores.password = 'Escribe tu contraseña'
  return errores
}

/**
 * A dónde regresar después de ingresar (?siguiente=/compra/entrega). Solo rutas de la propia tienda:
 * "//sitio.com" o una URL completa llevarían a otro sitio.
 *
 * @param {*} siguiente Valor del parámetro siguiente
 * @param {string} [porDefecto='/cuenta'] Ruta si el valor no es seguro
 * @returns {string} La ruta a la que se regresa
 */
export function destinoSeguro(siguiente, porDefecto = '/cuenta') {
  if (typeof siguiente !== 'string' || !siguiente.startsWith('/') || /^\/[/\\]/.test(siguiente))
    return porDefecto
  return siguiente
}
