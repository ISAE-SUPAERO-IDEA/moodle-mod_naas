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
 * Web service mod_naas_get_person.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

/**
 * Get a NaaS person (author) record, resolved by {@see \mod_naas\person_lookup}.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
class get_person extends naas_external_base {
    /**
     * Get person parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters(
            [
                'courseId' => new \core_external\external_value(PARAM_INT, 'Course ID'),
                'personKey' => new \core_external\external_value(PARAM_TEXT, 'Person Key'),
            ]
        );
    }

    /**
     * Get person returns description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get person method.
     * @param int $courseid
     * @param string $personkey
     * @return string the JSON encoded response.
     */
    public static function execute(int $courseid, string $personkey) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseId' => $courseid, 'personKey' => $personkey]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);
        // Vocabulary lookups only read caches and NaaS: do not hold the session lock.
        \core\session\manager::write_close();

        $personkey = \mod_naas\catalogue_cache::normalize_person_key($params['personKey']);
        self::validate_person_key($personkey);

        return \mod_naas\person_lookup::resolve(static::make_naas_client(self::naas_config()), $personkey);
    }

    /**
     * Person keys are encrypted-email hex hashes, emails, or relationship-prefixed ids.
     *
     * @param string $value
     */
    private static function validate_person_key(string $value): void {
        if (preg_match('/^[a-zA-Z0-9_\-]{1,256}$/', $value)) {
            return;
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', 'personKey'));
    }
}
