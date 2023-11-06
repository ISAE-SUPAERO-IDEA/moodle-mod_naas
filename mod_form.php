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

class mod_naas_mod_form extends moodleform_mod {

    ///////         TEST

    






    ////////////////////////////////:

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
        // Grade settings.
        // $this->standard_grading_coursemodule_elements();

        /*
        $mform->removeElement('grade');
        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        } else {
            $currentgrade = 5;
        }
        */
        $mform->addElement('header', 'grade', 'Grade');
        

        $mform->addElement('text', 'grade_pass_field', get_string('grade_pass', 'naas'));
        $mform->setType('grade_pass_field', PARAM_INT);


      

        // Grading method.
        $mform->addElement('select', 'grade_method_field', get_string('grade_method', 'naas'), naas_get_grading_options());
        $mform->addHelpButton('grade_method_field', 'grade_method', 'naas');
       

        echo "<br><br><br>";
        echo json_encode($this->current);


        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    function data_preprocessing(&$toform) {

        if (isset($toform['grade_pass_field'])) {
            // Convert to a real number, so we don't get 0.0000.
            $toform['grade_pass_field'] = $toform['grade_pass_field'] + 0;
        }


    }

    function data_postprocessing($data) {

    }



    function validation($data, $files) {
        $errors = parent::validation($data, $files);


        if (array_key_exists('completion', $data) && $data['completion'] == COMPLETION_TRACKING_AUTOMATIC) {
            $completionpass = isset($data['completionpass']) ? $data['completionpass'] : $this->current->completionpass;

            // Show an error if require passing grade was selected and the grade to pass was set to 0.
            if ($completionpass && (empty($data['grade_pass_field']) || grade_floatval($data['grade_pass_field']) == 0)) {
                if (isset($data['completionpass'])) {
                    $errors['completionpassgroup'] = get_string('grade_to_pass_not_set', 'naas');
                } else {
                    $errors['grade_pass_field'] = get_string('grade_to_pass_must_be_set', 'naas');
                }
            }
        }


        return $errors;
    }






    public function add_completion_rules() {
        $mform = $this->_form;
        $items = array();

        $group = array();
        $group[] = $mform->createElement('advcheckbox', 'completionpass', null, get_string('completion_pass', 'naas'),
                array('group' => 'cpass'));
        $mform->disabledIf('completionpass', 'completionusegrade', 'notchecked');
        $group[] = $mform->createElement('advcheckbox', 'completionattemptsexhausted', null,
                get_string('completion_attempts_exhausted', 'naas'),
                array('group' => 'cattempts'));
        $mform->disabledIf('completionattemptsexhausted', 'completionpass', 'notchecked');
        
        $mform->addGroup($group, 'completionpassgroup', get_string('completion_pass', 'naas'), ' &nbsp; ', false);
        $mform->addHelpButton('completionpassgroup', 'completion_pass', 'naas');
        // $items[] = 'completionpassgroup';

        $group = array();
        $group[] = $mform->createElement('checkbox', 'completionminattemptsenabled', '',
            get_string('completion_min_attempts', 'naas'));
        $group[] = $mform->createElement('text', 'completionminattempts', '', array('size' => 3));
        $mform->setType('completionminattempts', PARAM_INT);
        $mform->addGroup($group, 'completionminattemptsgroup', get_string('completion_min_attempts_group', 'naas'), array(' '), false);
        $mform->disabledIf('completionminattempts', 'completionminattemptsenabled', 'notchecked');

        $items[] = 'completionminattemptsgroup';

        return $items;
    }
}
