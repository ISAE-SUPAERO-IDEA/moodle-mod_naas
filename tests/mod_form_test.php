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
 * Unit tests for {@see mod_naas_mod_form} (add + update flows, grading, validation, completion rules).
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/naas/lib.php');
require_once($CFG->dirroot . '/mod/naas/mod_form.php');

use advanced_testcase;
use core_grades\component_gradeitems;
use mod_naas_mod_form;
use moodle_url;

/**
 * Tests for the activity settings form.
 *
 * @covers \mod_naas_mod_form
 */
final class mod_form_test extends advanced_testcase {
    protected function tearDown(): void {
        mod_naas_mod_form::$phpunitfakemajorversion = false;
        mod_naas_mod_form::$phpunitmajorversionvalue = null;
        parent::tearDown();
    }

    /**
     * MoodleQuickForm instance from a mod form (protected property).
     *
     * @param mod_naas_mod_form $form Form being inspected.
     * @return \MoodleQuickForm
     */
    private function mform(mod_naas_mod_form $form): \MoodleQuickForm {
        $ref = new \ReflectionProperty(\moodleform::class, '_form');
        $ref->setAccessible(true);
        return $ref->getValue($form);
    }

    /**
     * Minimal $PAGE for widget AMD registration during definition().
     *
     * @param \context $context Page context.
     * @param \stdClass $course Course the page belongs to.
     */
    private function init_test_page(\context $context, \stdClass $course): void {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_context($context);
        $PAGE->set_course($course);
        $PAGE->set_url(new moodle_url('/course/modedit.php'));
    }

    /**
     * QuickForm elements from the internal list.
     *
     * @param \MoodleQuickForm $mform Form whose elements are read.
     * @return \HTML_QuickForm_element[]
     */
    private function form_elements(\MoodleQuickForm $mform): array {
        $ref = new \ReflectionProperty(\HTML_QuickForm::class, '_elements');
        $ref->setAccessible(true);
        return $ref->getValue($mform);
    }

    /**
     * Raw html elements concatenated (widget markup, not Moodle fitems).
     *
     * @param \MoodleQuickForm $mform Form whose html elements are concatenated.
     * @return string
     */
    private function html_elements(\MoodleQuickForm $mform): string {
        $chunks = [];
        foreach ($this->form_elements($mform) as $element) {
            if ($element->getType() === 'html') {
                $html = $element->toHtml();
                $this->assertNotSame('</div>', trim($html));
                $chunks[] = $html;
            }
        }
        return implode("\n", $chunks);
    }

