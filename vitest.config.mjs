import { defineConfig } from 'vitest/config'

// Pruebas del renderer: archivos *.test.js / *.test.jsx junto al código que prueban
export default defineConfig({
  test: {
    include: ['src/renderer/**/*.test.{js,jsx}'],
    environment: 'jsdom',
  },
})
