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

 * Push to naas

 *

 * @package    naas

 * @copyright  2019 onwards ISAE-SUPAERO

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

 */

namespace mod_naas;
#require_once($CFG->dirroot.'/mod/hvp/autoloader.php');

class NaasMoodle  {
    public function __construct() { 
    }

    // Inspired from /mod/hvp/lib.php

    function get_hvp_file_for_course($course_id, $context_id) {
        global $DB;
        $activity = $DB->get_record('hvp', array('course' => $course_id));
        return $this->get_hvp_file_for_course($activity->id, $context_id);
    }

    function get_hvp_file($id, $context_id) {
        global $DB;
        $activity = $DB->get_record('hvp', array('id' => $id));
        $h5pinterface = \mod_hvp\framework::instance('interface');
        $h5pcore = \mod_hvp\framework::instance('core');
        $contentid = $activity->id;
        $content = $h5pinterface->loadContent($contentid);
        $slug = $activity->slug;
        $filename = "{$slug}-{$contentid}.h5p";
        //$filepath = (!$args ? '/' : '/' .implode('/', $args) . '/');
        $filepath = "/";
        $itemid = 0;
        $filearea = "exports";
        $fs = get_file_storage();
        $file = $fs->get_file($context_id, 'mod_hvp', "exports", $itemid, $filepath, $filename);
        if (!$file) {
            return false; // No such file.
        }    
        return $file;
    }
    function get_course_img($context_id) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context_id, 'course', "overviewfiles", 0);
        foreach ($files as $file) {
            if ($file->get_filesize()>0) {
                return $file;
            }

        }
    }

    // Returns the value of the nugget_id field for a given course

    function get_nugget_id_from_course($course_id) {

        $handler = \core_course\customfield\course_handler::create();

        $custom_data = $handler-> export_instance_data_object($course_id);

        $nugget_id = $custom_data->nugget_id;

        return $nugget_id;

    }

    // Returns True if the user is an editing teacher in the course context

    function can_push($userid, $context) {

        $roles = get_user_roles($userid, $context);

        foreach($roles as $role) {

            if ($role->shortname == 'editingteacher') return true;

        }

        return false;

    }

    // Gets the physical path of a moodle file

    function get_stored_file_path($file) {
        $file_handle = $file->get_content_file_handle();
        $meta_data = stream_get_meta_data($file_handle);
        $file_path = $meta_data["uri"];
        return $file_path;
    }

    // Launch LTI content
    function lti_launch($naas_instance_id) {
        global $PAGE;
        global $DB;
        global $CFG;
        $cm = get_coursemodule_from_id('naas', $naas_instance_id, 0, false, MUST_EXIST);
        $naas_instance = $DB->get_record('naas', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

        // Retrieve LTI config from NaaS server
        $config = \get_config('naas');
        $naas = new \NaasClient($config);
        $nugget_config = $naas->get_nugget_lti_config($naas_instance->nugget_id);
        if ($nugget_config==null || array_key_exists("error", $nugget_config)) {
            error_log("Cannot get nugget information from Naas server");
            echo("Cannot get nugget information from NaaS server");
            return;
        }

        // Configure LTI module
        $PAGE->set_course($course);
        $naas_instance->cmid = $cm->id;
        $naas_instance->course = $course->id;
        $naas_instance->toolurl = $nugget_config->url;
        $naas_instance->securetoolurl = $nugget_config->url;
        $naas_instance->password = $nugget_config->secret;
        $naas_instance->resourcekey = $nugget_config->key;
        $naas_instance->debuglaunch = 0;
        $custom = [
            "hostname" => $CFG->wwwroot,
            "css" => $config->naas_css
        ];
        $naas_instance->instructorcustomparameters = "naas=". json_encode($custom);

        # TODO: permettre de configurer ces paramètes de sécurité
        $naas_instance->instructorchoicesendname = 1;
        $naas_instance->instructorchoicesendemailaddr = 1;
        $naas_instance->instructorchoiceacceptgrades = 1;
        $naas_instance->instructorchoiceallowroster = null;

        // Launch the LTI module
        lti_launch_tool($naas_instance);
    }
}
