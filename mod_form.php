<?php

use Phpml\Helper\Optimizer\div;

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
    function definition() {
        global $CFG, $DB;
        $mform = $this->_form;

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new NaasClient($config);

        // API info is not ready yet on production servers
        //$info = $naas->get_api_info();
        $info = $naas->get_connected_user();
        if ($info == null) {
            $mform->addElement('html', '<div class="alert alert-danger">Impossible de contacter le serveur NaaS</div>');
        }

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $labels = json_encode([
            "mount_point"=> "#naas_search_widget",
            "proxy_url"=> "$CFG->wwwroot/mod/naas/proxy.php",
            "labels" => [
                "search_here" => get_string('nugget_search_here','naas'),
                "search" => get_string('nugget_search','naas'),
                "click_to_modify" => get_string('click_to_modify','naas'),
                "clear_filters" => get_string('clear_filters','naas'),
                "no_nugget" => get_string('no_nugget','naas'),
                "click_to_modify" => get_string('click_to_modify','naas'),
                "metadata" => [
                    "resume" => get_string('resume','naas'),
                    "in_brief" => get_string('in_brief','naas'),
                    "about_author" => get_string('about_author','naas'),
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
                    "en" => get_string('en','naas'),
                    "english" => get_string('english','naas'),
                    "fr" => get_string('fr','naas'),
                    "french" => get_string('french','naas'),
                ]
            ]
        ]);
        $html = "";
        $html .= "<div id='naas_search_widget'></div>"; 
        $html .= "<script>NAAS=$labels</script>"; 
        // TODO: use $PAGE->require->js
        $search_widget_url = new moodle_url('/mod/naas/assets/vue/search_widget.js');
        $html .= "<script src='$search_widget_url' ></script>";


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


