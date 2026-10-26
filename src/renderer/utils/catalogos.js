/**
 * @file Formularios de Categorías y Proveedores: las mismas reglas que el API
 * (routes/categoryRoute.php y supplierRoute.php).
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { limpiarTexto } from './texto'
import { validateTelefono } from './validators'

// Formularios de Categorías y Proveedores: las mismas reglas que el API (routes/categoryRoute.php y supplierRoute.php)

const ESTADOS = ['activo', 'inactivo']

/**
 * Datos de la categoría que se mandan al API, con el nombre sin espacios sobrantes.
 *
 * @param {{nombre: string, estado: string}} form Valores del formulario
 * @returns {{nombre: string, estado: string}} Los datos limpios
 */
export function parseCategoria(form) {
  return { nombre: limpiarTexto(form.nombre), estado: form.estado }
}

/**
 * Valida la categoría: nombre obligatorio de hasta 50 caracteres y estado activo o inactivo.
 *
 * @param {{nombre: string, estado: string}} parsed Datos de parseCategoria()
 * @returns {{message: string, field: string}|null} El error y el campo que hay que marcar, o null si es válida
 */
export function validateCategoria(parsed) {
  if (!parsed.nombre) return { message: 'El nombre es obligatorio', field: 'nombre' }
  if (parsed.nombre.length > 50)
    return { message: 'El nombre no puede exceder 50 caracteres', field: 'nombre' }
  if (!ESTADOS.includes(parsed.estado)) return { message: 'Estado inválido', field: 'estado' }
  return null
}

/**
 * Datos del proveedor que se mandan al API, sin espacios sobrantes.
 *
 * @param {{nombre: string, telefono: string, estado: string}} form Valores del formulario
 * @returns {{nombre: string, telefono: string, estado: string}} Los datos limpios
 */
export function parseProveedor(form) {
  return {
    nombre: limpiarTexto(form.nombre),
    telefono: limpiarTexto(form.telefono),
    estado: form.estado,
  }
}

/**
 * Valida el proveedor: nombre obligatorio de hasta 255 caracteres, teléfono opcional de 10 o 12 dígitos
 * (la misma regla que en Usuarios) y estado activo o inactivo.
 *
 * @param {{nombre: string, telefono: string, estado: string}} parsed Datos de parseProveedor()
 * @returns {{message: string, field: string}|null} El error y el campo que hay que marcar, o null si es válido
 * @see validateTelefono
 */
export function validateProveedor(parsed) {
  if (!parsed.nombre) return { message: 'El nombre es obligatorio', field: 'nombre' }
  if (parsed.nombre.length > 255)
    return { message: 'El nombre no puede exceder 255 caracteres', field: 'nombre' }
  const telError = validateTelefono(parsed.telefono)
  if (telError) return { message: telError, field: 'telefono' }
  if (!ESTADOS.includes(parsed.estado)) return { message: 'Estado inválido', field: 'estado' }
  return null
}

/**
 * Respuesta de error del API para el formulario: el API dice qué campo marcar (por ejemplo, un nombre repetido).
 *
 * @param {{mensaje?: string, campo?: string}} res Respuesta del API
 * @param {Object<string, string>} campos Campo del API → campo del formulario
 * @param {string} porDefecto Mensaje si el API no manda uno
 * @returns {{type: 'error', message: string, field: (string|undefined)}} El aviso para el formulario
 */
export function errorDelApi(res, campos, porDefecto) {
  return { type: 'error', message: res.mensaje ?? porDefecto, field: campos[res.campo] }
}

/**
 * Aviso antes de desactivar una categoría: con productos activos, la tienda en línea deja de mostrarlos.
 *
 * @param {string} nombre Nombre de la categoría
 * @param {number} productos Productos activos de la categoría
 * @returns {string} El texto del aviso para confirmar
 */
export function avisoDesactivarCategoria(nombre, productos) {
  if (!productos) {
    return `¿Desactivar «${nombre}»? No podrá usarse para productos nuevos hasta que la vuelvas a activar.`
  }
  const cuantos = productos === 1 ? '1 producto activo' : `${productos} productos activos`
  return (
    `«${nombre}» tiene ${cuantos}. Si la desactivas, la tienda en línea deja de mostrarlos y no podrá ` +
    'usarse para productos nuevos. En SGT se siguen vendiendo y puedes volver a activarla cuando quieras.'
  )
}
