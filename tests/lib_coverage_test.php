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
 * Tests for the lib.php branches not exercised elsewhere: version parsing, calendar cleanup, grade item options.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/naas/lib.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/gradelib.php');

#[\PHPUnit\Framework\Attributes\CoversFunction('get_moodle_major_version')]
#[\PHPUnit\Framework\Attributes\CoversFunction('naas_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('naas_grade_item_update')]
#[\PHPUnit\Framework\Attributes\CoversFunction('naas_update_grades')]
/**
 * lib.php: Moodle version parsing, instance deletion and grade item updates.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::get_moodle_major_version
 * @covers     ::naas_delete_instance
 * @covers     ::naas_grade_item_update
 * @covers     ::naas_update_grades
 */
final class lib_coverage_test extends \advanced_testcase {
    /**
     * A release string that does not start with a version number gives no major version.
     */
    public function test_get_moodle_major_version_without_numeric_release(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $this->assertIsInt(get_moodle_major_version());

        $dirroot = $CFG->dirroot;
        $CFG->dirroot = $dirroot . '/mod/naas/tests/fixtures/moodle_root_without_release';
        try {
            $major = get_moodle_major_version();
        } finally {
            $CFG->dirroot = $dirroot;
        }

        $this->assertNull($major);
    }

    /**
     * Deleting an instance removes its calendar events, attempts, grade item and record.
     */
    public function test_delete_instance_removes_calendar_events(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        /** @var \mod_naas_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $generator->create_activity_outcome($user->id, $naas->cmid);
        $this->setAdminUser();
        foreach (['open', 'close'] as $eventtype) {
            \calendar_event::create([
                'name' => 'Nugget ' . $eventtype,
                'description' => '',
                'format' => FORMAT_HTML,
                'courseid' => $course->id,
                'groupid' => 0,
                'userid' => 0,
                'modulename' => 'naas',
                'instance' => $naas->id,
                'eventtype' => $eventtype,
                'type' => CALENDAR_EVENT_TYPE_STANDARD,
                'timestart' => time() + DAYSECS,
                'timeduration' => 0,
                'visible' => 1,
            ], false);
        }
        $this->assertSame(2, $DB->count_records('event', ['modulename' => 'naas', 'instance' => $naas->id]));

        $this->assertTrue(naas_delete_instance($naas->id));

        $this->assertSame(0, $DB->count_records('event', ['modulename' => 'naas', 'instance' => $naas->id]));
        $this->assertFalse($DB->record_exists('naas', ['id' => $naas->id]));
        $this->assertSame(0, $DB->count_records('naas_activity_outcome', ['activity_id' => $naas->cmid]));
        $this->assertFalse(\grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'naas',
            'iteminstance' => $naas->id,
            'courseid' => $course->id,
        ]));
    }

    /**
     * The grade item takes the course module id number and the maximum grade, and can be reset.
     */
    public function test_grade_item_update_with_idnumber_maxgrade_and_reset(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'name' => 'Graded']);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $nugget = (object) [
            'id' => $naas->id,
            'course' => $course->id,
            'name' => 'Graded',
            'cm_id' => 'naas-idnumber',
            'maxgrade' => 20,
        ];

        $this->assertSame(GRADE_UPDATE_OK, naas_grade_item_update($nugget, (object) ['userid' => $user->id, 'rawgrade' => 15]));

        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'naas', 'iteminstance' => $naas->id]);
        $this->assertSame('naas-idnumber', $item->idnumber);
        $this->assertEquals(20.0, (float) $item->grademax);
        $this->assertEquals(0.0, (float) $item->grademin);
        $this->assertEquals(15.0, (float) $item->get_grade($user->id, false)->finalgrade);

        $this->assertSame(GRADE_UPDATE_OK, naas_grade_item_update($nugget, 'reset'));
        $this->assertNull($item->get_grade($user->id, false)->finalgrade);
    }

    /**
     * Updating the grades of everyone only refreshes the grade item; of one user, writes an empty grade.
     */
    public function test_update_grades_for_all_users_or_one(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'name' => 'Before']);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $nugget = $DB->get_record('naas', ['id' => $naas->id], '*', MUST_EXIST);
        $nugget->name = 'After';

        naas_update_grades($nugget);
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'naas', 'iteminstance' => $naas->id]);
        $this->assertSame('After', $item->itemname);
        $this->assertFalse($DB->record_exists('grade_grades', ['itemid' => $item->id, 'userid' => $user->id]));

        naas_update_grades($nugget, $user->id);
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertNull($grade->rawgrade);
    }
}
