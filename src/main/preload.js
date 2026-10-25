const { contextBridge, ipcRenderer } = require('electron')

// Most handled via PHP backend
contextBridge.exposeInMainWorld('electronAPI', {
  ping: () => ipcRenderer.invoke('ping'),
  // Regla 4: avisa al proceso principal si hay una caja abierta
  setCajaAbierta: (abierta) => ipcRenderer.send('caja:estado', Boolean(abierta)),
  // Convierte un documento HTML a PDF y lo guarda en Documentos/SGT/<carpeta>; regresa { ok, ruta } o { ok: false, mensaje }
  generarPDF: (opciones) => ipcRenderer.invoke('pdf:generar', opciones),
})