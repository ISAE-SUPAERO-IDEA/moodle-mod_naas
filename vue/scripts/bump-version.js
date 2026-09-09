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
 * Bumps BUNDLE_VERSION in vite.config.ts, amd/src/widget_init.js, and version.php
 * in one command so all three stay in sync.
 *
 * Usage:  node scripts/bump-version.js [VERSION]
 * If VERSION is omitted, defaults to today's date as YYYYMMDDnn (nn = 00).
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { readFileSync, writeFileSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))

const ROOT = resolve(__dirname, '../..')  // mod/naas root
const VUE  = resolve(__dirname, '..')     // mod/naas/vue

const VITE_CONFIG   = resolve(VUE,  'vite.config.ts')
const WIDGET_INIT   = resolve(ROOT, 'amd/src/widget_init.js')
const VERSION_PHP   = resolve(ROOT, 'version.php')

function todayVersion() {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}${m}${day}00`
}

const newVersion = process.argv[2] ?? todayVersion()

if (!/^\d{10}$/.test(newVersion)) {
  console.error(`Invalid version "${newVersion}". Expected 10 digits (YYYYMMDDnn).`)
  process.exit(1)
}

function replace(file, pattern, replacement) {
  const before = readFileSync(file, 'utf-8')
  const after = before.replace(pattern, replacement)
  if (before === after) {
    console.warn(`  [warn] no match in ${file}`)
  } else {
    writeFileSync(file, after, 'utf-8')
    console.log(`  updated ${file}`)
  }
}

console.log(`Bumping to version ${newVersion}…`)

replace(
  VITE_CONFIG,
  /const BUNDLE_VERSION = '\d{10}'/,
  `const BUNDLE_VERSION = '${newVersion}'`
)

replace(
  WIDGET_INIT,
  /naas_widget-\d{10}\.js/g,
  `naas_widget-${newVersion}.js`
)

replace(
  VERSION_PHP,
  /\$plugin->version\s*=\s*\d{10};/,
  `$plugin->version        = ${newVersion};`
)

console.log('Done.')
