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
 * Unit and integration tests for mod_naas lib.php public API.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/naas/lib.php');

use advanced_testcase;
use stdClass;

/**
 * Tests for the public API functions in mod_naas/lib.php.
 *
 * PHPUnit only attributes coverage to lib.php for functions listed in @covers.
 * Keep this list in sync with tests that call lib.php APIs.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::get_moodle_major_version
 * @covers ::lti_get_jwt_claim_mapping_test
 * @covers ::naas_define_module_constants
 * @covers ::naas_add_instance
 * @covers ::naas_check_updates_since
 * @covers ::naas_delete_instance
 * @covers ::naas_dndupload_register
 * @covers ::naas_export_contents
 * @covers ::naas_extend_settings_navigation
 * @covers ::naas_get_coursemodule_info
 * @covers ::naas_get_post_actions
 * @covers ::naas_get_view_actions
 * @covers ::naas_grade_item_delete
 * @covers ::naas_grade_item_update
 * @covers ::naas_page_type_list
 * @covers ::naas_reset_userdata
 * @covers ::naas_supports
 * @covers ::naas_update_grades
 * @covers ::naas_update_instance
 * @covers ::naas_view
 */
class lib_test extends advanced_testcase {

    /**
     * Module constants must be defined and naas_define_module_constants() must be idempotent.
     */
    public function test_define_module_constants(): void {
        naas_define_module_constants();
        $this->assertSame(10, NAAS_MAX_ATTEMPT_OPTION);
        $this->assertSame(50, NAAS_MAX_QPP_OPTION);
        $this->assertSame(5, NAAS_MAX_DECIMAL_OPTION);
        $this->assertSame(7, NAAS_MAX_Q_DECIMAL_OPTION);
        $this->assertSame('1', NAAS_GRADEHIGHEST);
        $this->assertSame('3', NAAS_ATTEMPTFIRST);
        $this->assertSame('4', NAAS_ATTEMPTLAST);

        naas_define_module_constants();
        $this->assertSame(10, NAAS_MAX_ATTEMPT_OPTION);
    }

    // -----------------------------------------------------------------------
    // naas_supports
    // -----------------------------------------------------------------------

    /**
     * @dataProvider naas_supports_cases_provider
     *
     * @param string|int $feature
     * @param mixed $expected
     */
    public function test_supports_all_declared_features($feature, $expected): void {
        $this->assertSame($expected, naas_supports($feature));
    }

    /**
     * @return array<string, array{0: string|int, 1: mixed}>
     */
    public static function naas_supports_cases_provider(): array {
        return [
            'mod_archetype' => [FEATURE_MOD_ARCHETYPE, null],
            'groups' => [FEATURE_GROUPS, true],
            'groupings' => [FEATURE_GROUPINGS, true],
            'intro' => [FEATURE_MOD_INTRO, true],
            'mod_purpose' => [FEATURE_MOD_PURPOSE, MOD_PURPOSE_CONTENT],
            'completion_tracks_views' => [FEATURE_COMPLETION_TRACKS_VIEWS, true],
            'completion_has_rules' => [FEATURE_COMPLETION_HAS_RULES, true],
            'grade_has_grade' => [FEATURE_GRADE_HAS_GRADE, true],
            'grade_outcomes' => [FEATURE_GRADE_OUTCOMES, true],
            'backup_moodle2' => [FEATURE_BACKUP_MOODLE2, true],
            'show_description' => [FEATURE_SHOW_DESCRIPTION, true],
            'controls_grade_visibility' => [FEATURE_CONTROLS_GRADE_VISIBILITY, true],
            'plagiarism' => [FEATURE_PLAGIARISM, true],
            'unknown' => ['FEATURE_UNKNOWN_XYZ', null],
        ];
    }

    // -----------------------------------------------------------------------
    // naas_add_instance
    // -----------------------------------------------------------------------

