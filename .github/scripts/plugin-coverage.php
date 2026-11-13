<?php
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
 * CI only: line coverage of mod_naas from a PHPUnit clover report, with a minimum.
 *
 * The clover report covers all of Moodle; only the plugin files outside tests/ and vue/ count.
 * Usage: php plugin-coverage.php coverage.xml MINIMUM_PERCENT
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

[, $report, $minimum] = $argv + [null, 'coverage.xml', '0'];
$xml = @simplexml_load_file($report);
if ($xml === false) {
    fwrite(STDERR, "Cannot read the coverage report {$report}\n");
    exit(1);
}

$total = 0;
$covered = 0;
$files = [];
foreach ($xml->xpath('//file') as $file) {
    $name = (string) $file['name'];
    $pos = strpos($name, '/mod/naas/');
    if ($pos === false || preg_match('#/mod/naas/(tests|vue)/#', $name)) {
        continue;
    }
    $statements = (int) $file->metrics['statements'];
    $hits = (int) $file->metrics['coveredstatements'];
    $total += $statements;
    $covered += $hits;
    $files[substr($name, $pos + strlen('/mod/naas/'))] = [$hits, $statements];
}
$percent = $total ? 100 * $covered / $total : 0;

$lines = ["### PHP coverage of mod_naas", "", sprintf('**%.1f%%** of lines (%d / %d), minimum %s%%.', $percent, $covered,
    $total, $minimum), "", "| File | Lines |", "|---|---|"];
uasort($files, fn($a, $b) => ($a[0] / max(1, $a[1])) <=> ($b[0] / max(1, $b[1])));
foreach ($files as $name => [$hits, $statements]) {
    $lines[] = sprintf('| `%s` | %.1f%% (%d / %d) |', $name, 100 * $hits / max(1, $statements), $hits, $statements);
}
$summary = implode("\n", $lines) . "\n";
echo $summary;
if ($path = getenv('GITHUB_STEP_SUMMARY')) {
    file_put_contents($path, $summary, FILE_APPEND);
}

if ($percent < (float) $minimum) {
    fwrite(STDERR, sprintf("::error::PHP coverage of mod_naas is %.1f%%, below the %s%% minimum.\n", $percent, $minimum));
    exit(1);
}
