<?php

#use Phpml\Helper\Optimizer\div;

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
 * Moodle Nugget Plugin : NaaS configuration form
 *
 * @package    mod_naas
 * @copyright  2019 Bruno Ilponse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once('classes/NaasClient.php');
require_once('locallib.php');

require_once('classes/completion/custom_completion.php');

use core_grades\component_gradeitems;


class mod_naas_mod_form extends moodleform_mod {


    function definition() {
        global $CFG, $DB;
        $mform = $this->_form;

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new NaasClient($config);

        $info = $naas->get_api_info();
        if ($info == null) $mform->addElement('html', '<div class="alert alert-danger">'.get_string("naas_unable_connect", "naas").'</div>');

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $nugget_id = $mform->getCleanedValue("nugget_id", PARAM_TEXT);
        $mform->addElement('html',  naas_widget_html($nugget_id, null, "NuggetSearchWidget"));

        $mform->addElement('text', 'name', get_string('name_display','naas'), array('size'=>'48'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('hidden', 'nugget_id', 'nugget_id', array('size'=>'48'));
        $mform->setType('nugget_id', PARAM_TEXT);

        // CGU
        $mform->addElement('checkbox', 'cgu_agreement', get_string('cgu_agreement','naas'));
        $mform->addRule('cgu_agreement', null, 'required');

        // Course description
        $this->standard_intro_elements();

        // -------------------------------------------------------------------------------
        // Grade settings
        $mform->addElement('header', 'grade', 'Grade');

        $this->standard_grading_coursemodule_elements();

        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        } else {
            $currentgrade = 100;
        }
        $mform->addElement('hidden', 'grade', $currentgrade);
        $mform->setType('grade', PARAM_FLOAT);

        // Number of attempts
        $attemptoptions = array('0' => get_string('unlimited'));
        for ($i = 1; $i <= NAAS_MAX_ATTEMPT_OPTION; $i++) {
            $attemptoptions[$i] = $i;
        }
        // $mform->addElement('select', 'attempts', get_string('attempts_allowed', 'naas'),
                // $attemptoptions);

        // Grading method
        $mform->addElement('select', 'grade_method', get_string('grade_method', 'naas'), naas_get_grading_options());
        $mform->addHelpButton('grade_method', 'grade_method', 'naas');
        
        // -------------------------------------------------------------------------------

        /*
        echo "<br><br><br>";
        echo json_encode($this->current);
        */

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    function standard_grading_coursemodule_elements() {
        // $this->_features->rating = false;
        $this->standard_naas_grading_coursemodule_elements();
        // $this->_features->rating = true;
    }

    /**
     * Add grading settings for NaaS.
     */
    public function standard_naas_grading_coursemodule_elements() {
        global $COURSE, $CFG;
        $mform =& $this->_form;
        $isupdate = !empty($this->_cm);
        $gradeoptions = array('isupdate' => $isupdate,
            'currentgrade' => false,
            'hasgrades' => false,
            'canrescale' => $this->_features->canrescale,
            'useratings' => $this->_features->rating);

        $itemnumber = 0;
        $component = "mod_{$this->_modname}";
        $gradefieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'grade');
        $gradecatfieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'gradecat');
        $gradepassfieldname = component_gradeitems::get_field_name_for_itemnumber($component, $itemnumber, 'gradepass');

        if ($this->_features->hasgrades) {

            //if supports grades and grades aren't being handled via ratings
            if ($isupdate) {
                $gradeitem = grade_item::fetch(array('itemtype' => 'mod',
                    'itemmodule' => $this->_cm->modname,
                    'iteminstance' => $this->_cm->instance,
                    'itemnumber' => 0,
                    'courseid' => $COURSE->id));
                if ($gradeitem) {
                    $gradeoptions['currentgrade'] = $gradeitem->grademax;
                    $gradeoptions['currentgradetype'] = $gradeitem->gradetype;
                    $gradeoptions['currentscaleid'] = $gradeitem->scaleid;
                    $gradeoptions['hasgrades'] = $gradeitem->has_grades();
                }
            }

            $mform->addElement('modgrade', 'gradetype', get_string('grade_type', 'naas'), $gradeoptions);
            $mform->addHelpButton('gradetype', 'modgrade', 'grades');
            $mform->setDefault('gradetype', $CFG->gradepointdefault);

            if ($this->_features->advancedgrading
                and !empty($this->current->_advancedgradingdata['methods'])
                and !empty($this->current->_advancedgradingdata['areas'])) {

                if (count($this->current->_advancedgradingdata['areas']) == 1) {
                    // if there is just one gradable area (most cases), display just the selector
                    // without its name to make UI simpler
                    $areadata = reset($this->current->_advancedgradingdata['areas']);
                    $areaname = key($this->current->_advancedgradingdata['areas']);
                    $mform->addElement('select', 'advancedgradingmethod_'.$areaname,
                        get_string('gradingmethod', 'core_grading'), $this->current->_advancedgradingdata['methods']);
                    $mform->addHelpButton('advancedgradingmethod_'.$areaname, 'gradingmethod', 'core_grading');
                    if (!$this->_features->rating) {
                        $mform->hideIf('advancedgradingmethod_'.$areaname, 'grade[modgrade_type]', 'eq', 'none');
                    }

                } else {
                    // the module defines multiple gradable areas, display a selector
                    // for each of them together with a name of the area
                    $areasgroup = array();
                    foreach ($this->current->_advancedgradingdata['areas'] as $areaname => $areadata) {
                        $areasgroup[] = $mform->createElement('select', 'advancedgradingmethod_'.$areaname,
                            $areadata['title'], $this->current->_advancedgradingdata['methods']);
                        $areasgroup[] = $mform->createElement('static', 'advancedgradingareaname_'.$areaname, '', $areadata['title']);
                    }
                    $mform->addGroup($areasgroup, 'advancedgradingmethodsgroup', get_string('gradingmethods', 'core_grading'),
                        array(' ', '<br />'), false);
                }
            }

            if ($this->_features->gradecat) {
                $mform->addElement('select', $gradecatfieldname,
                    get_string('gradecategoryonmodform', 'grades'),
                    grade_get_categories_menu($COURSE->id, $this->_outcomesused));
                $mform->addHelpButton($gradecatfieldname, 'gradecategoryonmodform', 'grades');
                $mform->hideIf($gradecatfieldname, "{$gradefieldname}[modgrade_type]", 'eq', 'none');
            }

            // Grade to pass.
            $mform->addElement('text', $gradepassfieldname, get_string($gradepassfieldname, 'grades'));
            $mform->addHelpButton($gradepassfieldname, $gradepassfieldname, 'grades');
            $mform->setDefault($gradepassfieldname, '');
            $mform->setType($gradepassfieldname, PARAM_RAW);
            $mform->hideIf($gradepassfieldname, "{$gradefieldname}[modgrade_type]", 'eq', 'none');
        }

        // echo "<br><br>";
        // echo print_r($this->_features);
    }



