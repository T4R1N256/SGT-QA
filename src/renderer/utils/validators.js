/**
 * @file Validaciones del formulario de Usuarios (RF13).
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { limpiarTexto } from './texto'

const TEL_REGEX = /^\d+$/
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

/**
 * Valida un teléfono opcional: 10 o 12 dígitos, la misma regla que campoTelefono en el backend.
 *
 * @param {string} value Teléfono sin espacios
 * @returns {string|null} El mensaje de error, o null si es válido o viene vacío
 */
export function validateTelefono(value) {
  if (!value) return null
  if (!TEL_REGEX.test(value) || (value.length !== 10 && value.length !== 12))
    return 'El teléfono debe tener 10 o 12 dígitos numéricos'
  return null
}

/**
 * Valida un correo opcional.
 *
 * @param {string} value Correo
 * @returns {string|null} El mensaje de error, o null si es válido o viene vacío
 */
export function validateCorreo(value) {
  if (!value) return null
  if (!EMAIL_REGEX.test(value)) return 'El formato del correo no es válido'
  return null
}

/**
 * Valida el formulario de usuario: obligatorios, contraseñas iguales, teléfono y correo.
 * Al editar, la contraseña es opcional: solo se compara si se escribe una nueva.
 *
 * @param {Object} form Valores del formulario (nombre, puesto, estado, password, confirmar, telefono, correo)
 * @param {'create'|'edit'} mode Alta o edición
 * @returns {{message: string, field: string}|null} El primer error y el campo que hay que marcar, o null si es válido
 */
export function validateUserForm(form, mode) {
  const requiredMissing =
    mode === 'create'
      ? !limpiarTexto(form.nombre) || !form.puesto || !form.password || !form.confirmar
      : !limpiarTexto(form.nombre) || !form.puesto || !form.estado

  if (requiredMissing) {
    return {
      message:
        mode === 'create'
          ? 'Nombre, contraseña y puesto son obligatorios'
          : 'Nombre, puesto y estado son obligatorios',
      field: 'required',
    }
  }

  const password = form.password.trim()
  const confirmar = form.confirmar.trim()
  if ((mode === 'create' || password) && password !== confirmar)
    return { message: 'Las contraseñas no coinciden', field: 'confirmar' }

  const telError = validateTelefono(limpiarTexto(form.telefono))
  if (telError) return { message: telError, field: 'telefono' }

  const correoError = validateCorreo(limpiarTexto(form.correo))
  if (correoError) return { message: correoError, field: 'correo' }

  return null
}
