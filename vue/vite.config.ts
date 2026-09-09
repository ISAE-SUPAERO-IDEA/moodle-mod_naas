// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Vite build configuration for the NaaS Vue 3 widget.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import { readFileSync } from 'fs'

// Bump this when releasing — mirrors the AMD widget_init.js reference.
const BUNDLE_VERSION = '2026030300'

export default defineConfig(({ mode }) => ({
  root: __dirname,
  plugins: [
    vue(),
    // Inject window.NAAS dev config into the HTML served locally.
    mode === 'development'
      ? {
          name: 'inject-naas-dev-config',
          transformIndexHtml(html: string) {
            const raw = readFileSync(resolve(__dirname, 'dev_config.js'), 'utf-8')
            // Strip the CommonJS wrapper to extract the object literal.
            const match = raw.match(/module\.exports\s*=\s*(\{[\s\S]*\});?\s*$/)
            if (!match) return html
            return html.replace(
              '<!-- NAAS_DEV_CONFIG -->',
              `<script>window.NAAS = ${match[1]}</script>`
            )
          },
        }
      : null,
  ],
  // Replace process.env.NODE_ENV with a string literal so the IIFE bundle
  // has no reference to Node globals when loaded in a browser via Moodle.
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  build: {
    lib: {
      entry: resolve(__dirname, 'src/main.ts'),
      formats: ['iife'],
      name: 'NaasWidget',
      fileName: () => `naas_widget-${BUNDLE_VERSION}.js`,
    },
    outDir: resolve(__dirname, '../assets/vue'),
    emptyOutDir: false,
    cssCodeSplit: false,
    rollupOptions: {
      output: {
        // Inline all dynamic imports so we ship one file.
        inlineDynamicImports: true,
      },
    },
  },
}))
