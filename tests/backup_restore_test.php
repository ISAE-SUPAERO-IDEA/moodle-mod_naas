<?php
// This file is part of Moodle - http://moodle.org
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
 * Tests for the NaaS backup and restore steps.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');
// The plan builders load every module's backup and restore tasks, ours included.
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_plan_builder.class.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\backup_naas_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_naas_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_naas_activity_task::class)]
/**
 * A restored or duplicated Nugget activity keeps its settings, and its attempts with user data.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_naas_activity_structure_step
 * @covers     \restore_naas_activity_structure_step
 * @covers     \backup_naas_activity_task
 */
final class backup_restore_test extends \advanced_testcase {
    /** Settings that differ from every table default, so a lost field shows. */
    private const SETTINGS = [
        'attempts' => 3,
        'grade_method' => 3,
        'cgu_agreement' => 1,
        'completionattemptsexhausted' => 1,
        'completionpass' => 1,
        'completionminattempts' => 2,
        'allowofflineattempts' => 1,
    ];

    /**
     * Back up a course and restore it into a new one.
     *
     * @param int $courseid
     * @param bool $withusers Include enrolments and user data.
     * @return int The new course id.
     */
    private function backup_and_restore(int $courseid, bool $withusers): int {
        global $CFG, $USER;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($withusers);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $folder = 'naas-restore-' . uniqid();
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $folder);
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored-' . uniqid(), 1);
        $rc = new \restore_controller(
            $folder,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value($withusers);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * The only Nugget instance of a course.
     *
     * @param int $courseid
     * @return \stdClass
     */
    private function only_instance(int $courseid): \stdClass {
        global $DB;
        $records = $DB->get_records('naas', ['course' => $courseid]);
        $this->assertCount(1, $records);
        return reset($records);
    }

    public function test_restore_keeps_every_activity_setting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module(
            'naas',
            ['course' => $course->id, 'nugget_id' => 'nugget-to-restore'] + self::SETTINGS
        );

        $restored = $this->only_instance($this->backup_and_restore($course->id, false));

        $this->assertNotEquals($naas->id, $restored->id);
        $this->assertSame('nugget-to-restore', $restored->nugget_id);
        $this->assertSame($naas->name, $restored->name);
        foreach (self::SETTINGS as $field => $value) {
            $this->assertEquals($value, $restored->$field, "Field $field was not restored");
        }
    }

    public function test_restore_without_user_data_restores_no_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $learner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_naas_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $generator->create_completed_attempt($learner->id, $naas->cmid, 0.8);

        $newcourseid = $this->backup_and_restore($course->id, false);
        $newcmid = get_coursemodule_from_instance('naas', $this->only_instance($newcourseid)->id)->id;

        $this->assertSame(0, $DB->count_records('naas_activity_outcome', ['activity_id' => $newcmid]));
    }

    public function test_restore_with_user_data_restores_attempts_under_new_sourcedids(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $learner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_naas_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $completed = $generator->create_completed_attempt($learner->id, $naas->cmid, 0.8, 1700000000);
        $launched = $generator->create_activity_outcome($learner->id, $naas->cmid);

        $newcourseid = $this->backup_and_restore($course->id, true);
        $newcmid = (int) get_coursemodule_from_instance('naas', $this->only_instance($newcourseid)->id)->id;

        $rows = array_values($DB->get_records('naas_activity_outcome', ['activity_id' => $newcmid], 'id ASC'));
        $this->assertCount(2, $rows);
        $this->assertEquals($learner->id, $rows[0]->user_id);
        $this->assertEquals(0.8, (float) $rows[0]->score);
        $this->assertEquals(1700000000, (int) $rows[0]->timecompleted);
        $this->assertNull($rows[1]->timecompleted);
        $this->assertNull($rows[1]->score);
        foreach ($rows as $row) {
            $this->assertNotSame($completed->sourced_id, $row->sourced_id);
            $this->assertNotSame($launched->sourced_id, $row->sourced_id);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $row->sourced_id);
        }
        // The source activity keeps its own attempts untouched.
        $this->assertSame(2, $DB->count_records('naas_activity_outcome', ['activity_id' => $naas->cmid]));
    }

    public function test_duplicate_keeps_every_activity_setting(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module(
            'naas',
            ['course' => $course->id, 'nugget_id' => 'nugget-to-copy'] + self::SETTINGS
        );

        $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($naas->cmid));
        $copy = $DB->get_record('naas', ['id' => $newcm->instance], '*', MUST_EXIST);

        $this->assertSame('nugget-to-copy', $copy->nugget_id);
        foreach (self::SETTINGS as $field => $value) {
            $this->assertEquals($value, $copy->$field, "Field $field was not duplicated");
        }
    }

    public function test_encode_content_links_encodes_nugget_links_only(): void {
        global $CFG;
        $this->resetAfterTest();

        $content = 'See <a href="' . $CFG->wwwroot . '/mod/naas/view.php?id=42">this Nugget</a>, '
            . '<a href="' . $CFG->wwwroot . '/mod/naas/index.php?id=7">all Nuggets</a> and '
            . '<a href="' . $CFG->wwwroot . '/mod/page/view.php?id=42">a page</a>.';

        $encoded = \backup_naas_activity_task::encode_content_links($content);

        $this->assertStringContainsString('$@NAASVIEWBYID*42@$', $encoded);
        $this->assertStringContainsString('$@NAASINDEX*7@$', $encoded);
        $this->assertStringContainsString($CFG->wwwroot . '/mod/page/view.php?id=42', $encoded);
    }
}
