const { contextBridge, ipcRenderer } = require('electron')

// Most handled via PHP backend
contextBridge.exposeInMainWorld('electronAPI', {
  ping: () => ipcRenderer.invoke('ping'),
  // Regla 4: avisa al proceso principal si hay una caja abierta
  setCajaAbierta: (abierta) => ipcRenderer.send('caja:estado', Boolean(abierta)),
})