import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

// Pruebas del renderer: archivos *.test.js / *.test.jsx junto al código que prueban
// El plugin de React usa el mismo JSX automático que la app
export default defineConfig({
  plugins: [react()],
  test: {
    include: ['src/renderer/**/*.test.{js,jsx}'],
    environment: 'jsdom',
  },
})
