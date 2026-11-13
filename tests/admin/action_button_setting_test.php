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
 * Tests for {@see \mod_naas\admin\action_button_setting}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\admin;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\admin\action_button_setting::class)]
/**
 * Tests for the action buttons of the plugin settings page.
 *
 * @covers \mod_naas\admin\action_button_setting
 */
final class action_button_setting_test extends advanced_testcase {
    /**
     * The two buttons of the settings page, as settings.php declares them.
     *
     * @return array
     */
    public static function button_provider(): array {
        return [
            'test connection' => ['naas/test_connection_ui', [
                'buttonid' => 'testconnection',
                'resultid' => 'connection-result',
                'methodname' => 'mod_naas_test_config',
                'label' => 'Test connection',
                'pendinglabel' => 'Testing…',
                'successstring' => 'connection_test_success',
                'failurestring' => 'connection_test_failed',
            ]],
            'refresh cache' => ['naas/refresh_cache_ui', [
                'buttonid' => 'refreshcache',
                'resultid' => 'cache-refresh-result',
                'methodname' => 'mod_naas_refresh_cache',
                'label' => 'Refresh cache',
                'pendinglabel' => 'Refreshing…',
                'successstring' => 'cache_refresh_success',
                'failurestring' => 'cache_refresh_failed',
            ]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('button_provider')]
    /**
     * Display-only setting: nothing is read from or written to config.
     *
     * @dataProvider button_provider
     * @param string $name
     * @param array $action
     */
    public function test_does_not_persist(string $name, array $action): void {
        $setting = new action_button_setting($name, $action['label'], 'Info', $action);

        $this->assertTrue($setting->nosave);
        $this->assertTrue($setting->get_setting());
        $this->assertTrue($setting->get_defaultsetting());
        $this->assertSame('', $setting->write_setting('anything'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('button_provider')]
    /**
     * output_html() renders a real button that carries its web service and strings,
     * an aria-live result region, and loads the shared AMD module for that button.
     *
     * @dataProvider button_provider
     * @param string $name
     * @param array $action
     */
    public function test_output_renders_the_button_and_its_result_region(string $name, array $action): void {
        global $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/admin/settings.php', ['section' => 'modsettingnaas']));

        $setting = new action_button_setting($name, $action['label'], 'Info', $action);
        $html = $setting->output_html('', '');

        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringContainsString('id="' . $action['buttonid'] . '"', $html);
        $this->assertStringContainsString('data-methodname="' . $action['methodname'] . '"', $html);
        $this->assertStringContainsString('data-result-id="' . $action['resultid'] . '"', $html);
        $this->assertStringContainsString('data-success-string="' . $action['successstring'] . '"', $html);
        $this->assertStringContainsString('data-failure-string="' . $action['failurestring'] . '"', $html);
        $this->assertStringContainsString('id="' . $action['resultid'] . '"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString($action['label'], $html);
        $this->assertStringContainsString($action['pendinglabel'], $html);
        $this->assertStringNotContainsString('<a href="#"', $html);

        $js = $PAGE->requires->get_end_code();
        $this->assertStringContainsString('mod_naas/action_button', $js);
        $this->assertStringContainsString($action['buttonid'], $js);
    }

    /**
     * A button without its web service or strings is a programming error.
     */
    public function test_missing_action_parameter_is_rejected(): void {
        $this->expectException(\coding_exception::class);
        new action_button_setting('naas/broken_ui', 'Broken', 'Info', ['buttonid' => 'broken']);
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
        $PAGE->set_pagetype('admin-setting-modsettingnaas');

        $hassiteconfig = true;
        $settings = new \admin_settingpage('modsettingnaas', 'NaaS');

        if ($hassiteconfig) {
            require($CFG->dirroot . '/mod/naas/settings.php');
        }

        $keys = array_keys((array) $settings->settings);
        $this->assertContains('naasnaas_endpoint', $keys);
        $this->assertContains('naasnaas_username', $keys);
        $this->assertContains('naasnaas_password', $keys);
        $this->assertContains('naasnaas_structure_id', $keys);
        $this->assertContains('naastest_connection_ui', $keys);
        $this->assertContains('naasheading_cache', $keys);
        $this->assertContains('naasrefresh_cache_ui', $keys);
        $this->assertContains('naasheading_about', $keys);
        $this->assertContains('naasheading_connection', $keys);
        $this->assertContains('naasheading_privacy', $keys);
        $this->assertContains('naasheading_learner', $keys);
        $this->assertContains('naasheading_catalogue', $keys);
        $this->assertContains('naasheading_appearance', $keys);
        $this->assertContains('naasheading_advanced', $keys);

        $endpointpos = array_search('naasnaas_endpoint', $keys, true);
        $testpos = array_search('naastest_connection_ui', $keys, true);
        $cachepos = array_search('naasrefresh_cache_ui', $keys, true);
        $this->assertNotFalse($endpointpos);
        $this->assertNotFalse($testpos);
        $this->assertNotFalse($cachepos);
        $this->assertGreaterThan($endpointpos, $testpos);
        $this->assertGreaterThan($testpos, $cachepos);

        $this->assertSame('', $settings->settings->naasnaas_password->get_defaultsetting());
        $this->assertContains('naasnaas_feedback', $keys);
        $this->assertContains('naasnaas_nugbot', $keys);
        $this->assertEquals(0, $settings->settings->naasnaas_nugbot->get_defaultsetting());
        $this->assertContains('naasnaas_license_filter', $keys);
        $this->assertSame(
            \mod_naas\catalogue_filters::LICENSE_ALL,
            $settings->settings->naasnaas_license_filter->get_defaultsetting()
        );
        $this->assertContains('naasnaas_access_filter', $keys);
        $this->assertSame(
            \mod_naas\catalogue_filters::ACCESS_ALL,
            $settings->settings->naasnaas_access_filter->get_defaultsetting()
        );
        $this->assertStringNotContainsString('h6teLq3cQangBLFE6qw8', file_get_contents($CFG->dirroot . '/mod/naas/settings.php'));
        $this->assertStringContainsString(
            'mod_naas/admin_settings',
            $PAGE->requires->get_end_code()
        );
    }
}
