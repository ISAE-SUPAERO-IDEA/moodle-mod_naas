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
 * Moodle Nugget Plugin : NaaS configuration form
 *
 * @author Bruno Ilponse
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/naas/lib.php');
use core_grades\component_gradeitems;

/**
 * NaaS configuration form
 */
class mod_naas_mod_form extends moodleform_mod {
    /**
     * Form definition
     * @return void
     */
    public function definition() {
        global $CFG, $DB;
        $mform = $this->_form;

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = \mod_naas\naas_client::create($config);

        // -------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $nuggetid = $mform->getCleanedValue("nugget_id", PARAM_TEXT);
        $mform->addElement(
            'html',
            \mod_naas\naas_widget::naas_widget_html($nuggetid, $this->get_course()->id, null, "NuggetSearchWidget")
        );

        $mform->addElement('text', 'name', get_string('name_display', 'naas'), ['size' => '48']);
        $mform->setType('name', PARAM_TEXT);
        // Empty until the widget writes the chosen Nugget's id.
        $mform->addElement('hidden', 'nugget_id', '');
        $mform->setType('nugget_id', PARAM_TEXT);

        // CGU.
        $mform->addElement('checkbox', 'cgu_agreement', get_string('cgu_agreement', 'naas'));
        $mform->addRule('cgu_agreement', null, 'required');

        // Course description.
        $this->standard_intro_elements();

        // Grade settings.
        $mform->addElement('header', 'gradesettings', get_string('gradenoun'));
        $this->grading_elements();

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Create grading section of a Nugget activity.
     * @return void
     */
    private function grading_elements() {
        global $COURSE;

        $mform =& $this->_form;
        $isupdate = !empty($this->_cm);

        $grademax = 100.0;
        if ($isupdate) {
            $gradeitem = grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => 'naas',
                'iteminstance' => $this->_cm->instance,
                'itemnumber' => 0,
                'courseid' => $COURSE->id,
            ]);

            if ($gradeitem) {
                $grademax = $gradeitem->grademax;
            }
        }

        // Attempts allowed. 0 means unlimited. Only attempts that received an LTI outcome count.
        $attemptoptions = [0 => get_string('unlimited', 'naas')];
        for ($i = 1; $i <= NAAS_MAX_ATTEMPT_OPTION; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'attempts', get_string('attempts', 'naas'), $attemptoptions);
        $mform->addHelpButton('attempts', 'attempts', 'naas');
        $mform->setDefault('attempts', 0);

        // Grading method. With a single attempt every method gives the same grade.
        $mform->addElement(
            'select',
            'grade_method',
            get_string('grade_method', 'naas'),
            \mod_naas\naas_widget::naas_get_grading_options()
        );
        $mform->addHelpButton('grade_method', 'grade_method', 'naas');
        $mform->hideIf('grade_method', 'attempts', 'eq', 1);

        // Maximum Grade.
        $maxgradefieldname = component_gradeitems::get_field_name_for_itemnumber("mod/naas", 0, 'maxgrade');
        $mform->addElement('text', $maxgradefieldname, get_string('maximumgrade'));
        $mform->setType($maxgradefieldname, PARAM_FLOAT);
        $mform->setDefault($maxgradefieldname, format_float($grademax, 2));

