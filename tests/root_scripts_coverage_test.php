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
 * Runs the root entry scripts of mod_naas: index.php, view.php, launch.php, settings.php, version.php.
 *
 * These scripts hold no class or function, so no coverage target can name them:
 * a "covers" target keeps only the lines of that class or function and
 * "coversNothing" records nothing at all. This test class therefore declares no
 * coverage metadata, which is the only way their lines are recorded.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing

/**
 * Root script behaviour: access checks, redirects, rendered pages and registered settings.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class root_scripts_coverage_test extends \advanced_testcase {
    /** @var array Copy of $_SERVER taken in setUp(). */
    private $server = [];

    /**
     * Start every test with an empty request.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->server = $_SERVER;
        $_SERVER['SERVER_NAME'] = 'moodle.example.test';
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    /**
     * Clear the request, and the curl responses a failing test did not consume.
     */
    protected function tearDown(): void {
        $_SERVER = $this->server;
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $mocks = new \ReflectionProperty(\curl::class, 'mockresponses');
        $mocks->setAccessible(true);
        $mocks->setValue(null, []);
        parent::tearDown();
    }

    /**
     * Set the query string of the simulated request.
     *
     * @param array $params
     * @return void
     */
    private function request(array $params): void {
        $_GET = $params;
        $_REQUEST = $params;
    }

    /**
     * Require a root script of the plugin and return what it printed.
     *
     * @param string $script File name in mod/naas.
     * @return string
     */
    private function run_script(string $script): string {
        global $CFG;
        ob_start();
        try {
            require($CFG->dirroot . '/mod/naas/' . $script);
        } finally {
            $html = ob_get_clean();
        }
        return $html;
    }

    /**
     * Run a script that must stop with a redirect (an exception under PHPUnit).
     *
     * @param string $script
     * @return void
     */
    private function assert_script_redirects(string $script): void {
        try {
            $this->run_script($script);
            $this->fail("{$script} should redirect");
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
    }

    /**
     * Course with a Nugget activity and an enrolled user.
     *
     * @param string $role
     * @param array $course Course record fields.
     * @return array [course, naas module, user]
     */
    private function setup_activity(string $role = 'student', array $course = []): array {
        $course = $this->getDataGenerator()->create_course($course);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'name' => 'Root script nugget',
            'nugget_id' => 'nugget-root',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        return [$course, $naas, $user];
    }

    /**
     * Forbid mod/naas:view to every authenticated user in a course.
     *
     * @param int $courseid
     * @return void
     */
    private function prohibit_view(int $courseid): void {
        global $DB;
        foreach (['user', 'student'] as $shortname) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
            assign_capability('mod/naas:view', CAP_PROHIBIT, $roleid, \context_course::instance($courseid)->id, true);
        }
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * index.php without a course id sends the user home.
     */
    public function test_index_without_course_id_redirects(): void {
        $this->setAdminUser();
        $this->assert_script_redirects('index.php');
    }

    /**
     * index.php with an unknown course id sends the user home.
     */
    public function test_index_with_unknown_course_redirects(): void {
        $this->setAdminUser();
        $this->request(['id' => 987654]);
        $this->assert_script_redirects('index.php');
    }

    /**
     * index.php refuses a user who may not view Nugget activities in the course.
     */
    public function test_index_without_view_capability_redirects(): void {
        [$course, , $user] = $this->setup_activity();
        $this->prohibit_view($course->id);
        $this->setUser($user);
        $this->request(['id' => $course->id]);

        $this->assert_script_redirects('index.php');
    }

    /**
     * index.php lists the activities with their section, and dims hidden ones.
     */
    public function test_index_lists_activities_by_section(): void {
        global $PAGE;
        [$course, $naas, $user] = $this->setup_activity('editingteacher', ['format' => 'topics']);
        $hidden = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'name' => 'Hidden nugget',
            'visible' => 0,
        ]);
        $this->setUser($user);
        $this->request(['id' => $course->id]);

        $html = $this->run_script('index.php');

        $this->assertStringContainsString('Root script nugget', $html);
        $this->assertStringContainsString('Hidden nugget', $html);
        $this->assertStringContainsString('/mod/naas/view.php?id=' . $naas->cmid, $html);
        $this->assertStringContainsString('/mod/naas/view.php?id=' . $hidden->cmid, $html);
        $sectionnum = get_fast_modinfo($course)->get_cm($naas->cmid)->sectionnum;
        $this->assertStringContainsString(get_section_name($course, $sectionnum), $html);
        $this->assertStringContainsString(get_string('back_to_course', 'naas'), $html);
        $this->assertSame('/mod/naas/index.php?id=' . $course->id, $PAGE->url->out_as_local_url(false));
        $this->assertSame('incourse', $PAGE->pagelayout);
    }

    /**
     * index.php on a course without Nugget activity says so and offers to go back.
     */
    public function test_index_without_activities_shows_notice(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $this->request(['id' => $course->id]);

        $html = $this->run_script('index.php');

        $this->assertStringContainsString(get_string('nonewmodules', 'naas'), $html);
        $this->assertStringContainsString(get_string('modulenameplural', 'naas'), $html);
        $this->assertStringContainsString('/course/view.php', $html);
        $this->assertStringContainsString('name="id" value="' . $course->id . '"', $html);
    }

    /**
     * view.php by course module id renders the widget, logs the view and sets the page.
     */
    public function test_view_by_course_module_id(): void {
        global $PAGE, $DB;
        [$course, $naas, $user] = $this->setup_activity('student', ['shortname' => 'ROOTV']);
        $this->setUser($user);
        $this->request(['id' => $naas->cmid]);
        $sink = $this->redirectEvents();

        $html = $this->run_script('view.php');

        $events = array_filter($sink->get_events(), function ($event) {
            return $event instanceof \mod_naas\event\course_module_viewed;
        });
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertStringContainsString('nugget-root', $html);
        $this->assertStringContainsString(get_string('back_to_course', 'naas'), $html);
        $this->assertStringStartsWith('ROOTV: Root script nugget', $PAGE->title);
        $this->assertSame((int) $naas->cmid, (int) $PAGE->cm->id);
        $this->assertTrue($DB->record_exists('naas', ['id' => $naas->id]));
    }

    /**
     * view.php sends the learner back to the activity's section, not to the top of the course.
     */
    public function test_view_back_link_targets_the_activity_section(): void {
        // Default topics format: every section on one page, so the link carries an anchor.
        [$course, $naas, $user] = $this->setup_activity('student', ['shortname' => 'ROOTS']);
        $section = get_fast_modinfo($course)->get_cm($naas->cmid)->sectionnum;
        $this->setUser($user);
        $this->request(['id' => $naas->cmid]);

        $html = $this->run_script('view.php');

        $expected = course_get_url($course, $section)->out();
        $this->assertStringContainsString('#section-' . $section, $expected);
        $this->assertStringContainsString('href="' . $expected . '"', $html);
    }

    /**
     * view.php also accepts the instance id (u=).
     */
    public function test_view_by_instance_id(): void {
        global $PAGE;
        [, $naas, $user] = $this->setup_activity();
        $this->setUser($user);
        $this->request(['u' => $naas->id]);

        $html = $this->run_script('view.php');

        $this->assertStringContainsString('nugget-root', $html);
        $this->assertSame('/mod/naas/view.php?id=' . $naas->cmid, $PAGE->url->out_as_local_url(false));
    }

    /**
     * view.php sends away a user without mod/naas:view: the activity is not available to them.
     */
    public function test_view_without_capability_is_refused(): void {
        [$course, $naas, $user] = $this->setup_activity();
        $this->prohibit_view($course->id);
        $this->setUser($user);
        $this->request(['id' => $naas->cmid]);

        $this->assert_script_redirects('view.php');
    }

    /**
     * view.php with an unknown instance id fails before any output.
     */
    public function test_view_with_unknown_instance_fails(): void {
        $this->setAdminUser();
        $this->request(['u' => 987654]);

        $this->expectException(\dml_missing_record_exception::class);
        $this->run_script('view.php');
    }

    /**
     * launch.php renders a standalone page holding the signed LTI form, and records the launch.
     */
    public function test_launch_renders_lti_form(): void {
        global $DB;
        set_config('naas_endpoint', 'https://naas.example.test/api', 'naas');
        [, $naas, $user] = $this->setup_activity();
        $this->setUser($user);
        $this->request(['id' => $naas->cmid]);
        // Served last queued first: nugget data, then its LTI configuration.
        \curl::mock_response(json_encode(['payload' => [
            'url' => 'https://player.example.test/lti',
            'key' => 'lti-key',
            'secret' => 'lti-secret',
        ]]));
        \curl::mock_response(json_encode(['payload' => ['version_id' => 'v-1']]));

        $html = $this->run_script('launch.php');

        $this->assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">", $html);
        $this->assertStringContainsString('<title>Root script nugget</title>', $html);
        $this->assertStringContainsString('id="ltiLaunchForm"', $html);
        $this->assertStringContainsString('action="https://player.example.test/lti"', $html);
        $this->assertStringContainsString('name="oauth_consumer_key" value="lti-key"', $html);
        $this->assertStringEndsWith("</body>\n</html>\n", $html);
        $this->assertSame(1, $DB->count_records('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
        ]));
    }

    /**
     * launch.php shows NaaS failures inline, inside the same standalone page.
     */
    public function test_launch_renders_naas_error_inline(): void {
        global $DB;
        // Blocked by the curl security helper: fails at once, without network.
        set_config('naas_endpoint', 'http://127.0.0.1:9/api', 'naas');
        [, $naas, $user] = $this->setup_activity();
        $this->setUser($user);
        $this->request(['id' => $naas->cmid, 'language' => 'fr']);

        $html = $this->run_script('launch.php');

        $this->assertStringContainsString('naas-launch-error', $html);
        $this->assertStringNotContainsString('ltiLaunchForm', $html);
        $this->assertSame(0, $DB->count_records('naas_activity_outcome', ['activity_id' => $naas->cmid]));
    }

    /**
     * launch.php sends away a user without mod/naas:view, before any launch is recorded.
     */
    public function test_launch_without_capability_is_refused(): void {
        global $DB;
        [$course, $naas, $user] = $this->setup_activity();
        $this->prohibit_view($course->id);
        $this->setUser($user);
        $this->request(['id' => $naas->cmid]);

        $this->assert_script_redirects('launch.php');
        $this->assertSame(0, $DB->count_records('naas_activity_outcome', ['activity_id' => $naas->cmid]));
    }

    /**
     * launch.php requires the course module id.
     */
    public function test_launch_requires_id(): void {
        $this->setAdminUser();
        try {
            $this->run_script('launch.php');
            $this->fail('launch.php without id must fail');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingparam', $e->errorcode);
        }
    }

    /**
     * Load settings.php into a fresh settings page.
     *
     * @param bool $hassiteconfig
     * @return \admin_settingpage
     */
    private function load_settings(bool $hassiteconfig): \admin_settingpage {
        global $CFG, $PAGE;
        $settings = new \admin_settingpage('modsettingnaas', 'NaaS');
        require($CFG->dirroot . '/mod/naas/settings.php');
        return $settings;
    }

    /**
     * Description of the "Advanced" heading.
     *
     * @param \admin_settingpage $settings
     * @return string
     */
    private function advanced_information(\admin_settingpage $settings): string {
        return $settings->settings->naasheading_advanced->description;
    }

    /**
     * Without site configuration capability nothing is registered.
     */
    public function test_settings_need_site_config(): void {
        $settings = $this->load_settings(false);
        $this->assertSame([], array_keys((array) $settings->settings));
    }

    /**
     * Every setting is registered, and the help script only on the plugin settings page.
     */
    public function test_settings_register_every_setting(): void {
        global $PAGE;
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/settings.php', ['section' => 'modsettingnaas']));
        $PAGE->set_pagetype('admin-setting-modsettingnaas');

        $settings = $this->load_settings(true);

        $expected = [
            'naasheading_about', 'naasheading_connection', 'naasnaas_endpoint', 'naasnaas_username',
            'naasnaas_password', 'naasnaas_structure_id', 'naastest_connection_ui', 'naasheading_cache',
            'naasrefresh_cache_ui', 'naasheading_privacy', 'naasnaas_privacy_learner_mail',
            'naasnaas_privacy_learner_name', 'naasheading_learner', 'naasnaas_feedback', 'naasnaas_nugbot',
            'naasheading_catalogue', 'naasnaas_license_filter', 'naasnaas_access_filter', 'naasnaas_filter',
            'naasheading_appearance', 'naasnaas_css', 'naasheading_advanced', 'naasnaas_timeout',
            'naasnaas_ssl_noverify', 'naasnaas_require_outcome_oauth', 'naasnaas_refresh_limit',
        ];
        $this->assertSame($expected, array_keys((array) $settings->settings));
        $this->assertInstanceOf(admin\action_button_setting::class, $settings->settings->naastest_connection_ui);
        $this->assertSame('https://api.naas-edu.eu/api', $settings->settings->naasnaas_endpoint->defaultsetting);
        $this->assertSame(
            (string) task\refresh_catalogue::DEFAULT_LIMIT,
            $settings->settings->naasnaas_refresh_limit->defaultsetting
        );
        $this->assertStringContainsString('mod_naas/admin_settings', $PAGE->requires->get_end_code());
        $this->assertStringContainsString(
            get_string('naas_settings_require_outcome_oauth_off', 'naas'),
            $this->advanced_information($settings)
        );
    }

    /**
     * Other admin pages that include settings.php do not load the help script.
     */
    public function test_settings_skip_help_script_on_other_pages(): void {
        global $PAGE;
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/search.php'));
        $PAGE->set_pagetype('admin-search');

        $this->load_settings(true);

        $this->assertStringNotContainsString('mod_naas/admin_settings', $PAGE->requires->get_end_code());
    }

    /**
     * The advanced heading warns about disabled SSL checks and reports that NaaS signs outcomes.
     */
    public function test_settings_advanced_information_reflects_configuration(): void {
        $this->setAdminUser();
        set_config('naas_ssl_noverify', 1, 'naas');
        set_config('naas_outcome_signed_seen', 1700000000, 'naas');

        $information = $this->advanced_information($this->load_settings(true));

        $this->assertStringContainsString(get_string('naas_settings_ssl_noverify_active', 'naas'), $information);
        $this->assertStringContainsString(get_string(
            'naas_settings_require_outcome_oauth_ready',
            'naas',
            userdate(1700000000, get_string('strftimedatetime', 'langconfig'))
        ), $information);

        // Once OAuth is required there is nothing left to advise.
        set_config('naas_require_outcome_oauth', 1, 'naas');
        $this->assertSame(
            get_string('naas_settings_advanced_information', 'naas') . "\n\n"
                . get_string('naas_settings_ssl_noverify_active', 'naas'),
            $this->advanced_information($this->load_settings(true))
        );
    }

    /**
     * version.php declares the component, a stable release and its dependency on mod_lti.
     */
    public function test_version_php_declares_the_plugin(): void {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/mod/naas/version.php');

        $this->assertSame('mod_naas', $plugin->component);
        $this->assertMatchesRegularExpression('/^20\d{8}$/', (string) $plugin->version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $plugin->release);
        $this->assertSame(MATURITY_STABLE, $plugin->maturity);
        $this->assertSame(['mod_lti'], array_keys($plugin->dependencies));
        $this->assertSame(get_config('mod_naas', 'version'), (string) $plugin->version);
    }
}
