/**
 * @file Texto que se escribe en los formularios de SGT.
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

/**
 * Quita los espacios al inicio y al final, nada más. El texto se guarda tal cual (el backend tampoco
 * lo escapa) y React lo escapa al mostrarlo, así que "Pan & Cía <grande>" se ve igual en pantalla, en los tickets
 * y en la tienda en línea. Antes se usaba DOMPurify: con un "<" en el texto lo devolvía como HTML ("&amp;", "&lt;")
 * y borraba lo que pareciera etiqueta, y una contraseña con "<" ya no coincidía al iniciar sesión.
 *
 * @param {*} valor Lo que se escribió (null y undefined cuentan como '')
 * @returns {string} El texto sin espacios al inicio ni al final
 */
export function limpiarTexto(valor) {
  return String(valor ?? '').trim()
}
