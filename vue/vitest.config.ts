import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    coverage: {
      provider: 'v8',
      reporter: ['text-summary', 'html', 'json-summary'],
      include: ['src/**/*.{ts,vue}'],
      // Setting exclude replaces vitest's defaults: list the spec files explicitly.
      exclude: ['src/main.ts', 'src/types/*', 'src/test/**', 'src/**/*.spec.ts'],
      // A little below the measured values (2026-11-06: lines 92.9, statements 92.6,
      // functions 93.3, branches 86.5), so coverage cannot drop unnoticed.
      thresholds: {
        lines: 90,
        statements: 90,
        functions: 90,
        branches: 84,
      },
    }
  }
})
