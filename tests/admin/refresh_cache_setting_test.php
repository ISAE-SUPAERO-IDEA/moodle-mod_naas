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
 * Tests for {@see \mod_naas\admin\refresh_cache_setting}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests\admin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

use advanced_testcase;
use mod_naas\admin\refresh_cache_setting;

/**
 * @covers \mod_naas\admin\refresh_cache_setting
 */
final class refresh_cache_setting_test extends advanced_testcase {

    /**
     * Display-only setting: nothing is read from or written to config.
     */
    public function test_does_not_persist(): void {
        $setting = new refresh_cache_setting(
            'naas/refresh_cache_ui',
            'Refresh cache',
            'Info'
        );

        $this->assertTrue($setting->nosave);
        $this->assertTrue($setting->get_setting());
        $this->assertTrue($setting->get_defaultsetting());
        $this->assertSame('', $setting->write_setting('anything'));
    }

    /**
     * output_html() renders a real button and an aria-live result region.
     */
    public function test_output_contains_button_and_result_region(): void {
        global $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/admin/settings.php', ['section' => 'modsettingnaas']));

        $setting = new refresh_cache_setting(
            'naas/refresh_cache_ui',
            get_string('cache_refresh', 'naas'),
            get_string('cache_refresh_information', 'naas')
        );
        $html = $setting->output_html('', '');

        $this->assertStringContainsString('id="refreshcache"', $html);
        $this->assertStringContainsString('id="cache-refresh-result"', $html);
        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString(get_string('cache_refresh', 'naas'), $html);
        $this->assertStringContainsString(get_string('cache_refresh_testing', 'naas'), $html);
    }
}
