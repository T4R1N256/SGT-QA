const { contextBridge, ipcRenderer } = require('electron')

// URL del API: el proceso principal levanta PHP en un puerto libre y la pasa como argumento
const argumentoAPI = process.argv.find((a) => a.startsWith('--sgt-api='))

// Most handled via PHP backend
contextBridge.exposeInMainWorld('electronAPI', {
  apiURL: argumentoAPI ? argumentoAPI.slice('--sgt-api='.length) : null,
  ping: () => ipcRenderer.invoke('ping'),
  // Regla 4: avisa al proceso principal si hay una caja abierta
  setCajaAbierta: (abierta) => ipcRenderer.send('caja:estado', Boolean(abierta)),
  // Convierte un documento HTML a PDF y lo guarda en Documentos/SGT/<carpeta>; regresa { ok, ruta } o { ok: false, mensaje }
  generarPDF: (opciones) => ipcRenderer.invoke('pdf:generar', opciones),
})
