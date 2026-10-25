// Texto que se escribe en los formularios de SGT: solo se quitan los espacios al inicio y al final.
// Se guarda tal cual (el backend tampoco lo escapa) y React lo escapa al mostrarlo, así que "Pan & Cía <grande>"
// se ve igual en pantalla, en los tickets y en la tienda en línea.
// Antes se usaba DOMPurify: con un "<" en el texto lo devolvía como HTML ("&amp;", "&lt;") y borraba lo que
// pareciera etiqueta, y una contraseña con "<" ya no coincidía al iniciar sesión
export function limpiarTexto(valor) {
  return String(valor ?? '').trim()
}
