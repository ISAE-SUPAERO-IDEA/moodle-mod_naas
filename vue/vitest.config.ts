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
      // Everything is covered (2026-11-06). Lines that only a race or a direct
      // misuse could reach carry a commented `v8 ignore` hint.
      thresholds: {
        lines: 100,
        statements: 100,
        functions: 100,
        branches: 100,
      },
    }
  }
})