    /**
     * definition() on add-activity form wires general fields, grades, and standard elements.
     */
    public function test_definition_add_instance_builds_form(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);

        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);
        $mform = $this->mform($form);

        $this->assertTrue($mform->elementExists('name'));
        $this->assertTrue($mform->elementExists('nugget_id'));
        $this->assertTrue($mform->elementExists('cgu_agreement'));
        $this->assertTrue($mform->elementExists('grade_method'));
        $maxfield = component_gradeitems::get_field_name_for_itemnumber('mod/naas', 0, 'maxgrade');
        $this->assertTrue($mform->elementExists($maxfield));
        $this->assertSame('hidden', $mform->getAttribute('data-naas-details'));
        $this->assertStringNotContainsString('naas-activity-details', $this->html_elements($mform));
    }

    /**
     * definition() does not wrap name/intro/CGU in a split HTML div on update either.
     */
    public function test_definition_update_does_not_split_activity_details_wrapper(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $naas = $generator->create_module('naas', [
            'course' => $course->id,
            'nugget_id' => 'nugget-already-selected',
        ]);
        $cm = \get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);

        [$cm, $context, , $data, $cw] = \get_moduleinfo_data($cm, $course);
        $this->init_test_page($context, $course);

        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);
        $mform = $this->mform($form);

        $this->assertSame('visible', $mform->getAttribute('data-naas-details'));
        $this->assertStringNotContainsString('naas-activity-details', $this->html_elements($mform));
    }

    /**
     * definition() on update loads grademax from the existing gradeitem when present.
     */
    public function test_definition_update_instance_reads_grade_item_grademax(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $naas = $generator->create_module('naas', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $cm = \get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);

        [$cm, $context, , $data, $cw] = \get_moduleinfo_data($cm, $course);
        $this->init_test_page($context, $course);

        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);
        $mform = $this->mform($form);

        $maxfield = component_gradeitems::get_field_name_for_itemnumber('mod/naas', 0, 'maxgrade');
        $this->assertTrue($mform->elementExists($maxfield));
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'naas',
            'iteminstance' => $naas->id,
            'itemnumber' => 0,
            'courseid' => $course->id,
        ]);
        $this->assertNotFalse($item);
        $exported = $mform->exportValues([$maxfield]);
        $this->assertArrayHasKey($maxfield, $exported);
        $this->assertEquals(
            \format_float($item->grademax, 2),
            \format_float((float) $exported[$maxfield], 2)
        );
    }

    public function test_data_preprocessing_sets_default_completion_min_attempts(): void {
        $this->resetAfterTest(true);

        $form = $this->getMockBuilder(mod_naas_mod_form::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['data_preprocessing'])
            ->getMock();

        $data = [];
        $form->data_preprocessing($data);
        $this->assertSame(1, $data['completionminattempts']);

        $data = ['completionminattempts' => 5];
        $form->data_preprocessing($data);
        $this->assertSame(5, $data['completionminattempts']);
    }

    public function test_data_postprocessing_resets_min_attempts_when_locked_or_not_auto(): void {
        $this->resetAfterTest(true);

        $form = $this->getMockBuilder(mod_naas_mod_form::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['data_postprocessing'])
            ->getMock();

        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionminattemptsenabled' => 0,
            'completionminattempts' => 5,
        ];
        $form->data_postprocessing($data);
        $this->assertSame(0, $data->completionminattempts);

        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 5,
        ];
        $form->data_postprocessing($data);
        $this->assertSame(5, $data->completionminattempts);

        $data = (object) [
            'completionunlocked' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionminattemptsenabled' => 0,
            'completionminattempts' => 7,
        ];
        $form->data_postprocessing($data);
        $this->assertSame(7, $data->completionminattempts);

        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 3,
        ];
        $form->data_postprocessing($data);
        $this->assertSame(0, $data->completionminattempts);
    }

    public function test_validation_errors_missing_nugget_and_non_positive_maxgrade(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $base = ['nugget_id' => '', 'maxgrade' => 100, 'completion' => COMPLETION_TRACKING_DISABLED];
        $errors = $form->validation($base, []);
        $this->assertArrayHasKey('name', $errors);

        $base = ['nugget_id' => 'nid-1', 'maxgrade' => 0, 'completion' => COMPLETION_TRACKING_DISABLED];
        $errors = $form->validation($base, []);
        $this->assertArrayHasKey('maxgrade', $errors);
    }

    public function test_validation_requires_cgu_only_when_a_nugget_is_selected(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $errors = $form->validation([
            'nugget_id' => '',
            'maxgrade' => 100,
            'completion' => COMPLETION_TRACKING_DISABLED,
        ], []);
        $this->assertArrayNotHasKey('cgu_agreement', $errors);

        $errors = $form->validation([
            'nugget_id' => 'nid-1',
            'maxgrade' => 100,
            'completion' => COMPLETION_TRACKING_DISABLED,
        ], []);
        $this->assertArrayHasKey('cgu_agreement', $errors);

        $errors = $form->validation([
            'nugget_id' => 'nid-1',
            'cgu_agreement' => 1,
            'maxgrade' => 100,
            'completion' => COMPLETION_TRACKING_DISABLED,
        ], []);
        $this->assertArrayNotHasKey('cgu_agreement', $errors);
    }

    public function test_validation_completion_pass_requires_grade_to_pass_with_completionpass_key(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $payload = [
            'nugget_id' => 'x',
            'maxgrade' => 100,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1,
            'gradepass' => '',
        ];
        $errors = $form->validation($payload, []);
        $this->assertArrayHasKey('completionpassgroup', $errors);
    }

    public function test_validation_completion_pass_uses_current_when_completionpass_absent(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $rp = new \ReflectionProperty(\moodleform_mod::class, 'current');
        $rp->setAccessible(true);
        $current = $rp->getValue($form);
        $current->completionpass = 1;
        $rp->setValue($form, $current);

        $payload = [
            'nugget_id' => 'x',
            'maxgrade' => 100,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'gradepass' => '',
        ];
        $errors = $form->validation($payload, []);
        $this->assertArrayHasKey('gradepass', $errors);
    }

    public function test_add_completion_rules_moodle3_branch_when_major_version_is_three(): void {
        $this->resetAfterTest(true);

        mod_naas_mod_form::$phpunitfakemajorversion = true;
        mod_naas_mod_form::$phpunitmajorversionvalue = 3;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $this->assertTrue($this->mform($form)->elementExists('completionpassgroup'));
    }

    public function test_add_completion_rules_no_items_when_major_version_null(): void {
        $this->resetAfterTest(true);

        mod_naas_mod_form::$phpunitfakemajorversion = true;
        mod_naas_mod_form::$phpunitmajorversionvalue = null;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $this->assertFalse($this->mform($form)->elementExists('completionpassgroup'));
    }

    public function test_add_completion_rules_no_items_when_major_version_not_three(): void {
        $this->resetAfterTest(true);

        mod_naas_mod_form::$phpunitfakemajorversion = true;
        mod_naas_mod_form::$phpunitmajorversionvalue = 4;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $this->assertFalse($this->mform($form)->elementExists('completionpassgroup'));
    }

    public function test_data_postprocessing_unlocked_without_automatic_completion_resets_min_attempts(): void {
        $this->resetAfterTest(true);

        $form = $this->getMockBuilder(mod_naas_mod_form::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['data_postprocessing'])
            ->getMock();

        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_NONE,
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 9,
        ];
        $form->data_postprocessing($data);
        $this->assertSame(0, $data->completionminattempts);
    }

    public function test_validation_negative_gradepass_when_completion_requires_pass(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $payload = [
            'nugget_id' => 'x',
            'maxgrade' => 100,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1,
            'gradepass' => '-0.01',
        ];
        $errors = $form->validation($payload, []);
        $this->assertArrayHasKey('completionpassgroup', $errors);
    }

    public function test_validation_no_completion_pass_errors_when_gradepass_set(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);
        $this->init_test_page($context, $course);
        $form = new mod_naas_mod_form($data, $cw->section, $cm, $course);

        $payload = [
            'nugget_id' => 'x',
            'maxgrade' => 100,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1,
            'gradepass' => '50',
        ];
        $errors = $form->validation($payload, []);
        $this->assertArrayNotHasKey('completionpassgroup', $errors);
        $this->assertArrayNotHasKey('gradepass', $errors);
    }

    public function test_completion_rule_enabled(): void {
        $this->resetAfterTest(true);

        $form = $this->getMockBuilder(mod_naas_mod_form::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['completion_rule_enabled'])
            ->getMock();

        $this->assertTrue($form->completion_rule_enabled(['completionpass' => 1]));
        $this->assertFalse($form->completion_rule_enabled(['completionpass' => 0]));
        $this->assertFalse($form->completion_rule_enabled([]));
    }
}
