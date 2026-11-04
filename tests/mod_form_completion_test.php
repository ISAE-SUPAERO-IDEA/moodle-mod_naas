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
 * Unit tests for the attempts and completion rules of {@see mod_naas_mod_form} on Moodle 4.x.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/naas/lib.php');
require_once($CFG->dirroot . '/mod/naas/mod_form.php');

use advanced_testcase;
use mod_naas_mod_form;
use moodle_url;

/**
 * Tests for the attempts select and the custom completion rules of the activity settings form.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas_mod_form
 */
final class mod_form_completion_test extends advanced_testcase {
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
     * Add-activity form on a course with completion enabled, as an editing teacher.
     *
     * @return mod_naas_mod_form
     */
    private function new_form(): mod_naas_mod_form {
        global $PAGE;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        [, $context, $cw, $cm, $data] = \prepare_new_moduleinfo_data($course, 'naas', 0);

        $PAGE = new \moodle_page();
        $PAGE->set_context($context);
        $PAGE->set_course($course);
        $PAGE->set_url(new moodle_url('/course/modedit.php'));

        return new mod_naas_mod_form($data, $cw->section, $cm, $course);
    }

    /**
     * Submitted data that passes every unrelated validation rule.
     *
     * @param array $overrides Values merged over the defaults.
     * @return array
     */
    private function payload(array $overrides = []): array {
        return array_merge([
            'name' => 'Nugget',
            'nugget_id' => 'nid-1',
            'cgu_agreement' => 1,
            'maxgrade' => 100,
            'gradepass' => '',
            'attempts' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ], $overrides);
    }

    /**
     * The form offers the attempts select and both custom completion groups on Moodle 4.x.
     */
    public function test_form_offers_attempts_and_completion_rules(): void {
        $this->resetAfterTest(true);

        $mform = $this->mform($this->new_form());

        $this->assertTrue($mform->elementExists('attempts'));
        $this->assertTrue($mform->elementExists('grade_method'));
        $this->assertTrue($mform->elementExists('completionminattemptsgroup'));
        $this->assertTrue($mform->elementExists('completionpassgroup'));

        // 0 (unlimited) then 1..NAAS_MAX_ATTEMPT_OPTION.
        $attempts = $mform->getElement('attempts');
        $values = array_map(fn($option) => (int) $option['attr']['value'], $attempts->_options);
        $this->assertSame(range(0, NAAS_MAX_ATTEMPT_OPTION), array_values($values));
        $this->assertSame(get_string('unlimited', 'naas'), $attempts->_options[0]['text']);

        // Group members keep their plain names: the module form has no suffix.
        $names = fn(string $group) => array_map(
            fn($element) => $element->getName(),
            $mform->getElement($group)->getElements()
        );
        $this->assertSame(['completionminattemptsenabled', 'completionminattempts'], $names('completionminattemptsgroup'));
        $this->assertSame(['completionpass', 'completionattemptsexhausted'], $names('completionpassgroup'));
    }

    /**
     * completion_rule_enabled() is true for each custom rule and false otherwise.
     */
    public function test_completion_rule_enabled_covers_each_rule(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $this->assertTrue($form->completion_rule_enabled(['completionpass' => 1]));
        $this->assertTrue($form->completion_rule_enabled(['completionattemptsexhausted' => 1]));
        $this->assertTrue($form->completion_rule_enabled([
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 2,
        ]));

        $this->assertFalse($form->completion_rule_enabled([
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 0,
        ]));
        $this->assertFalse($form->completion_rule_enabled(['completionminattempts' => 2]));
        $this->assertFalse($form->completion_rule_enabled(['completionpass' => 0, 'completionattemptsexhausted' => 0]));
        $this->assertFalse($form->completion_rule_enabled([]));
    }

    /**
     * The minimum number of attempts may not exceed the attempts allowed, unless unlimited.
     */
    public function test_validation_min_attempts_must_not_exceed_attempts_allowed(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $errors = $form->validation($this->payload([
            'attempts' => 2,
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 3,
        ]), []);
        $this->assertSame(get_string('completionminattemptserror', 'naas'), $errors['completionminattemptsgroup']);

        $errors = $form->validation($this->payload([
            'attempts' => 3,
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 3,
        ]), []);
        $this->assertArrayNotHasKey('completionminattemptsgroup', $errors);

        $errors = $form->validation($this->payload([
            'attempts' => 0,
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 50,
        ]), []);
        $this->assertArrayNotHasKey('completionminattemptsgroup', $errors);
    }

