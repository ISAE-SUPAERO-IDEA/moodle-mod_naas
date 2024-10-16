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

// Accepts two kinds of headers:
// 1- ?fulltext=...&is_default_version=...&page_size=...
// 2- ?nugget_id=...

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once('classes/NaasClient.php');

$path  = $_GET['path'];

// We allow requests to these specific URIs
$whitelist = [
    '/^\/nuggets\/([\w]+-?)+\/default_version$/',
    '/^\/persons\/[\w]+\/?$/',
    '/^\/vocabularies\/nugget_domains_vocabulary\/[\d]+\/?$/'];

$match = false;
foreach ($whitelist as $pexp) {
    if (preg_match($pexp, $path) == 1 ) {
        $match = true;
        break;
    }
}

if (!$match) {
    if (!is_siteadmin()) {
        // Only managers and teachers can use the proxy
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $ismanager = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);

        if (!$ismanager) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            $isteacher = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);
            if (!$isteacher) {
                die;
            }
        }
    }
}

$config = (object) array_merge((array) get_config('naas'), (array) $CFG);
$naas = new NaasClient($config);

// Add nql filter
$nql = $config->naas_filter;
if ($nql) {
    $nql = urlencode($nql);
    if (strpos($path, "/nuggets/search") === 0) {
        $separator = strpos($path, "?") >= 0 ? "&" : "?";
        $path = "$path$separator"."nql=$nql";
    }
}

$response = $naas->request('GET', $path);
echo json_encode($response);
