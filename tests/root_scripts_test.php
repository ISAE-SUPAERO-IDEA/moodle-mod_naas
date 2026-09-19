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
 * Executes root-level mod_naas entry scripts under PHPUnit (see phpunit.xml coverage includes).
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

use advanced_testcase;

/**
 * Root script smoke tests (index, launch, settings). {@see view_php_test} covers view.php; {@see version_php_test} covers version.php.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class root_scripts_test extends advanced_testcase {

    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        parent::tearDown();
    }

    /**
     * index.php lists NaaS instances when the course has activities.
     */
    public function test_index_php_lists_activities(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'name' => 'Listed nugget',
        ]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->setUser($teacher);
        $_GET['id'] = $course->id;
        $_REQUEST['id'] = $course->id;

        ob_start();
        require $CFG->dirroot . '/mod/naas/index.php';
        $html = ob_get_clean();

        $this->assertStringContainsString(get_string('modulenameplural', 'naas'), $html);
        $this->assertStringContainsString('Listed nugget', $html);
        $this->assertStringContainsString('mod_index', $html);
    }

    /**
     * index.php shows the empty-state message when there are no NaaS modules.
     */
    public function test_index_php_empty_course(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->setUser($teacher);
        $_GET['id'] = $course->id;
        $_REQUEST['id'] = $course->id;

        ob_start();
        require $CFG->dirroot . '/mod/naas/index.php';
        $html = ob_get_clean();

        $this->assertStringContainsString(get_string('nonewmodules', 'naas'), $html);
    }

    /**
     * Under PHPUnit, CLI_SCRIPT is true: redirect() throws instead of sending headers.
     */
    public function test_index_php_missing_course_id_throws_redirect_exception_in_cli(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        try {
            require $CFG->dirroot . '/mod/naas/index.php';
            $this->fail('Expected moodle_exception for redirect under CLI');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
    }

    /**
     * launch.php runs LTI launch (typically API error notification or LTI form in output).
     */
    public function test_launch_php_outputs_lti_or_error(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->setUser($teacher);
        $_GET['id'] = $naas->cmid;
        $_REQUEST['id'] = $naas->cmid;

        ob_start();
        require $CFG->dirroot . '/mod/naas/launch.php';
        $html = ob_get_clean();

        $hasform = strpos($html, 'ltiLaunchForm') !== false;
        $haserror = strpos($html, get_string('cannot_get_nugget', 'naas')) !== false;
        $this->assertTrue($hasform || $haserror, 'Launch page should render LTI form or nugget load error');
    }

    /**
     * settings.php registers admin settings when hassiteconfig is true.
     */
    public function test_settings_php_registers_admin_settings(): void {
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
        $this->assertGreaterThanOrEqual(8, count($keys));
        $this->assertContains('naasnaas_endpoint', $keys);
    }
}
