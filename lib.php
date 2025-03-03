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
 * Moodle Nugget Plugin : Mandatory public API of NaaS module
 *
 * @author Bruno Ilponse
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

/**#@+
 * Option controlling what options are offered on the NaaS settings form.
 */
define('NAAS_MAX_ATTEMPT_OPTION', 10);
define('NAAS_MAX_QPP_OPTION', 50);
define('NAAS_MAX_DECIMAL_OPTION', 5);
define('NAAS_MAX_Q_DECIMAL_OPTION', 7);
/**#@-*/

/**#@+
 * Options determining how the grades from individual attempts are combined to give
 * the overall grade for a user
 */
define('NAAS_GRADEHIGHEST', '1');
define('NAAS_ATTEMPTFIRST', '3');
define('NAAS_ATTEMPTLAST',  '4');
/**#@-*/


/**
 * Return the major moodle version
 *
 * @return array
 */
function get_moodle_major_version() {
    global $CFG;

    include($CFG->dirroot . '/version.php');

    // Extraire la version majeure.
    if (isset($release) && preg_match('/^\d+(\.\d+)?/', $release, $matches)) {
        return (int)$matches[0];
    }
    return null;
}

/**
 * Return the mapping for standard message parameters to JWT claim.
 *
 * @return array
 */
function lti_get_jwt_claim_mapping_test() {
    return [
        'launch_presentation_return_url' => [
            'suffix' => '',
            'group' => 'launch_presentation',
            'claim' => 'return_url',
            'isarray' => false,
        ],
    ];
}

/**
 * List of features supported in NaaS module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function naas_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT; // Defines the background color of icon.
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY:
            return true;
        case FEATURE_USES_QUESTIONS:
            return true;
        case FEATURE_PLAGIARISM:
            return true;

        default:
            return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param array $data the data submitted from the reset course.
 * @return array status array
 */
function naas_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return [];
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function naas_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function naas_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $data the data that came from the form.
 * @return mixed the id of the new instance on success,
 *          false or a string error message on failure.
 */
function naas_add_instance($data) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('naas', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'naas', $data->id, $completiontimeexpected);

    naas_grade_item_update($data);

    return $data->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $data the data that came from the form.
 * @return mixed true on success, false or a string error message on failure.
 */
function naas_update_instance($data) {
    global $DB;
    $data->timemodified = time();

    $data->id = $data->instance;
    $DB->update_record('naas', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'naas', $data->id, $completiontimeexpected);

    naas_grade_item_update($data);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id the id of the naas to delete.
 * @return bool success or failure.
 */
function naas_delete_instance($id) {
    global $DB;

    $nugget = $DB->get_record('naas', ['id' => $id], '*', MUST_EXIST);

    $cm = get_coursemodule_from_instance('naas', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'naas', $id, null);

    /*
    ...
    */

    $events = $DB->get_records('event', ['modulename' => 'naas', 'instance' => $nugget->id]);
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    naas_grade_item_delete($nugget);


    // We must delete the module record after we delete the grade item.
    $DB->delete_records('naas', ['id' => $nugget->id]);

    return true;
}

/**
 * Add a get_coursemodule_info function in case any NaaS type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function naas_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, nugget_id, completionattemptsexhausted, completionpass, completionminattempts';
    if (!$naas = $DB->get_record('naas', $dbparams, $fields)) {
        return null;
    }

    $result = new cached_cm_info();
    $result->name = $naas->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('naas', $naas, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        if ($naas->completionpass) {
            $result->customdata['customcompletionrules']['completionpassorattemptsexhausted'] = [
                'completionpass' => $naas->completionpass,
            ];
        } else {
            $result->customdata['customcompletionrules']['completionpassorattemptsexhausted'] = [];
        }

        $result->customdata['customcompletionrules']['completionminattempts'] = $naas->completionminattempts;
    }

    return $result;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function naas_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = ['mod-url-*' => get_string('page-mod-url-x', 'url')];
    return $modulepagetype;
}

/**
 * Export URL resource contents
 * @param object $cm
 * @return array of file content
 */
function naas_export_contents($cm) {
    global $CFG, $DB;
    require_once("$CFG->dirroot/mod/url/locallib.php");
    $contents = [];
    $context = context_module::instance($cm->id);

    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $urlrecord = $DB->get_record('url', ['id' => $cm->instance], '*', MUST_EXIST);

    $fullurl = str_replace('&amp;', '&', url_get_full_url($urlrecord, $cm, $course));
    $isurl = clean_param($fullurl, PARAM_URL);
    if (empty($isurl)) {
        return null;
    }

    $url = [];
    $url['type'] = 'url';
    $url['filename']     = clean_param(format_string($urlrecord->name), PARAM_FILE);
    $url['filepath']     = null;
    $url['filesize']     = 0;
    $url['fileurl']      = $fullurl;
    $url['timecreated']  = null;
    $url['timemodified'] = $urlrecord->timemodified;
    $url['sortorder']    = null;
    $url['userid']       = null;
    $url['author']       = null;
    $url['license']      = null;
    $contents[] = $url;

    return $contents;
}

/**
 * Register the ability to handle drag and drop file uploads
 * @return array containing details of the files / types the mod can handle
 */
function naas_dndupload_register() {
    return ['types' => [
        ['identifier' => 'url', 'message' => get_string('createurl', 'url')],
    ]];
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function naas_view($course, $cm, $context) {
    // Trigger view event.
    $event = \mod_naas\event\course_module_viewed::create([
        'objectid' => $cm->instance,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    // Trigger activity completion on view.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function naas_check_updates_since(cm_info $cm, $from, $filter = []) {
    $updates = course_check_module_updates_since($cm, $from, ['content'], $filter);
    return $updates;
}

/**
 * Adds link(s) to secondary navigation inside activity
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $naasnode The node to add module settings to
 * @since Moodle 4.0
 */
function naas_extend_settings_navigation(settings_navigation $settings, navigation_node $naasnode) {
    $naasnode->add(get_string('about', 'naas'),
        new moodle_url('#'),
        navigation_node::TYPE_SETTING, null, 'about');
}

/**
 * Update the grades for a Nugget activity.
 * @param $nugget
 * @param $grades
 * @return mixed
 */
function naas_grade_item_update($nugget, $grades = null) {
    global $CFG;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (property_exists($nugget, 'cm_id')) { // It may not be always present.
        $params = array('itemname' => $nugget->name, 'idnumber' => $nugget->cm_id);
    } else {
        $params = array('itemname'=>$nugget->name);
    }

    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademax'] = $nugget->maxgrade;
    $params['grademin'] = 0;

    return grade_update('mod/naas', $nugget->course, 'mod', 'naas', $nugget->id, 0, $grades, $params);
}

/**
 * Delete the grades of a Nugget activity.
 * @param $nugget
 * @return mixed
 */
function naas_grade_item_delete($nugget) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/naas', $nugget->course, 'mod', 'naas', $nugget->id, 0, null, ['deleted' => 1]);
}