        // Grade to pass.
        $gradepassfieldname = component_gradeitems::get_field_name_for_itemnumber("mod/naas", 0, 'gradepass');
        $mform->addElement('text', $gradepassfieldname, get_string($gradepassfieldname, 'grades'));
        $mform->addHelpButton($gradepassfieldname, $gradepassfieldname, 'grades');
        $mform->setDefault($gradepassfieldname, '');
        $mform->setType($gradepassfieldname, PARAM_RAW);
    }

    /**
     * Suffix appended to the completion element names.
     *
     * Moodle 4.3+ reuses this form inside the default / bulk activity completion forms and
     * suffixes every completion element (see moodleform_mod::get_suffix()). Older versions
     * have no suffix.
     *
     * @return string
     */
    private function completion_suffix(): string {
        return method_exists($this, 'get_suffix') ? (string) $this->get_suffix() : '';
    }

    /**
     * Prepare the completion defaults before set_data().
     *
     * Also called by the default / bulk activity completion forms.
     *
     * @param array $defaultvalues passed by reference
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        $suffix = $this->completion_suffix();
        $minattemptsel = 'completionminattempts' . $suffix;

        if (empty($defaultvalues[$minattemptsel])) {
            $defaultvalues[$minattemptsel] = 1;
        } else {
            $defaultvalues['completionminattemptsenabled' . $suffix] = 1;
        }
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (empty($data->completionunlocked)) {
            return;
        }

        $suffix = $this->completion_suffix();
        $completionel = 'completion' . $suffix;
        $autocompletion = !empty($data->{$completionel}) && $data->{$completionel} == COMPLETION_TRACKING_AUTOMATIC;

        // Turn off completion settings if the checkboxes aren't ticked.
        if (empty($data->{'completionminattemptsenabled' . $suffix}) || !$autocompletion) {
            $data->{'completionminattempts' . $suffix} = 0;
        }
        if (!$autocompletion) {
            $data->{'completionpass' . $suffix} = 0;
            $data->{'completionattemptsexhausted' . $suffix} = 0;
        }

        // Unlimited attempts can never be exhausted. The checkbox is disabled (hence not
        // submitted) in that case, so clear the stored flag explicitly.
        if (isset($data->attempts) && (int) $data->attempts === 0) {
            $data->{'completionattemptsexhausted' . $suffix} = 0;
        }
    }

    /**
     * Form validation.
     * @param array $data
     * @param array $files
     * @return mixed
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Forms rendered before 3.0.0 posted the placeholder "nugget_id" when no Nugget was chosen.
        if (empty($data['nugget_id']) || $data['nugget_id'] === 'nugget_id') {
            // The name row stays hidden until a Nugget is chosen: the widget shows this error above itself.
            $errors['name'] = get_string('error:nugget_required', 'naas');
        }

        if ($data['maxgrade'] <= 0) {
            $errors['maxgrade'] = get_string('error:must_be_strictly_positive', 'naas');
        }

        $suffix = $this->completion_suffix();
        $completionel = 'completion' . $suffix;
        if (array_key_exists($completionel, $data) && $data[$completionel] == COMPLETION_TRACKING_AUTOMATIC) {
            $completionpassel = 'completionpass' . $suffix;
            $passgroupel = 'completionpassgroup' . $suffix;

            // Check if completionpass exists in $data, otherwise use $this->current->completionpass.
            $completionpass = isset($data[$completionpassel]) ?
                $data[$completionpassel] :
                (isset($this->current->completionpass) ? $this->current->completionpass : null);

            // Show an error if require passing grade was selected and the grade to pass was set to < 0.
            if ($completionpass && (empty($data['gradepass']) || grade_floatval($data['gradepass']) < 0)) {
                if (isset($data[$completionpassel])) {
                    $errors[$passgroupel] = get_string('gradetopassnotset', 'naas');
                } else {
                    $errors['gradepass'] = get_string('gradetopassmustbeset', 'naas');
                }
            }

            // The attempts select is absent from the default / bulk completion forms.
            $maxattempts = isset($data['attempts']) ? (int) $data['attempts'] : null;

            if (!empty($data['completionminattemptsenabled' . $suffix])) {
                $minattempts = (int) ($data['completionminattempts' . $suffix] ?? 0);
                $minattemptsgroupel = 'completionminattemptsgroup' . $suffix;
                if ($minattempts < 1) {
                    $errors[$minattemptsgroupel] = get_string('completionminattemptsinvalid', 'naas');
                } else if ($maxattempts !== null && $maxattempts > 0 && $minattempts > $maxattempts) {
                    $errors[$minattemptsgroupel] = get_string('completionminattemptserror', 'naas');
                }
            }

            if (!empty($data['completionattemptsexhausted' . $suffix]) && $maxattempts === 0) {
                $errors[$passgroupel] = get_string('completionattemptsexhaustederror', 'naas');
            }
        }

        return $errors;
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->completion_suffix();

        // Minimum number of completed attempts.
        $minattemptsenabledel = 'completionminattemptsenabled' . $suffix;
        $minattemptsel = 'completionminattempts' . $suffix;
        $minattemptsgroupel = 'completionminattemptsgroup' . $suffix;
        $group = [];
        $group[] = $mform->createElement('checkbox', $minattemptsenabledel, '', get_string('completionminattempts', 'naas'));
        $group[] = $mform->createElement('text', $minattemptsel, '', ['size' => 3]);
        $mform->setType($minattemptsel, PARAM_INT);
        $mform->addGroup($group, $minattemptsgroupel, get_string('completionminattemptsgroup', 'naas'), [' '], false);
        $mform->disabledIf($minattemptsel, $minattemptsenabledel, 'notchecked');

        // Passing grade and / or exhausted attempts. Either flag alone enables the rule
        // (see \mod_naas\completion\custom_completion::check_passing_grade_or_all_attempts()).
        $completionpassel = 'completionpass' . $suffix;
        $exhaustedel = 'completionattemptsexhausted' . $suffix;
        $passgroupel = 'completionpassgroup' . $suffix;
        $group = [];
        $group[] = $mform->createElement('advcheckbox', $completionpassel, null, get_string('completionpass', 'naas'));
        $group[] = $mform->createElement(
            'advcheckbox',
            $exhaustedel,
            null,
            get_string('completionattemptsexhausted', 'naas')
        );
        $mform->addGroup($group, $passgroupel, get_string('completionpass', 'naas'), ' &nbsp; ', false);
        $mform->addHelpButton($passgroupel, 'completionpass', 'naas');
        if ($mform->elementExists('attempts')) {
            // Unlimited attempts can never be exhausted.
            $mform->disabledIf($exhaustedel, 'attempts', 'eq', 0);
        }

        return [$minattemptsgroupel, $passgroupel];
    }

    /**
     * Called during validation. Indicates whether a module-specific completion rule is selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->completion_suffix();

        $minattemptsenabled = !empty($data['completionminattemptsenabled' . $suffix])
            && (int) ($data['completionminattempts' . $suffix] ?? 0) > 0;

        return $minattemptsenabled
            || !empty($data['completionpass' . $suffix])
            || !empty($data['completionattemptsexhausted' . $suffix]);
    }
}
