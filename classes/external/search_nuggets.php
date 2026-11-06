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
 * Web service mod_naas_search_nuggets.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

/**
 * Search the NaaS catalogue, answering from the search cache when it can.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
class search_nuggets extends naas_external_base {
    /**
     * Search nuggets parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters(
            [
                'courseId' => new \core_external\external_value(PARAM_INT, 'Course ID'),
                'searchOptions' => new \core_external\external_single_structure(
                    [
                        'fulltext' => new \core_external\external_value(PARAM_TEXT, 'Full text search', VALUE_OPTIONAL),
                        'page_size' => new \core_external\external_value(PARAM_INT, 'Number of results per page', VALUE_OPTIONAL),
                        'page' => new \core_external\external_value(PARAM_INT, 'Page number', VALUE_OPTIONAL),
                        'related_domains' => new \core_external\external_multiple_structure(
                            new \core_external\external_value(PARAM_TEXT, 'Single domain value'),
                            'Domain filter',
                            VALUE_OPTIONAL
                        ),
                        'structure' => new \core_external\external_value(PARAM_TEXT, 'Structure filter', VALUE_OPTIONAL),
                        'language' => new \core_external\external_multiple_structure(
                            new \core_external\external_value(PARAM_TEXT, 'Single language value'),
                            'Language filter',
                            VALUE_OPTIONAL
                        ),
                        'level' => new \core_external\external_multiple_structure(
                            new \core_external\external_value(PARAM_TEXT, 'Single level value', VALUE_OPTIONAL),
                            'Level filter',
                            VALUE_OPTIONAL
                        ),
                        'tags' => new \core_external\external_multiple_structure(
                            new \core_external\external_value(PARAM_TEXT, 'Single tag value'),
                            'Tags filter',
                            VALUE_OPTIONAL
                        ),
                        'producers' => new \core_external\external_multiple_structure(
                            new \core_external\external_value(PARAM_TEXT, 'Single producer value'),
                            'Producers filter',
                            VALUE_OPTIONAL
                        ),
                        'authors' => new \core_external\external_multiple_structure(
                            new \core_external\external_value(PARAM_TEXT, 'Single author value'),
                            'Authors filter',
                            VALUE_OPTIONAL
                        ),
                        'type' => new \core_external\external_multiple_structure(
                            new \core_external\external_value(PARAM_TEXT, 'Single type value'),
                            'Type filter',
                            VALUE_OPTIONAL
                        ),
                        'license' => new \core_external\external_multiple_structure(
                            new \core_external\external_value(PARAM_TEXT, 'Single licence value'),
                            'Licence filter',
                            VALUE_OPTIONAL
                        ),
                    ],
                    'Search options',
                    VALUE_DEFAULT,
                    []
                ),
                'mode' => new \core_external\external_value(
                    PARAM_ALPHAEXT,
                    'cache_first to serve a cached page when one exists, revalidate to force a live call',
                    VALUE_DEFAULT,
                    'cache_first'
                ),
            ]
        );
    }

    /**
     * Search nuggets returns description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'API response');
    }

    /**
     * Search nuggets method.
     *
     * In cache_first mode a stored page is returned without touching NaaS, and
     * the response is flagged `_cache.hit`. The widget paints that immediately
     * and then calls again in revalidate mode; it only repaints when the
     * freshness digest moved. A cold key falls through to a live call, reports
     * `hit: false`, and the widget skips the revalidation as redundant.
     *
     * @param int $courseid
     * @param array $searchoptions
     * @param string $mode
     * @return string the JSON encoded response.
     */
    public static function execute(int $courseid, array $searchoptions, string $mode = 'cache_first') {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseId' => $courseid, 'searchOptions' => $searchoptions, 'mode' => $mode]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);
        // A catalogue probe may already be calling NaaS. Release the session
        // so this read can return the saved page without waiting for it.
        \core\session\manager::write_close();

        $config = self::naas_config();

        $searchoptionsarray = $params['searchOptions'];

        // Set default search options.
        $searchoptionsarray['is_default_version'] = true;
        if (!isset($searchoptionsarray['page_size'])) {
            $searchoptionsarray['page_size'] = 6;
        }

        $searchoptionsarray = \mod_naas\catalogue_filters::apply($searchoptionsarray, $config);

        $cachekey = \mod_naas\search_cache::canonical_key($searchoptionsarray, $config);
        if ($params['mode'] !== 'revalidate') {
            $cached = \mod_naas\search_cache::get($cachekey, $config);
            if ($cached === null) {
                foreach (\mod_naas\catalogue_producers::producer_search_aliases($searchoptionsarray) as $alias) {
                    $aliaskey = \mod_naas\search_cache::canonical_key($alias, $config);
                    $cached = \mod_naas\search_cache::get($aliaskey, $config);
                    if ($cached !== null) {
                        $cachekey = $aliaskey;
                        break;
                    }
                }
            }
            if ($cached !== null) {
                \mod_naas\search_cache::touch($cachekey);
                return \mod_naas\search_cache::response($cached, true);
            }
        }

        $naas = static::make_naas_client($config);
        $result = \mod_naas\catalogue_filters::constrain_search_json(
            self::sanitise_json_response($naas->request_raw('GET', self::search_url($searchoptionsarray))),
            $config
        );

        if (\mod_naas\catalogue_cache::is_landing_search($searchoptionsarray)) {
            \mod_naas\catalogue_cache::remember_search($result, $config);
        }
        $entry = \mod_naas\search_cache::store($cachekey, $searchoptionsarray, $result, $config);
        if ($entry === null) {
            return $result;
        }
        return \mod_naas\search_cache::response($entry, false);
    }
}