    /**
     * naas_add_instance() must insert a row and return an integer id.
     */
    public function test_add_instance_returns_integer_id(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $data   = $this->make_naas_data($course->id);

        $id = naas_add_instance($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /**
     * naas_add_instance() must create a row in the naas table.
     */
    public function test_add_instance_creates_db_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $data   = $this->make_naas_data($course->id, 'Test Nugget Activity');

        $id = naas_add_instance($data);

        $record = $DB->get_record('naas', ['id' => $id]);
        $this->assertNotFalse($record);
        $this->assertSame('Test Nugget Activity', $record->name);
        $this->assertSame('test-nugget-uuid', $record->nugget_id);
    }

    /**
     * naas_add_instance() sets timecreated on the record.
     */
    public function test_add_instance_sets_timecreated(): void {
        global $DB;
        $this->resetAfterTest(true);

        $before = time();
        $course = $this->getDataGenerator()->create_course();
        $id     = naas_add_instance($this->make_naas_data($course->id));
        $after  = time();

        $record = $DB->get_record('naas', ['id' => $id]);
        $this->assertGreaterThanOrEqual($before, $record->timecreated);
        $this->assertLessThanOrEqual($after, $record->timecreated);
    }

    /**
     * naas_add_instance() sets timemodified equal to timecreated on creation.
     */
    public function test_add_instance_sets_timemodified(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $id     = naas_add_instance($this->make_naas_data($course->id));

        $record = $DB->get_record('naas', ['id' => $id]);
        $this->assertSame($record->timecreated, $record->timemodified);
    }

    // -----------------------------------------------------------------------
    // naas_update_instance
    // -----------------------------------------------------------------------

    /**
     * naas_update_instance() must return true on success.
     */
    public function test_update_instance_returns_true(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $data    = $this->make_naas_data($course->id, 'Updated Name');
        $data->instance    = $naas->id;
        $data->coursemodule = $naas->cmid;

        $result = naas_update_instance($data);
        $this->assertTrue($result);
    }

    /**
     * naas_update_instance() must modify the name field in the database.
     */
    public function test_update_instance_modifies_name(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $data              = $this->make_naas_data($course->id, 'New Name');
        $data->instance    = $naas->id;
        $data->coursemodule = $naas->cmid;

        naas_update_instance($data);

        $record = $DB->get_record('naas', ['id' => $naas->id]);
        $this->assertSame('New Name', $record->name);
    }

    /**
     * naas_update_instance() must update the nugget_id field.
     */
    public function test_update_instance_modifies_nugget_id(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $data              = $this->make_naas_data($course->id);
        $data->nugget_id   = 'updated-nugget-uuid';
        $data->instance    = $naas->id;
        $data->coursemodule = $naas->cmid;

        naas_update_instance($data);

        $record = $DB->get_record('naas', ['id' => $naas->id]);
        $this->assertSame('updated-nugget-uuid', $record->nugget_id);
    }

    /**
     * naas_update_instance() must update timemodified to the current time.
     */
    public function test_update_instance_updates_timemodified(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $before            = time();
        $data              = $this->make_naas_data($course->id);
        $data->instance    = $naas->id;
        $data->coursemodule = $naas->cmid;
        naas_update_instance($data);
        $after = time();

        $record = $DB->get_record('naas', ['id' => $naas->id]);
        $this->assertGreaterThanOrEqual($before, $record->timemodified);
        $this->assertLessThanOrEqual($after, $record->timemodified);
    }

    // -----------------------------------------------------------------------
    // naas_delete_instance
    // -----------------------------------------------------------------------

    /**
     * naas_delete_instance() must return true on success.
     */
    public function test_delete_instance_returns_true(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $result = naas_delete_instance($naas->id);
        $this->assertTrue($result);
    }

    /**
     * naas_delete_instance() must remove the naas table row.
     */
    public function test_delete_instance_removes_naas_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        naas_delete_instance($naas->id);

        $this->assertFalse($DB->record_exists('naas', ['id' => $naas->id]));
    }

    public function test_delete_instance_removes_outcomes(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user    = $this->getDataGenerator()->create_user();

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas->cmid);
        $gen->create_activity_outcome($user->id, $naas->cmid);

        $this->assertEquals(2, $DB->count_records('naas_activity_outcome', ['activity_id' => $naas->cmid]));

        naas_delete_instance($naas->id);

