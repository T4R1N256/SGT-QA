import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

// Pruebas de SGT (renderer), de la tienda y de las utilidades compartidas: *.test.js / *.test.jsx junto al código
// El plugin de React usa el mismo JSX automático que la app
export default defineConfig({
  plugins: [react()],
  test: {
    include: ['src/{renderer,tienda,compartido}/**/*.test.{js,jsx}'],
    environment: 'jsdom',
  },
})