    /**
     * An enabled minimum must be a positive whole number.
     */
    public function test_validation_min_attempts_must_be_positive(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        foreach ([0, -1] as $minattempts) {
            $errors = $form->validation($this->payload([
                'attempts' => 3,
                'completionminattemptsenabled' => 1,
                'completionminattempts' => $minattempts,
            ]), []);
            $this->assertSame(get_string('completionminattemptsinvalid', 'naas'), $errors['completionminattemptsgroup']);
        }

        // Disabled rule: the stored text value is ignored.
        $errors = $form->validation($this->payload(['attempts' => 3, 'completionminattempts' => 0]), []);
        $this->assertArrayNotHasKey('completionminattemptsgroup', $errors);
    }

    /**
     * Completion on exhausted attempts needs a limited number of attempts allowed.
     */
    public function test_validation_exhausted_attempts_requires_limited_attempts(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $errors = $form->validation($this->payload(['attempts' => 0, 'completionattemptsexhausted' => 1]), []);
        $this->assertSame(get_string('completionattemptsexhaustederror', 'naas'), $errors['completionpassgroup']);

        $errors = $form->validation($this->payload(['attempts' => 2, 'completionattemptsexhausted' => 1]), []);
        $this->assertArrayNotHasKey('completionpassgroup', $errors);

        // Not automatic completion: no completion rule is validated.
        $errors = $form->validation($this->payload([
            'attempts' => 0,
            'completionattemptsexhausted' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]), []);
        $this->assertArrayNotHasKey('completionpassgroup', $errors);
    }

    /**
     * Requiring a passing grade needs a grade to pass.
     */
    public function test_validation_completion_pass_requires_grade_to_pass(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $errors = $form->validation($this->payload(['completionpass' => 1, 'gradepass' => '']), []);
        $this->assertSame(get_string('gradetopassnotset', 'naas'), $errors['completionpassgroup']);

        $errors = $form->validation($this->payload(['completionpass' => 1, 'gradepass' => '50']), []);
        $this->assertArrayNotHasKey('completionpassgroup', $errors);
        $this->assertArrayNotHasKey('gradepass', $errors);
    }

    /**
     * Leaving automatic completion clears every custom rule.
     */
    public function test_data_postprocessing_clears_rules_when_completion_not_automatic(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'attempts' => 3,
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 3,
            'completionpass' => 1,
            'completionattemptsexhausted' => 1,
        ];
        $form->data_postprocessing($data);

        $this->assertSame(0, $data->completionminattempts);
        $this->assertSame(0, $data->completionpass);
        $this->assertSame(0, $data->completionattemptsexhausted);
    }

    /**
     * Automatic completion keeps the enabled rules; an unticked minimum is stored as 0.
     */
    public function test_data_postprocessing_keeps_enabled_rules_and_clears_unticked_minimum(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'attempts' => 3,
            'completionminattemptsenabled' => 1,
            'completionminattempts' => 2,
            'completionpass' => 1,
            'completionattemptsexhausted' => 1,
        ];
        $form->data_postprocessing($data);

        $this->assertSame(2, $data->completionminattempts);
        $this->assertSame(1, $data->completionpass);
        $this->assertSame(1, $data->completionattemptsexhausted);

        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'attempts' => 3,
            'completionminattempts' => 2,
            'completionpass' => 1,
        ];
        $form->data_postprocessing($data);

        $this->assertSame(0, $data->completionminattempts);
        $this->assertSame(1, $data->completionpass);
    }

    /**
     * Unlimited attempts clear the exhaustion flag (its checkbox is disabled, hence not submitted).
     */
    public function test_data_postprocessing_clears_exhausted_when_attempts_unlimited(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'attempts' => 0,
            'completionpass' => 1,
        ];
        $form->data_postprocessing($data);

        $this->assertSame(0, $data->completionattemptsexhausted);
        $this->assertSame(1, $data->completionpass);
    }

    /**
     * Locked completion settings are left untouched.
     */
    public function test_data_postprocessing_leaves_locked_completion_untouched(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $data = (object) [
            'completionunlocked' => 0,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'attempts' => 0,
            'completionminattempts' => 7,
            'completionpass' => 1,
            'completionattemptsexhausted' => 1,
        ];
        $form->data_postprocessing($data);

        $this->assertSame(7, $data->completionminattempts);
        $this->assertSame(1, $data->completionpass);
        $this->assertSame(1, $data->completionattemptsexhausted);
    }

    /**
     * data_preprocessing() defaults the minimum to 1 and ticks the checkbox when a minimum is stored.
     */
    public function test_data_preprocessing_enables_min_attempts_when_stored(): void {
        $this->resetAfterTest(true);

        $form = $this->new_form();

        $data = ['completionminattempts' => 0];
        $form->data_preprocessing($data);
        $this->assertSame(1, $data['completionminattempts']);
        $this->assertArrayNotHasKey('completionminattemptsenabled', $data);

        $data = ['completionminattempts' => 4];
        $form->data_preprocessing($data);
        $this->assertSame(4, $data['completionminattempts']);
        $this->assertSame(1, $data['completionminattemptsenabled']);
    }
}
