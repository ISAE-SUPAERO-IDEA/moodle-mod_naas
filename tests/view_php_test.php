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
 * Full line coverage for mod/naas/view.php (both instance-resolution branches,
 * optional query params, happy path with/without next activity, and access / existence failures).
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;

/**
 * Tests for the activity view script.
 *
 * @coversNothing
 */
final class view_php_test extends advanced_testcase {
    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        parent::tearDown();
    }

    /**
     * Else branch: resolve by course-module id (u absent / zero).
     */
    public function test_view_resolves_by_course_module_id(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['shortname' => 'VPH1']);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'name'    => 'Nugget A',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $_GET['id'] = $naas->cmid;
        $_REQUEST['id'] = $naas->cmid;

        ob_start();
        require($CFG->dirroot . '/mod/naas/view.php');
        $html = ob_get_clean();

        $this->assertStringContainsString(get_string('back_to_course', 'naas'), $html);
        $this->assertStringContainsString('naas_widget', $html);
        $this->assertStringContainsString('VPH1', $html);
        $this->assertStringContainsString('Nugget A', $html);
    }

    /**
     * If branch: resolve by instance id u= (alternative entry URL).
     */
    public function test_view_resolves_by_instance_id_u(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $_GET['u'] = $naas->id;
        $_REQUEST['u'] = $naas->id;

        ob_start();
        require($CFG->dirroot . '/mod/naas/view.php');
        $html = ob_get_clean();

        $this->assertStringContainsString(get_string('back_to_course', 'naas'), $html);
        $this->assertStringContainsString('naas_widget', $html);
    }

    /**
     * When u is set it wins over id (invalid id would fail in the else branch).
     */
    public function test_view_u_param_takes_precedence_over_id(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $_GET['u'] = $naas->id;
        $_GET['id'] = 999999999;
        $_REQUEST['u'] = $naas->id;
        $_REQUEST['id'] = 999999999;

        ob_start();
        require($CFG->dirroot . '/mod/naas/view.php');
        $html = ob_get_clean();

        $this->assertStringContainsString(get_string('back_to_course', 'naas'), $html);
    }

    /**
     * Optional redirect / forceview query params are read (lines 29–30) even when unused later.
     */
    public function test_view_reads_redirect_and_forceview_optional_params(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $_GET['id'] = $naas->cmid;
        $_GET['redirect'] = '1';
        $_GET['forceview'] = '1';
        $_REQUEST = $_GET;

        ob_start();
        require($CFG->dirroot . '/mod/naas/view.php');
        $html = ob_get_clean();

        $this->assertStringContainsString('naas_widget', $html);
    }

    /**
     * With a following activity in the same section, mod_util::get_next_activity_url() feeds the template next block.
     */
    public function test_view_renders_next_activity_when_present(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'First nugget',
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Next page activity',
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $_GET['id'] = $naas->cmid;
        $_REQUEST['id'] = $naas->cmid;

        ob_start();
        require($CFG->dirroot . '/mod/naas/view.php');
        $html = ob_get_clean();

        $this->assertStringContainsString('next-activity', $html);
        $this->assertStringContainsString('Next page activity', $html);
    }

    /**
     * Non-enrolled user cannot access the activity (require_course_login / capability).
     */
    public function test_view_denies_user_not_in_course(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);

        $_GET['id'] = $naas->cmid;
        $_REQUEST['id'] = $naas->cmid;

        $this->expectException(\require_login_exception::class);
        require($CFG->dirroot . '/mod/naas/view.php');
    }

    /**
     * Invalid course-module id for naas must not load the page.
     */
    public function test_view_invalid_cm_id_throws(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $_GET['id'] = $naas->cmid + 999999;
        $_REQUEST['id'] = $naas->cmid + 999999;

        $this->expectException(\dml_missing_record_exception::class);
        require($CFG->dirroot . '/mod/naas/view.php');
    }

    /**
     * Invalid instance id u= must not load the page.
     */
    public function test_view_invalid_instance_u_throws(): void {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $badid = (int) $DB->get_field_sql("SELECT COALESCE(MAX(id),0)+999999 FROM {naas}");

        $_GET['u'] = $badid;
        $_REQUEST['u'] = $badid;

        $this->expectException(\dml_missing_record_exception::class);
        require($CFG->dirroot . '/mod/naas/view.php');
    }
}
