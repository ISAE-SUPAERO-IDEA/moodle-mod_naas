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
 * Tests for mod/naas/version.php (plugin version metadata loaded by core upgrade / plugins UI).
 *
 * When bumping $plugin->version, $plugin->release, $plugin->requires, or $plugin->dependencies,
 * update the assertions in this file to match version.php.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;

/**
 * Checks the metadata declared in version.php.
 *
 * @coversNothing
 */
final class version_php_test extends advanced_testcase {
    /**
     * Require version.php and assert every public metadata field used by core.
     */
    public function test_version_php_defines_complete_plugin_object(): void {
        global $CFG, $plugin;

        $plugin = new \stdClass();
        require($CFG->dirroot . '/mod/naas/version.php');

        $this->assertSame('mod_naas', $plugin->component);
        $this->assertMatchesRegularExpression('/^20\d{8}$/', (string) $plugin->version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $plugin->release);
        $this->assertSame(2022041900, (int) $plugin->requires);
        $this->assertSame(0, (int) $plugin->cron);
        $this->assertSame(MATURITY_STABLE, $plugin->maturity);

        $this->assertIsArray($plugin->dependencies);
        $this->assertArrayHasKey('mod_lti', $plugin->dependencies);
        $this->assertGreaterThanOrEqual(2022041900, (int) $plugin->dependencies['mod_lti']);
    }

    /**
     * The savepoints in db/upgrade.php must be unique, ascending, and never above the plugin version.
     */
    public function test_version_php_is_not_below_last_upgrade_savepoint(): void {
        global $CFG, $plugin;

        $plugin = new \stdClass();
        require($CFG->dirroot . '/mod/naas/version.php');

        $upgrade = file_get_contents($CFG->dirroot . '/mod/naas/db/upgrade.php');
        preg_match_all("/upgrade_mod_savepoint\(true,\s*(\d+),\s*'naas'\)/", $upgrade, $matches);
        $this->assertNotEmpty($matches[1], 'db/upgrade.php must declare at least one savepoint.');

        $savepoints = array_map('intval', $matches[1]);
        $this->assertSame(array_values(array_unique($savepoints)), $savepoints, 'Savepoints must be unique.');

        $sorted = $savepoints;
        sort($sorted);
        $this->assertSame($sorted, $savepoints, 'Savepoints must be in ascending order.');
        $this->assertGreaterThanOrEqual(max($savepoints), (int) $plugin->version);
    }

    /**
     * Fresh process require keeps coverage attribution clean for the version file (same as other root scripts).
     *
     * @runInSeparateProcess
     */
    public function test_version_php_loads_in_separate_process(): void {
        global $CFG, $plugin;

        $plugin = new \stdClass();
        require($CFG->dirroot . '/mod/naas/version.php');

        $this->assertSame('mod_naas', $plugin->component);
        $this->assertGreaterThan(0, (int) $plugin->version);
    }
}
