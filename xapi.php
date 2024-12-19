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
 * Moodle Nugget Plugin : send xAPI statement
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

require_once('../../config.php');

$verb = required_param('verb', PARAM_TEXT);
$versionid = required_param('version_id', PARAM_TEXT);
$body = optional_param('body', null, PARAM_TEXT);

// Get data from DB.
$id = required_param('id', PARAM_INT); // Course Module ID.
$cm = get_coursemodule_from_id('naas', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Check credentials.
require_login($course, true, $cm);
require_capability('mod/naas:view', $context);

// Get NaaS Client.
$config = (object) array_merge((array) get_config('naas'), (array) $CFG);
$naas = new \mod_naas\naas_client($config);

// Get user info from Moodle.
$user = new stdClass();
$user->name = $USER->firstname.' '.$USER->lastname;
$user->email = $USER->email;

// Request post data.
$data = new stdClass();
$data->user = $user;
if (!$body) {
    $data->body = new stdClass();
} else {
    $data->body = (array)json_decode($body);
}

$data->resource_link_id = $_SESSION["resource_link_id"];

$response = $naas->post_xapi_statement($verb, $versionid, $data);
