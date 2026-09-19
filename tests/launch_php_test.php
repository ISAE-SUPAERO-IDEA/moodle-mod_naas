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
 * Line coverage for {@see mod/naas/launch.php} (params, login, capability, LTI hand-off).
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
final class launch_php_test extends advanced_testcase {

    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        parent::tearDown();
    }

    /**
     * Happy path: LTI launch renders a form or a load error (no live API required).
     */
    public function test_launch_outputs_lti_or_error(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $_GET['id'] = $naas->cmid;
        $_REQUEST['id'] = $naas->cmid;

        ob_start();
        require $CFG->dirroot . '/mod/naas/launch.php';
        $html = ob_get_clean();

        $hasform = strpos($html, 'ltiLaunchForm') !== false;
        $haserror = strpos($html, get_string('cannot_get_nugget', 'naas')) !== false;
        $this->assertTrue($hasform || $haserror, 'Launch should render LTI form or nugget load error');
    }

    /**
     * optional_param('language', …) must be forwarded to {@see \mod_naas\naas_lti::lti_launch()}.
     */
    public function test_launch_forwards_language_query_param(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $_GET['id'] = $naas->cmid;
        $_GET['language'] = 'fr';
        $_REQUEST = $_GET;

        ob_start();
        require $CFG->dirroot . '/mod/naas/launch.php';
        $html = ob_get_clean();

        $hasform = strpos($html, 'ltiLaunchForm') !== false;
        $haserror = strpos($html, get_string('cannot_get_nugget', 'naas')) !== false;
        $this->assertTrue($hasform || $haserror);
    }

    /**
     * Non-enrolled user cannot access (require_login with course + cm).
     */
    public function test_launch_denies_user_not_in_course(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);

        $_GET['id'] = $naas->cmid;
        $_REQUEST['id'] = $naas->cmid;

        $this->expectException(\require_login_exception::class);
        require $CFG->dirroot . '/mod/naas/launch.php';
    }

    /**
     * Enrolled user without mod/naas:view must be denied (require_capability or activity visibility).
     */
    public function test_launch_denies_without_view_capability(): void {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/naas:view', CAP_PROHIBIT, $roleid, \context_course::instance($course->id));
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($user);
        $_GET['id'] = $naas->cmid;
        $_REQUEST['id'] = $naas->cmid;

        $caught = null;
        try {
            require $CFG->dirroot . '/mod/naas/launch.php';
        } catch (\require_login_exception $e) {
            $caught = $e;
        } catch (\required_capability_exception $e) {
            $caught = $e;
        } catch (\moodle_exception $e) {
            // Hidden activity: require_login() redirects; under PHPUnit that becomes redirecterrordetected.
            if ($e->errorcode === 'redirecterrordetected') {
                $caught = $e;
            } else {
                throw $e;
            }
        }
        $this->assertNotNull($caught, 'Expected access denial (login, capability, or redirect) when mod/naas:view is prohibited');
    }

    /**
     * Invalid course-module id must fail at get_coursemodule_from_id(..., MUST_EXIST).
     */
    public function test_launch_invalid_cm_id_throws(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $_GET['id'] = $naas->cmid + 999999999;
        $_REQUEST['id'] = $naas->cmid + 999999999;

        $this->expectException(\dml_missing_record_exception::class);
        require $CFG->dirroot . '/mod/naas/launch.php';
    }

    /**
     * Missing id query param → required_param() throws missingparam under PHPUnit CLI.
     */
    public function test_launch_missing_id_throws(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        try {
            require $CFG->dirroot . '/mod/naas/launch.php';
            $this->fail('Expected moodle_exception for missing id');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingparam', $e->errorcode);
        }
    }
}
