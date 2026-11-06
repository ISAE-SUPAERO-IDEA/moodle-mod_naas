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
 * Web service mod_naas_check_catalogue.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
namespace mod_naas\external;

/**
 * Landing probe: refresh producer membership, facet counts and the catalogue stamp without fetching cards.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class check_catalogue extends naas_external_base {
    /**
     * Check catalogue parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'courseId' => new \core_external\external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Check catalogue returns description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'API response');
    }

    /**
     * Landing probe: refresh producers, facet counts, and the catalogue stamp.
     *
     * Aggregations are computed over the whole match set rather than the page,
     * so page_size=1 returns every producer bucket and every count. Sorted by
     * modification_date DESC, items[0] is the newest document in the catalogue.
     * Together those two numbers are a sound change detector: unchanged stamp
     * means every cached page is still valid.
     *
     * @param int $courseid
     * @return string the JSON encoded response.
     */
    public static function execute(int $courseid) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseId' => $courseid]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);
        \core\session\manager::write_close();

        $config = self::naas_config();

        $cached = \mod_naas\catalogue_export::cached_probe($config);
        if ($cached !== null) {
            return json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $naas = static::make_naas_client($config);

        $searchoptions = \mod_naas\catalogue_filters::apply([
            'is_default_version' => true,
            'page_size' => 1,
            'sort_by' => 'modification_date',
            'sort_order' => 'DESC',
        ], $config);

        try {
            $raw = \mod_naas\catalogue_filters::constrain_search_json(
                self::sanitise_json_response($naas->request_raw('GET', self::search_url($searchoptions))),
                $config
            );
        } catch (\moodle_exception $e) {
            // NaaS unreachable: answer with the last known catalogue instead of failing,
            // so the landing keeps working from the cache.
            $snapshot = \mod_naas\catalogue_cache::get();
            if (!$snapshot || ($snapshot['fingerprint'] ?? '') !== \mod_naas\catalogue_cache::fingerprint($config)) {
                throw $e;
            }
            return json_encode(
                \mod_naas\catalogue_export::probe_payload($snapshot, true),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
        \mod_naas\catalogue_cache::remember_aggregations($raw, $config);
        $snapshot = \mod_naas\catalogue_cache::get() ?? [];

        return json_encode(
            \mod_naas\catalogue_export::probe_payload($snapshot, false),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