    function data_preprocessing(&$data) {

        if (empty($data['completionminattempts'])) {
            $data['completionminattempts'] = 1;
        } else {
            $data['completionminattempts'] = $data['completionminattempts'] > 0;
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
    function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionminattemptsenabled) || !$autocompletion) {
                $data->completionminattempts = 0;
            }
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        if (array_key_exists('completion', $data) && $data['completion'] == COMPLETION_TRACKING_AUTOMATIC) {
            // Check if completionpass exists in $data, otherwise use $this->current->completionpass
            $completionpass = isset($data['completionpass']) ? $data['completionpass'] : (isset($this->current->completionpass) ? $this->current->completionpass : null);

            // Show an error if require passing grade was selected and the grade to pass was set to < 0.
            if ($completionpass && (empty($data['gradepass']) || grade_floatval($data['gradepass']) < 0)) {
                if (isset($data['completionpass'])) {
                    $errors['completionpassgroup'] = get_string('gradetopassnotset', 'naas');
                } else {
                    $errors['gradepass'] = get_string('gradetopassmustbeset', 'naas');
                }
            }
        }

        /*
        if (!empty($data['completionminattempts'])) {
            if ($data['attempts'] > 0 && $data['completionminattempts'] > $data['attempts']) {
                $errors['completionminattemptsgroup'] = get_string('completionminattemptserror', 'naas');
            }
        }
        */

        return $errors;
    }

    /**
     * Display module-specific activity completion rules.·
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $items = array();

        // Require passing grade
        $group = array();
        $group[] = $mform->createElement('advcheckbox', 'completionpass', null, get_string('completionpass', 'naas'), array('group' => 'cpass'));
        $mform->disabledIf('completionpass', 'completionusegrade', 'notchecked');
        // $group[] = $mform->createElement('advcheckbox', 'completionattemptsexhausted', null, get_string('completionattemptsexhausted', 'naas'), array('group' => 'cattempts'));
        // $mform->disabledIf('completionattemptsexhausted', 'completionpass', 'notchecked');
        $mform->addGroup($group, 'completionpassgroup', get_string('completionpass', 'naas'), ' &nbsp; ', false);
        $mform->addHelpButton('completionpassgroup', 'completionpass', 'naas');
        $items[] = 'completionpassgroup';

        // Require attempts
        /*
        $group = array();
        $group[] = $mform->createElement('checkbox', 'completionminattemptsenabled', '', get_string('completionminattempts', 'naas'));
        $group[] = $mform->createElement('text', 'completionminattempts', '', array('size' => 3));
        $mform->setType('completionminattempts', PARAM_INT);
        $mform->addGroup($group, 'completionminattemptsgroup', get_string('completionminattemptsgroup', 'naas'), array(' '), false);
        $mform->disabledIf('completionminattempts', 'completionminattemptsenabled', 'notchecked');
        $items[] = 'completionminattemptsgroup';
        */

        return $items;
    }

    /**
     * Called during validation. Indicates whether a module-specific completion rule is selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        return  !empty($data['completionpass']);
        /*
        return  !empty($data['completionattemptsexhausted']) ||
                !empty($data['completionpass']) ||
                !empty($data['completionminattemptsenabled']);
                */
    }
}