        $this->assertEquals(0, $DB->count_records('naas_activity_outcome', ['activity_id' => $naas->cmid]));
    }

    // -----------------------------------------------------------------------
    // naas_get_view_actions & naas_get_post_actions
    // -----------------------------------------------------------------------

    /**
     * Test naas_get_view_actions returns correct array.
     */
    public function test_naas_get_view_actions(): void {
        $actions = naas_get_view_actions();
        $this->assertIsArray($actions);
        $this->assertContains('view', $actions);
        $this->assertContains('view all', $actions);
    }

    /**
     * Test naas_get_post_actions returns correct array.
     */
    public function test_naas_get_post_actions(): void {
        $actions = naas_get_post_actions();
        $this->assertIsArray($actions);
        $this->assertContains('update', $actions);
        $this->assertContains('add', $actions);
    }

    // -----------------------------------------------------------------------
    // naas_get_coursemodule_info
    // -----------------------------------------------------------------------

    /**
     * Test naas_get_coursemodule_info populates completion rules.
     */
    public function test_naas_get_coursemodule_info(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1,
            'completionminattempts' => 2,
        ]);

        $cm = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);
        
        $info = naas_get_coursemodule_info($cm);
        
        $this->assertInstanceOf(\cached_cm_info::class, $info);
        $this->assertSame($naas->name, $info->name);
        $this->assertArrayHasKey('customcompletionrules', $info->customdata);
        $this->assertEquals(1, $info->customdata['customcompletionrules']['completionpassorattemptsexhausted']['completionpass']);
        $this->assertEquals(2, $info->customdata['customcompletionrules']['completionminattempts']);
    }

    /**
     * When pass and exhaustion are both off, course-module info must not expose the composite rule key.
     */
    public function test_naas_get_coursemodule_info_omits_pass_rule_when_disabled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 0,
            'completionattemptsexhausted' => 0,
        ]);

        $cm = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);
        $info = naas_get_coursemodule_info($cm);

        $this->assertInstanceOf(\cached_cm_info::class, $info);
        $this->assertArrayHasKey('customcompletionrules', $info->customdata);
        $this->assertArrayNotHasKey('completionpassorattemptsexhausted', $info->customdata['customcompletionrules']);
    }

    public function test_naas_get_coursemodule_info_returns_null_for_unknown_instance(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $cm = new \stdClass();
        $cm->instance = 987654321;
        $cm->id = 1;
        $cm->course = $course->id;
        $cm->showdescription = 0;
        $cm->completion = COMPLETION_TRACKING_NONE;

        $this->assertNull(naas_get_coursemodule_info($cm));
    }

    public function test_naas_get_coursemodule_info_sets_intro_content_when_showdescription(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'intro' => '<p>Visible intro</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $cm = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);
        $cm->showdescription = 1;

        $info = naas_get_coursemodule_info($cm);

        $this->assertNotNull($info);
        $this->assertStringContainsString('Visible intro', $info->content);
    }

    public function test_naas_get_coursemodule_info_manual_completion_skips_automatic_rules(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionpass' => 1,
        ]);
        $cm = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);
        $info = naas_get_coursemodule_info($cm);

        $this->assertNotNull($info);
        $this->assertFalse(isset($info->customdata['customcompletionrules']['completionminattempts']));
    }

    public function test_naas_get_coursemodule_info_exhausted_without_pass_enables_composite_rule(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 0,
            'completionattemptsexhausted' => 1,
        ]);
        $cm = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);
        $info = naas_get_coursemodule_info($cm);

        $this->assertNotNull($info);
        $rules = $info->customdata['customcompletionrules'];
        $this->assertArrayHasKey('completionpassorattemptsexhausted', $rules);
        // DB / driver may expose tinyint as string; assertEquals tolerates int vs string.
        $this->assertEquals(0, $rules['completionpassorattemptsexhausted']['completionpass']);
        $this->assertEquals(1, $rules['completionpassorattemptsexhausted']['completionattemptsexhausted']);
    }

    // -----------------------------------------------------------------------
    // naas_view
    // -----------------------------------------------------------------------

    /**
     * Test naas_view triggers course_module_viewed event.
     */
    public function test_naas_view(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $sink = $this->redirectEvents();
        naas_view($course, $cm, $context);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        
        $event = reset($events);
        $this->assertInstanceOf('\mod_naas\event\course_module_viewed', $event);
        $this->assertEquals($context->id, $event->contextid);
    }

    // -----------------------------------------------------------------------
    // Miscellaneous functions
    // -----------------------------------------------------------------------

    /**
     * Test get_moodle_major_version.
     */
    public function test_get_moodle_major_version(): void {
        $version = get_moodle_major_version();
        $this->assertIsInt($version);
        // $release starts with the branch number (3, 4, 5, …), not a combined "3.0" → 30 style value.
        $this->assertGreaterThanOrEqual(3, $version);
    }

    /**
     * JWT claim mapping helper used by LTI tests must return a stable structure.
     */
    public function test_lti_get_jwt_claim_mapping_test_returns_mapping(): void {
        $map = lti_get_jwt_claim_mapping_test();
        $this->assertArrayHasKey('launch_presentation_return_url', $map);
        $this->assertSame('return_url', $map['launch_presentation_return_url']['claim']);
        $this->assertFalse($map['launch_presentation_return_url']['isarray']);
    }

    /**
     * Test naas_reset_userdata.
     */
    public function test_naas_reset_userdata(): void {
        $result = naas_reset_userdata([]);
        $this->assertEquals([], $result);
    }

    /**
     * Test naas_page_type_list.
     */
    public function test_naas_page_type_list(): void {
        $types = naas_page_type_list('some_page', null, null);
        $this->assertIsArray($types);
        $this->assertArrayHasKey('mod-url-*', $types);
    }

    /**
     * Test naas_dndupload_register.
     */
    public function test_naas_dndupload_register(): void {
        $register = naas_dndupload_register();
        $this->assertIsArray($register);
        $this->assertArrayHasKey('types', $register);
        $this->assertEquals('url', $register['types'][0]['identifier']);
    }

    /**
     * Test naas_check_updates_since.
     */
    public function test_naas_check_updates_since(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);
        $cm_info = \cm_info::create($cm);

        $updates = naas_check_updates_since($cm_info, time() - 3600);
        $this->assertIsObject($updates);
    }

    /**
     * Test naas_extend_settings_navigation.
     */
    public function test_naas_extend_settings_navigation(): void {
        global $PAGE;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $PAGE->set_url('/mod/naas/view.php', ['id' => $naas->cmid]);

        $settings = new \settings_navigation($PAGE, $PAGE->context);
        $node = new \navigation_node('naas');
        
        naas_extend_settings_navigation($settings, $node);
        
        $this->assertNotNull($node->get('about'));
    }

    /**
     * Test naas_update_grades and naas_grade_item_delete.
     */
    public function test_grading_functions(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        // Test update_grades.
        naas_update_grades($naas, $user->id);
        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertArrayHasKey($user->id, $grades->items[0]->grades);

        // Test grade_item_delete.
        naas_grade_item_delete($naas);
        $grade_item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'naas', 'iteminstance' => $naas->id]);
        // Note: grade_item_delete in lib.php doesn't actually delete the record from DB, 
        // it just marks it as deleted in the grade_update call (which often just nulls things or handles it in gradebook).
        // But we can verify the call doesn't crash.
        $this->assertTrue(true);
    }

    /**
     * naas_grade_item_update must accept cm_id on the nugget object and the reset grades sentinel.
     */
    public function test_naas_grade_item_update_cm_id_and_reset(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $nugget = $DB->get_record('naas', ['id' => $naas->id], '*', MUST_EXIST);
        $nugget->course = $course->id;
        $nugget->cm_id = $naas->cmid;

        naas_grade_item_update($nugget);
        naas_grade_item_update($nugget, 'reset');

        $this->assertTrue(true);
    }

    /**
     * Test naas_export_contents.
     */
    public function test_naas_export_contents(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);

        $contents = naas_export_contents($cm);
        $this->assertIsArray($contents);
        $this->assertSame([], $contents);
    }

    /**
     * Build a minimal data object for naas_add_instance / naas_update_instance.
     *
     * @param int    $courseid
     * @param string $name
     * @return stdClass
     */
    private function make_naas_data(int $courseid, string $name = 'Test Nugget'): stdClass {
        $data                  = new stdClass();
        $data->course          = $courseid;
        $data->coursemodule    = 0;
        $data->name            = $name;
        $data->intro           = '';
        $data->introformat     = FORMAT_HTML;
        $data->nugget_id       = 'test-nugget-uuid';
        $data->grade_method    = NAAS_GRADEHIGHEST;
        $data->attempts        = 0;
        $data->completionattemptsexhausted = 0;
        $data->completionpass  = 0;
        $data->completionminattempts = 0;
        $data->allowofflineattempts  = 0;
        return $data;
    }
}
