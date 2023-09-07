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
        $mform->addElement('checkbox', 'ratingtime', get_string('ratingtime', 'forum'));

        // Course description
        $this->standard_intro_elements();
        
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values) { }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
