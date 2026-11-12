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
 * Bumps the Vue bundle version in the two places that define it:
 * BUNDLE_VERSION in vite.config.ts (build output name) and
 * widget::BUNDLE_VERSION in classes/output/widget.php (URL served by Moodle).
 *
 * Usage:  node scripts/bump-version.js [VERSION]
 * If VERSION is omitted, defaults to today's date as YYYYMMDDnn (nn = 00).
 *
 * The plugin version ($plugin->version in version.php) is deliberately NOT
 * touched: it follows the upgrade savepoints, not the bundle. Run `pnpm build`
 * afterwards so assets/vue/ contains the renamed bundle.
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

const VITE_CONFIG = resolve(VUE,  'vite.config.ts')
const WIDGET_PHP  = resolve(ROOT, 'classes/output/widget.php')

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
    console.error(`  [error] no match in ${file}`)
    process.exitCode = 1
  } else {
    writeFileSync(file, after, 'utf-8')
    console.log(`  updated ${file}`)
  }
}

console.log(`Bumping the widget bundle to ${newVersion}…`)

replace(
  VITE_CONFIG,
  /const BUNDLE_VERSION = ['"]\d{10}['"]/,
  `const BUNDLE_VERSION = "${newVersion}"`
)

replace(
  WIDGET_PHP,
  /const BUNDLE_VERSION = '\d{10}';/,
  `const BUNDLE_VERSION = '${newVersion}';`
)

console.log('Done. Now run `pnpm build` and commit assets/vue/ with these two files.')
