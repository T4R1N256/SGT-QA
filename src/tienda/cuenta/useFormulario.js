/**
 * @file Hook para los formularios de la tienda en línea (cuenta y entrega).
 * @author Alejandro Tarin
 * @version 1.0.0
 * @since 1.0.0
 */

import { useRef, useState } from 'react'

/**
 * Estado de un formulario con validación por campo (P8–P10):
 * - al enviar se validan todos los campos y se enfoca el primero con error
 * - después del primer intento, cada campo se vuelve a validar mientras se escribe
 * - los errores del servidor ({ errores: { campo: mensaje } }) se muestran en su campo
 *
 * @param {Object<string, string>} inicial Valores iniciales; su orden es el de los campos en pantalla
 * @param {function(Object): Object<string, string>} validar Regresa { campo: mensaje } con los campos inválidos
 * @returns {{campo: function(string): Object, enviar: function(function(Object): Promise<Object>): function(Event),
 *           mensaje: (string|null), enviando: boolean}}
 *          campo(nombre) da las props del componente Campo; enviar(fn) da el onSubmit, que llama a fn(valores)
 *          con el API y regresa su respuesta
 */
export function useFormulario(inicial, validar) {
  const [valores, setValores] = useState(inicial)
  const [errores, setErrores] = useState({})
  const [intentado, setIntentado] = useState(false)
  const [mensaje, setMensaje] = useState(null)
  const [enviando, setEnviando] = useState(false)
  const refs = useRef({})

  const campo = (nombre) => ({
    name: nombre,
    value: valores[nombre],
    error: errores[nombre],
    ref: (el) => (refs.current[nombre] = el),
    onChange: (e) => {
      const nuevos = { ...valores, [nombre]: e.target.value }
      setValores(nuevos)
      setMensaje(null)
      if (intentado) setErrores(validar(nuevos))
    },
  })

  const enfocarPrimero = (encontrados) => {
    const primero = Object.keys(inicial).find((nombre) => encontrados[nombre])
    refs.current[primero]?.focus()
  }

  // enviarFn(valores) regresa la respuesta del API
  const enviar = (enviarFn) => async (e) => {
    e.preventDefault()
    if (enviando) return
    setIntentado(true)
    const encontrados = validar(valores)
    setErrores(encontrados)
    if (Object.keys(encontrados).length) {
      setMensaje('Revisa los datos marcados')
      enfocarPrimero(encontrados)
      return
    }

    setEnviando(true)
    const res = await enviarFn(valores)
    setEnviando(false)
    if (!res.ok) {
      setMensaje(res.mensaje)
      if (res.errores) {
        setErrores(res.errores)
        enfocarPrimero(res.errores)
      }
    }
    return res
  }

  return { campo, enviar, mensaje, enviando }
}
