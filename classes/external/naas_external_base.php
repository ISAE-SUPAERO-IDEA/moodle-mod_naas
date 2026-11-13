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
 * Base class of the web services that proxy requests from the user agent to the NaaS API.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

// @codeCoverageIgnoreStart
// Runs once when the file is included, outside the class that the PHPUnit covers annotations attribute lines to.
defined('MOODLE_INTERNAL') || die();
// Moodle 4.2+ provides the core_external classes. On Moodle 4.1 they only exist under their
// old global names: load them and give them the new names, so one code base runs on both.
// (Including lib/externallib.php on 4.2+ would force every unit test into a separate process.)
// Every NaaS web service class extends this one, so loading any of them runs this block first.
if (!class_exists('\\core_external\\external_api')) {
    global $CFG;
    require_once($CFG->libdir . '/externallib.php');
    $legacyclasses = [
        'external_api', 'external_description', 'external_value', 'external_single_structure',
        'external_multiple_structure', 'external_function_parameters',
    ];
    foreach ($legacyclasses as $legacyclass) {
        if (!class_exists('\\core_external\\' . $legacyclass, false)) {
            class_alias($legacyclass, 'core_external\\' . $legacyclass);
        }
    }
}
// @codeCoverageIgnoreEnd

/**
 * Helpers shared by the web services that proxy requests to the NaaS API.
 *
 * Each web service is its own subclass with execute_parameters(), execute() and
 * execute_returns(). They get their NaaS client from static::make_naas_client(),
 * which a test subclass may override to answer without any HTTP request.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
abstract class naas_external_base extends \core_external\external_api {
    /** Regex matching RFC 4122 UUIDs and simple alphanumeric slugs (no path separators). */
    protected const UUID_SLUG_PATTERN = '/^[a-zA-Z0-9_\-]{1,128}$/';

    /**
     * Reject a parameter value that does not match the UUID/slug allowlist.
     * @param string $value
     * @param string $paramname used in the exception message
     */
    protected static function validate_id_param(string $value, string $paramname): void {
        if (!preg_match(self::UUID_SLUG_PATTERN, $value)) {
            throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', $paramname));
        }
    }

    /**
     * Re-encode a raw JSON string to strip unexpected fields and control characters.
     * @param string $json
     * @return string
     */
    public static function sanitise_json_response(string $json): string {
        $decoded = json_decode($json);
        if ($decoded === null) {
            return $json;
        }
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Build the NaaS search URL, flattening PHP's indexed array syntax.
     * @param array $searchoptions
     * @return string
     */
    public static function search_url(array $searchoptions): string {
        $url = '/nuggets/search?' . http_build_query($searchoptions, '', '&');
        return preg_replace('/\%5B\d+\%5D/', '', $url);
    }

    /**
     * The plugin settings merged with the site configuration, as the NaaS client expects them.
     *
     * @return object
     */
    protected static function naas_config(): object {
        global $CFG;
        return (object) array_merge((array) get_config('naas'), (array) $CFG);
    }

    /**
     * Factory so tests can stub the NaaS HTTP client.
     * @param object $config
     * @return \mod_naas\naas_client
     */
    protected static function make_naas_client(object $config): \mod_naas\naas_client {
        return \mod_naas\naas_client::create($config);
    }

    /**
     * Reject callers who hold no active enrolment in the course.
     *
     * @param int $courseid Course the activity belongs to.
     * @throws \moodle_exception error:not_enrolled when the current user is not actively enrolled.
     */
    protected static function require_active_course_enrolment(int $courseid): void {
        $coursecontext = \context_course::instance($courseid);
        if (!is_enrolled($coursecontext, null, '', true)) {
            throw new \moodle_exception('error:not_enrolled', 'naas');
        }
    }
}
