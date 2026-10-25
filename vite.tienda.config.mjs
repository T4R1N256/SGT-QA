import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// Tienda en línea: app web independiente de Electron (src/tienda)
// Se compila a dist-tienda/, que en Docker sirve nginx en el mismo origen que /api/tienda.
// En desarrollo (npm run dev:tienda) /api se reenvía al backend: el de Docker (8001) o el de TIENDA_API.
// El proxy conserva el Host original para que el backend reconozca las peticiones como del mismo origen.
export default defineConfig({
  root: fileURLToPath(new URL('./src/tienda', import.meta.url)),
  plugins: [react(), tailwindcss()],
  build: {
    outDir: fileURLToPath(new URL('./dist-tienda', import.meta.url)),
    emptyOutDir: true,
  },
  server: {
    port: 5174,
    strictPort: true,
    proxy: { '/api': { target: process.env.TIENDA_API ?? 'http://localhost:8001', changeOrigin: false } },
  },
})
