import { limpiarTexto } from './texto'
import { validateTelefono } from './validators'

// Formularios de Categorías y Proveedores: las mismas reglas que el API (routes/categoryRoute.php y supplierRoute.php)

const ESTADOS = ['activo', 'inactivo']

export function parseCategoria(form) {
  return { nombre: limpiarTexto(form.nombre), estado: form.estado }
}

export function validateCategoria(parsed) {
  if (!parsed.nombre) return { message: 'El nombre es obligatorio', field: 'nombre' }
  if (parsed.nombre.length > 50)
    return { message: 'El nombre no puede exceder 50 caracteres', field: 'nombre' }
  if (!ESTADOS.includes(parsed.estado)) return { message: 'Estado inválido', field: 'estado' }
  return null
}

export function parseProveedor(form) {
  return {
    nombre: limpiarTexto(form.nombre),
    telefono: limpiarTexto(form.telefono),
    estado: form.estado,
  }
}

// El teléfono es opcional y, si se escribe, lleva 10 o 12 dígitos (la misma regla que en Usuarios)
export function validateProveedor(parsed) {
  if (!parsed.nombre) return { message: 'El nombre es obligatorio', field: 'nombre' }
  if (parsed.nombre.length > 255)
    return { message: 'El nombre no puede exceder 255 caracteres', field: 'nombre' }
  const telError = validateTelefono(parsed.telefono)
  if (telError) return { message: telError, field: 'telefono' }
  if (!ESTADOS.includes(parsed.estado)) return { message: 'Estado inválido', field: 'estado' }
  return null
}

// Respuesta de error del API para el formulario: el API dice qué campo marcar (por ejemplo, un nombre repetido)
export function errorDelApi(res, campos, porDefecto) {
  return { type: 'error', message: res.mensaje ?? porDefecto, field: campos[res.campo] }
}

// Aviso antes de desactivar una categoría: con productos activos, la tienda en línea deja de mostrarlos
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
