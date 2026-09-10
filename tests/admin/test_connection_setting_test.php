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
 * Tests for {@see \mod_naas\admin\test_connection_setting}.
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
use mod_naas\admin\test_connection_setting;

/**
 * @covers \mod_naas\admin\test_connection_setting
 */
final class test_connection_setting_test extends advanced_testcase {

    /**
     * Display-only setting: nothing is read from or written to config.
     */
    public function test_does_not_persist(): void {
        $setting = new test_connection_setting(
            'naas/test_connection_ui',
            'Test connection',
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

        $setting = new test_connection_setting(
            'naas/test_connection_ui',
            get_string('test_connection', 'naas'),
            get_string('test_connection_information', 'naas')
        );
        $html = $setting->output_html('', '');

        $this->assertStringContainsString('id="testconnection"', $html);
        $this->assertStringContainsString('id="connection-result"', $html);
        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringNotContainsString('<a href="#"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString(get_string('test_connection', 'naas'), $html);
        $this->assertStringContainsString(get_string('connection_test_testing', 'naas'), $html);
    }

    /**
     * settings.php groups connection fields before the test-connection control
     * and does not ship a default API password.
     */
    public function test_settings_php_registers_grouped_admin_settings(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/admin/settings.php', ['section' => 'modsettingnaas']));

        $hassiteconfig = true;
        $settings = new \admin_settingpage('modsettingnaas', 'NaaS');

        require $CFG->dirroot . '/mod/naas/settings.php';

        $keys = array_keys((array) $settings->settings);
        $this->assertContains('naasnaas_endpoint', $keys);
        $this->assertContains('naasnaas_username', $keys);
        $this->assertContains('naasnaas_password', $keys);
        $this->assertContains('naasnaas_structure_id', $keys);
        $this->assertContains('naastest_connection_ui', $keys);
        $this->assertContains('naasheading_about', $keys);
        $this->assertContains('naasheading_connection', $keys);
        $this->assertContains('naasheading_privacy', $keys);
        $this->assertContains('naasheading_learner', $keys);
        $this->assertContains('naasheading_catalogue', $keys);
        $this->assertContains('naasheading_appearance', $keys);
        $this->assertContains('naasheading_advanced', $keys);

        $endpointpos = array_search('naasnaas_endpoint', $keys, true);
        $testpos = array_search('naastest_connection_ui', $keys, true);
        $this->assertNotFalse($endpointpos);
        $this->assertNotFalse($testpos);
        $this->assertGreaterThan($endpointpos, $testpos);

        $this->assertSame('', $settings->settings->naasnaas_password->get_defaultsetting());
        $this->assertStringNotContainsString('h6teLq3cQangBLFE6qw8', file_get_contents($CFG->dirroot . '/mod/naas/settings.php'));
    }
}
