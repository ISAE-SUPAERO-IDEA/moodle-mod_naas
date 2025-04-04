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
 * Proxy requests from the user agent to the naas-api.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */

require_once('../../config.php');
require_login(null, false);

$action = required_param('action',  PARAM_TEXT);

$config = (object) array_merge((array) get_config('naas'), (array) $CFG);
$naas = new \mod_naas\naas_client($config);

switch ($action) {
    case 'test-config':
        require_capability('mod/naas:admin', context_system::instance());
        $path = '/nuggets/search';
        $url = $path . '?' . http_build_query([
                'is_default_version' => true,
                'page_size' => 2,
            ]);
        break;

    case 'get-nugget':
        $courseid = required_param('courseId',  PARAM_INT);
        require_capability('mod/naas:view', context_course::instance($courseid));
        $nuggetid = required_param('nuggetId', PARAM_TEXT);
        $url = "/nuggets/{$nuggetid}/default_version";
        break;

    case 'get-nugget-preview':
        $courseid = required_param('courseId',  PARAM_INT);
        require_capability('mod/naas:addinstance', context_course::instance($courseid));
        $versionid = required_param('versionId', PARAM_TEXT);
        $url = "/versions/{$versionid}/preview_url";
        break;

    case 'get-domain':
        $courseid = required_param('courseId',  PARAM_INT);
        require_capability('mod/naas:addinstance', context_course::instance($courseid));
        $domainkey = required_param('domainKey',  PARAM_TEXT);
        $url = "/vocabularies/nugget_domains_vocabulary/{$domainkey}";
        break;

    case 'get-structure':
        $courseid = required_param('courseId',  PARAM_INT);
        require_capability('mod/naas:addinstance', context_course::instance($courseid));
        $structurekey = required_param('structureKey',  PARAM_TEXT);
        $url = "/structures/{$structurekey}";
        break;

    case 'get-person':
        $courseid = required_param('courseId',  PARAM_INT);
        require_capability('mod/naas:addinstance', context_course::instance($courseid));
        $personkey = required_param('personKey',  PARAM_TEXT);
        $url = "/persons/{$personkey}";
        break;

    case 'search-nuggets':
        $courseid = required_param('courseId',  PARAM_INT);
        require_capability('mod/naas:addinstance', context_course::instance($courseid));
        $searchoptionsjson = optional_param('searchOptions', '{}', PARAM_TEXT);
        $searchoptions = json_decode($searchoptionsjson, true);

        // Set default search options.
        $searchoptions['is_default_version'] = true;
        if (!isset($searchoptions['page_size'])) {
            $searchoptions['page_size'] = 6;
        }

        $path = '/nuggets/search';

        // Add nql filter.
        $nql = $config->naas_filter;
        if ($nql) {
            $searchoptions['nql'] = urlencode($nql);
        }

        $url = $path . '?' . http_build_query($searchoptions, '', '&');
        $url = preg_replace('/\%5B\d+\%5D/', '', $url);
        break;

    default:
        debugging('Invalid proxy action: '.$action);
        throw new moodle_exception('proxyactionnotfound', 'naas');
}

$response = $naas->request_raw('GET', $url);
echo $response->build_client_response();

