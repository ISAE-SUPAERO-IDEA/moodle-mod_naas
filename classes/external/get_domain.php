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
 * Web service mod_naas_get_domain.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

/**
 * Get a domain of the NaaS vocabulary, from the Moodle cache or from NaaS.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
class get_domain extends naas_external_base {
    /**
     * Get domain parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters(
            [
                'courseId' => new \core_external\external_value(PARAM_INT, 'Course ID'),
                'domainKey' => new \core_external\external_value(PARAM_TEXT, 'Domain Key'),
            ]
        );
    }

    /**
     * Get domain returns description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get domain method.
     * @param int $courseid
     * @param string $domainkey
     * @return string the JSON encoded response.
     */
    public static function execute(int $courseid, string $domainkey) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseId' => $courseid, 'domainKey' => $domainkey]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);
        // Vocabulary lookups only read caches and NaaS: do not hold the session lock.
        \core\session\manager::write_close();

        self::validate_domain_key($params['domainKey']);

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cachekey = \mod_naas\vocabulary_lookup::cache_key('domain_', $params['domainKey']);
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $naas = static::make_naas_client(self::naas_config());

        $url = "/vocabularies/nugget_domains_vocabulary/{$params['domainKey']}";
        $result = self::sanitise_json_response($naas->request_raw('GET', $url));
        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Domain vocabulary keys may include dots (hierarchical codes).
     *
     * @param string $value
     */
    private static function validate_domain_key(string $value): void {
        if (!preg_match('/^[a-zA-Z0-9_\-.]{1,128}$/', $value)) {
            throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', 'domainKey'));
        }
    }
}
