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
 * Tests for {@see mod/naas/version.php} (plugin version metadata loaded by core upgrade / plugins UI).
 *
 * When bumping $plugin->version, $plugin->release, $plugin->requires, or $plugin->dependencies,
 * update the assertions in this file to match version.php.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;

/**
 * @coversNothing
 */
final class version_php_test extends advanced_testcase {

    /**
     * Require version.php and assert every public metadata field used by core.
     */
    public function test_version_php_defines_complete_plugin_object(): void {
        global $CFG, $plugin;

        $plugin = new \stdClass();
        require $CFG->dirroot . '/mod/naas/version.php';

        $this->assertSame('mod_naas', $plugin->component);
        $this->assertSame(2026091700, (int) $plugin->version);
        $this->assertSame('3.0.0', $plugin->release);
        $this->assertSame(2022041900, (int) $plugin->requires);
        $this->assertSame(0, (int) $plugin->cron);
        $this->assertSame(MATURITY_STABLE, $plugin->maturity);

        $this->assertIsArray($plugin->dependencies);
        $this->assertCount(2, $plugin->dependencies);
        $this->assertSame(20191030, (int) $plugin->dependencies['mod_url']);
        $this->assertSame(2022041900, (int) $plugin->dependencies['mod_lti']);
    }

    /**
     * Fresh process require keeps coverage attribution clean for the version file (same as other root scripts).
     *
     * @runInSeparateProcess
     * @backupGlobals disabled
     * @preserveGlobalState disabled
     */
    public function test_version_php_loads_in_separate_process(): void {
        global $CFG, $plugin;

        $plugin = new \stdClass();
        require $CFG->dirroot . '/mod/naas/version.php';

        $this->assertSame('mod_naas', $plugin->component);
        $this->assertGreaterThan(0, (int) $plugin->version);
    }
}
