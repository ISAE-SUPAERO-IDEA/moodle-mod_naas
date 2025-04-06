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
 * Accepts two kinds of headers:
 *  - ?fulltext=...&is_default_version=...&page_size=...
 *  - ?nugget_id=...
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

require_once('../../config.php');
require_login(null, false);

$path  = required_param('path',  PARAM_RAW);

// We allow requests to these specific URIs.
$allowedlist = [
    '/^\/nuggets\/([\w]+-?)+\/default_version$/',
    '/^\/persons\/[\w]+\/?$/',
    '/^\/vocabularies\/nugget_domains_vocabulary\/[\d]+\/?$/',
    '/^\/nuggets\/search/',
];

$match = false;
foreach ($allowedlist as $pexp) {
    if (preg_match($pexp, $path) == 1 ) {
        $match = true;
        break;
    }
}

if (!$match) {    
    if (!is_siteadmin()) {
        // Only managers and teachers can use the proxy.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $ismanager = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);

        if (!$ismanager) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            $isteacher = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);
            if (!$isteacher) {
                // Return a proper error message
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 403,
                        'message' => 'Access denied',
                    ],
                ]);
                exit;
            }
        }
    }
}

$config = (object) array_merge((array) get_config('naas'), (array) $CFG);
$naas = new \mod_naas\naas_client($config);

// Add nql filter.
$nql = isset($config->naas_filter) ? $config->naas_filter : null;
if ($nql) {
    $nql = urlencode($nql);
    if (strpos($path, "/nuggets/search") === 0) {
        $separator = strpos($path, "?") >= 0 ? "&" : "?";
        $path = "$path$separator"."nql=$nql";
    }
}
$response = $naas->request_raw('GET', $path);
echo $response->build_client_response();
