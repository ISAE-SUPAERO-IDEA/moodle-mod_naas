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
 * NaaS configuration form
 *
 * @package    mod_naas
 * @copyright  2019 Bruno Ilponse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once('classes/NaasClient.php');



class mod_naas_mod_form extends moodleform_mod {
    /*function sort_by_name($a, $b) {
      // Compares two nuggets (sort by name)
      if ($a->name == $b->name) {
          return 0;
      }
      return ($a->name < $b->name) ? -1 : 1;
    }*/

    function definition() {
        global $CFG, $DB;
        $mform = $this->_form;

        $config = get_config('naas');
        $naas = new NaasClient($config);

        $info = $naas->get_api_info();

        if ($info == null) {
            $mform->addElement('html', '<div class="alert alert-danger">Impossible de contacter le serveur NaaS</div>');
        }

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $html = file_get_contents($CFG->wwwroot . '/mod/naas/assets/nuggetSearchWidget.html');
        $labels = json_encode([
            "url_root" => $CFG->wwwroot,
            "search_here" => get_string('nugget_search_here','naas'),
            "search" => get_string('nugget_search','naas'),
            "click_to_modify" => get_string('click_to_modify','naas'),
            "clear_filters" => get_string('clear_filters','naas'),
            "no_nugget" => get_string('no_nugget','naas'),
            "click_to_modify" => get_string('click_to_modify','naas'),
            "metadata" => [
                "learning_outcomes" => get_string('learning_outcomes','naas'),
                "prerequisites" => get_string('prerequisites','naas'),
                "references" => get_string('references','naas'),
                "field_of_study" => get_string('field_of_study','naas'),
                "language" => get_string('language','naas'),
                "duration" => get_string('duration','naas'),
                "level" => get_string('level','naas'),
                "structure_id" => get_string('structure_id','naas'),
                "advanced" => get_string('advanced','naas'),
                "intermediate" => get_string('intermediate','naas'),
                "beginner" => get_string('beginner','naas'),
                "tags" => get_string('tags','naas'),
            ]
        ]);

        $html = str_replace('$$labels$$', $labels, $html); 

        $mform->addElement('html', $html );

        $mform->addElement('text', 'name', get_string('name_display','naas'), array('size'=>'48'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('hidden', 'nugget_id', 'nugget_id', array('size'=>'48'));
        $mform->setType('nugget_id', PARAM_TEXT);


        // Description du cours
        $this->standard_intro_elements();
        
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values) {
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

}


