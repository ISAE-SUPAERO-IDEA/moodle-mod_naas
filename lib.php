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
 * Moodle Nugget Plugin : Mandatory public API of NaaS module
 *
 * @package    mod_naas
 * @copyright  2019 Bruno Ilponse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * List of features supported in NaaS module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function naas_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_MOD_PURPOSE: return MOD_PURPOSE_CONTENT; // Defines the background color of icon
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function naas_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return array();
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
    return array('view', 'view all');
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
    return array('update', 'add');
}

/**
 * Add nugget instance.
 * @param object $data
 * @param object $mform
 * @return int new naas instance id
 */
function naas_add_instance($data, $mform) {
    global $CFG, $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('naas', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'naas', $data->id, $completiontimeexpected);

    return $data->id;
}

/**
 * Update NaaS instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function naas_update_instance($data, $mform) {
    global $CFG, $DB;

    $data->timemodified = time();

    $data->id = $data->instance;
    $DB->update_record('naas', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'naas', $data->id, $completiontimeexpected);

    return true;
}

/**
 * Delete url instance.
 * @param int $id
 * @return bool true
 */
function naas_delete_instance($id) {
    global $DB;

    if (!$naas = $DB->get_record('naas', array('id'=>$id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('naas', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'naas', $id, null);

    // note: all context files are deleted automatically

    $DB->delete_records('naas', array('id'=>$naas->id));

    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param object $coursemodule
 * @return cached_cm_info info
 */
function naas_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;

    if (!$naas = $DB->get_record('naas', array('id'=>$coursemodule->instance),
            'id, name, intro, introformat, nugget_id')) {
        return NULL;
    }

    $info = new cached_cm_info();
    $info->name = $naas->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('naas', $naas, $coursemodule->id, false);
    }

    return $info;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function naas_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-url-*'=>get_string('page-mod-url-x', 'url'));
    return $module_pagetype;
}

/**
 * Export URL resource contents
 *
 * @return array of file content
 */
function naas_export_contents($cm, $baseurl) {
    global $CFG, $DB;
    require_once("$CFG->dirroot/mod/url/locallib.php");
    $contents = array();
    $context = context_module::instance($cm->id);

    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $urlrecord = $DB->get_record('url', array('id'=>$cm->instance), '*', MUST_EXIST);

    $fullurl = str_replace('&amp;', '&', url_get_full_url($urlrecord, $cm, $course));
    $isurl = clean_param($fullurl, PARAM_URL);
    if (empty($isurl)) {
        return null;
    }

    $url = array();
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
    return array('types' => array(
        array('identifier' => 'url', 'message' => get_string('createurl', 'url'))
    ));
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $url        url object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function naas_view($course, $cm, $context) {
    // Trigger view event
    $event = \mod_naas\event\course_module_viewed::create(array(
        'objectid' => $cm->instance,
        'context' => $context,
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    // Trigger activity completion on view
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
function naas_check_updates_since(cm_info $cm, $from, $filter = array()) {
    $updates = course_check_module_updates_since($cm, $from, array('content'), $filter);
    return $updates;
}

/**
 * Adds link(s) to secondary navigation inside activity
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $chatnode The node to add module settings to
 */
function naas_extend_settings_navigation(settings_navigation $settings, navigation_node $naasnode) {
    $naasnode->add(get_string('details_button', 'naas'),
        new moodle_url('#'),
        navigation_node::TYPE_SETTING, null, 'details');
}