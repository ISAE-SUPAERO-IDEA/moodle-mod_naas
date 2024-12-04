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
 * Moodle Nugget Plugin : launch LTI
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once('classes/NaasClient.php');
require_once('classes/NaasMoodle.php');


// Get data from DB.
$id = required_param('id', PARAM_INT); // Course Module ID.
$cm = get_coursemodule_from_id('naas', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$language = optional_param('language', null, PARAM_TEXT); // Multilanguage change.
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Check credentials.
require_login($course, true, $cm);
require_capability('mod/naas:view', $context);

// Launch LTI.
$naasmoodle = new \mod_naas\NaasMoodle();
$naasmoodle->lti_launch($id, $language);
