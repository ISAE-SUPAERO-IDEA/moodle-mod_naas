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

require_once($CFG->dirroot.'/course/moodleform_mod.php');
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
        $naas = new \mod_naas\naas_client($config);

        // -------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $nuggetid = $mform->getCleanedValue("nugget_id", PARAM_TEXT);
        $mform->addElement('html',  \mod_naas\naas_widget::naas_widget_html($nuggetid, null, "NuggetSearchWidget"));

        $mform->addElement('text', 'name', get_string('name_display', 'naas'), ['size' => '48']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('hidden', 'nugget_id', 'nugget_id', ['size' => '48']);
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
                'courseid' => $COURSE->id
            ]);

            if($gradeitem) {
                $grademax = $gradeitem->grademax;
            }
        }


        // Grading method.
        $mform->addElement(
            'select',
            'grade_method',
            get_string('grade_method', 'naas'),
            \mod_naas\naas_widget::naas_get_grading_options()
        );
        $mform->addHelpButton('grade_method', 'grade_method', 'naas');

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
     * Initialise the completion min attempts on the fly
     * @param array $data
     * @return void
     */
    public function data_preprocessing(&$data) {
        if (empty($data['completionminattempts'])) {
            $data['completionminattempts'] = 1;
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
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionminattemptsenabled) || !$autocompletion) {
                $data->completionminattempts = 0;
            }
        }
    }

    /**
     * Form validation
     * @param array $data
     * @param array $files
     * @return mixed
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // TODO Ensure a nugget has been selected

        if($data['maxgrade'] <= 0) {
            $errors['maxgrade'] = get_string('error:must_be_strictly_positive', 'naas');
        }

        if (array_key_exists('completion', $data) && $data['completion'] == COMPLETION_TRACKING_AUTOMATIC) {
            // Check if completionpass exists in $data, otherwise use $this->current->completionpass.
            $completionpass = isset($data['completionpass']) ?
                $data['completionpass'] :
                (isset($this->current->completionpass) ? $this->current->completionpass : null);

            // Show an error if require passing grade was selected and the grade to pass was set to < 0.
            if ($completionpass && (empty($data['gradepass']) || grade_floatval($data['gradepass']) < 0)) {
                if (isset($data['completionpass'])) {
                    $errors['completionpassgroup'] = get_string('gradetopassnotset', 'naas');
                } else {
                    $errors['gradepass'] = get_string('gradetopassmustbeset', 'naas');
                }
            }
        }

        return $errors;
    }

    /**
     * Display module-specific activity completion rules.·
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $items = [];

        $moodlemajorversion = get_moodle_major_version();
        if ($moodlemajorversion !== null) {
            if ($moodlemajorversion === 3) {
                $group = [];
                $group[] = $mform->createElement(
                    'advcheckbox',
                    'completionpass',
                    null,
                    get_string('completionpass', 'naas'),
                    ['group' => 'cpass']
                );
                $mform->disabledIf('completionpass', 'completionusegrade', 'notchecked');
                $mform->addGroup($group, 'completionpassgroup', get_string('completionpass', 'naas'), ' &nbsp; ', false);
                $mform->addHelpButton('completionpassgroup', 'completionpass', 'naas');
                $items[] = 'completionpassgroup';
            }
        }

        return $items;
    }

    /**
     * Called during validation. Indicates whether a module-specific completion rule is selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionpass']);
    }
}
