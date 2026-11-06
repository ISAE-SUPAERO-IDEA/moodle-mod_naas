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
      exclude: ['src/main.ts', 'src/types/*', 'src/**/*.spec.ts'],
      // A little below the measured values (2026-11-06: lines 47.4, statements 46.4,
      // functions 42.6, branches 41.7), so coverage cannot drop unnoticed.
      thresholds: {
        lines: 45,
        statements: 44,
        functions: 40,
        branches: 39,
      },
    }
  }
})
