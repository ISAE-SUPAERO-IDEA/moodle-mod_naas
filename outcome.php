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
 * Entrypoint for receiving grade and completion information from the NaaS plateform.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once(__DIR__ . '/lib.php');

/**
 * Applies an LTI replaceResult payload to a NaaS activity grade and completion state.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class mod_naas_outcome {
    /**
     * Apply a replaceResult body (HTTP entrypoint).
     *
     * OAuth verification is enforced only when {@see self::is_oauth_required()} is true
     * (admin setting naas_require_outcome_oauth). While it is off, a signed post is still
     * checked so that the settings page can tell the admin that NaaS already signs grades
     * (config naas_outcome_signed_seen); the grade is stored whatever the result.
     *
     * @param string $xml Raw POX body
     * @param string|null $authorizationheader Authorization header value
     * @param string|null $requesturl Absolute URL of this request (OAuth base string)
     * @param stdClass|null $credentials Optional key/secret (tests); otherwise loaded from NaaS
     * @return void
     * @throws moodle_exception
     */
    public static function process(
        string $xml,
        ?string $authorizationheader = null,
        ?string $requesturl = null,
        ?stdClass $credentials = null
    ): void {
        if (self::is_oauth_required()) {
            self::authenticate($xml, $authorizationheader, $requesturl, $credentials);
        } else {
            self::record_signed_outcome($xml, $authorizationheader, $requesturl, $credentials);
        }
        self::handle($xml);
    }

    /**
     * While OAuth is optional, remember when a correctly signed outcome was last received.
     *
     * Never throws: an unsigned or badly signed post is still accepted in this mode.
     *
     * @param string $xml
     * @param string|null $authorizationheader
     * @param string|null $requesturl
     * @param stdClass|null $credentials
     * @return void
     */
    private static function record_signed_outcome(
        string $xml,
        ?string $authorizationheader,
        ?string $requesturl,
        ?stdClass $credentials
    ): void {
        if ($authorizationheader === null) {
            $authorizationheader = self::authorization_header_from_request();
        }
        if (stripos($authorizationheader, 'OAuth ') === false) {
            return;
        }
        try {
            self::authenticate($xml, $authorizationheader, $requesturl, $credentials);
            set_config('naas_outcome_signed_seen', time(), 'naas');
        } catch (\Throwable $e) {
            return;
        }
    }

    /**
     * Whether admin requires OAuth body-hash on grade passback.
     *
     * Default is off so the plugin can ship before NaaS signs LisOutcome posts.
     *
     * @return bool
     */
    public static function is_oauth_required(): bool {
        return !empty(get_config('naas', 'naas_require_outcome_oauth'));
    }

    /**
     * Verify the OAuth body-hash signature of a replaceResult post.
     *
     * @param string $xml
     * @param string|null $authorizationheader
     * @param string|null $requesturl
     * @param stdClass|null $credentials Object with key and secret; null resolves via NaaS
     * @return void
     * @throws moodle_exception
     */
    public static function authenticate(
        string $xml,
        ?string $authorizationheader = null,
        ?string $requesturl = null,
        ?stdClass $credentials = null
    ): void {
        global $CFG;

        if ($authorizationheader === null) {
            $authorizationheader = self::authorization_header_from_request();
        }
        if ($requesturl === null) {
            $requesturl = $CFG->wwwroot . '/mod/naas/outcome.php';
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requesturl .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        if ($credentials === null) {
            [$sourcedid] = self::parse_replace_result($xml);
            $credentials = self::resolve_lti_credentials($sourcedid);
        }

        if (empty($credentials->key) || empty($credentials->secret)) {
            throw new moodle_exception('error:oauth_invalid', 'naas');
        }

        \mod_naas\lti_outcome_oauth::verify(
            (string) $credentials->key,
            (string) $credentials->secret,
            $xml,
            $authorizationheader,
            $requesturl
        );
    }

    /**
     * Parse a replaceResult body and store the grade.
     *
     * Callers that receive the body over HTTP must run {@see self::authenticate()} first
     * (or use {@see self::process()}). Unit tests may call handle() directly with fixtures.
     *
     * @param string $xml IMS LTI outcomes XML
     * @return void
     * @throws moodle_exception
     */
    public static function handle(string $xml): void {
        global $DB;

        [$sourcedid, $score] = self::parse_replace_result($xml);

        $scorevalue = (float) $score;
        if ($scorevalue < 0.0 || $scorevalue > 1.0) {
            throw new moodle_exception('error:invalid_score', 'naas');
        }

        $records = $DB->get_records('naas_activity_outcome', ['sourced_id' => $sourcedid]);
        if (!$records) {
            throw new moodle_exception('error:unknown_sourced_id', 'naas');
        }

        $userid = null;
        $activityid = null;
        foreach ($records as $record) {
            $userid = $record->user_id;
            $activityid = $record->activity_id;
        }

        $cm = get_coursemodule_from_id('naas', $activityid, 0, false, MUST_EXIST);
        $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        $itemnumber = 0;

        $existinggrades = grade_get_grades($course->id, 'mod', 'naas', $cm->instance, $userid);
        if (empty($existinggrades->items[$itemnumber])) {
            throw new \coding_exception('Missing grade item for NaaS outcome');
        }
        $existinggradesdata = $existinggrades->items[$itemnumber]->grades;
        $grademax = $existinggrades->items[$itemnumber]->grademax;

        $grade = new stdClass();
        $grade->userid = $userid;
        // Score is normalised between 0 and 1. Round so binary fractions such as 0.85 land on the scale.
        $grade->rawgrade = round($scorevalue * (float) $grademax, 5);

        $grademethod = $naasinstance->grade_method;
        if (!in_array((string) $grademethod, [NAAS_GRADEHIGHEST, NAAS_ATTEMPTFIRST, NAAS_ATTEMPTLAST], true)) {
            throw new moodle_exception(
                'error:unsupported_grade_method',
                'naas',
                '',
                $grademethod
            );
        }

        // The outcome closes the attempt. Record it before the grade so that any completion
        // recalculation triggered by the grade change already counts this attempt.
        \mod_naas\attempts::mark_completed($sourcedid, $scorevalue);

        if ($grademethod == NAAS_GRADEHIGHEST) {
            $currenthighestgrade = -1;

            foreach ($existinggradesdata as $data) {
                if ($data->grade > $currenthighestgrade) {
                    $currenthighestgrade = $data->grade;
                }
            }

            if ($grade->rawgrade > $currenthighestgrade) {
                grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade);
            }
        } else if ($grademethod == NAAS_ATTEMPTFIRST) {
            if (self::should_apply_attempt_first_grade(array_values($existinggradesdata))) {
                grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade);
            }
        } else {
            // NAAS_ATTEMPTLAST.
            grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade);
        }

        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            throw new moodle_exception('completionnotenabled', 'completion');
        }

        $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
    }

    /**
     * ATTEMPTFIRST applies only when a first grade slot exists and is still empty.
     *
     * @param array $grades Grade objects in display order, each with a grade property
     * @return bool
     */
    public static function should_apply_attempt_first_grade(array $grades): bool {
        return count($grades) && array_values($grades)[0]->grade === null;
    }

    /**
     * Load the LTI key/secret for the nugget that owns this sourcedId.
     *
     * @param string $sourcedid
     * @return stdClass {key, secret}
     * @throws moodle_exception
     */
    public static function resolve_lti_credentials(string $sourcedid): stdClass {
        global $DB, $CFG;

        $record = $DB->get_record('naas_activity_outcome', ['sourced_id' => $sourcedid], '*', IGNORE_MISSING);
        if (!$record) {
            throw new moodle_exception('error:unknown_sourced_id', 'naas');
        }

        $cm = get_coursemodule_from_id('naas', $record->activity_id, 0, false, MUST_EXIST);
        $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $client = new \mod_naas\naas_client($config);
        $ltconfig = $client->get_nugget_lti_config($naasinstance->nugget_id);

        if ($ltconfig === null || empty($ltconfig->key) || empty($ltconfig->secret)) {
            throw new moodle_exception('error:oauth_invalid', 'naas');
        }

        return (object) [
            'key' => (string) $ltconfig->key,
            'secret' => (string) $ltconfig->secret,
        ];
    }

    /**
     * Read sourcedId and score from a replaceResult document.
     *
     * External entities are not loaded (LIBXML_NONET).
     *
     * @param string $xml
     * @return array{0: string, 1: string} sourcedId and normalised score
     * @throws moodle_exception
     */
    public static function parse_replace_result(string $xml): array {
        $previous = libxml_use_internal_errors(true);
        // Drop namespace declarations so default-xmlns LTI documents are readable with SimpleXML.
        // LIBXML_NONET still blocks external entities.
        $document = simplexml_load_string(self::without_namespaces($xml), 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw new moodle_exception('error:malformed_xml', 'naas');
        }

        $result = $document->imsx_POXBody->replaceResultRequest->resultRecord->result ?? null;
        $sourcedguid = $document->imsx_POXBody->replaceResultRequest->resultRecord->sourcedGUID ?? null;

        $score = (isset($result->resultScore->score)) ? trim((string) $result->resultScore->score) : '';
        $sourcedid = (isset($sourcedguid->sourcedId)) ? trim((string) $sourcedguid->sourcedId) : '';

        if ($score === '' || $sourcedid === '') {
            throw new moodle_exception('error:malformed_xml', 'naas');
        }

        return [$sourcedid, $score];
    }

    /**
     * Remove xmlns declarations and prefixes so element names can be read directly.
     *
     * @param string $xml
     * @return string
     */
    private static function without_namespaces(string $xml): string {
        $stripped = preg_replace('/\sxmlns(:[A-Za-z0-9_]+)?="[^"]*"/', '', $xml);
        return preg_replace('/(<\/?)[A-Za-z0-9_.-]+:/', '$1', $stripped ?? $xml);
    }

    /**
     * Read the Authorization header from the current HTTP request.
     *
     * @return string
     */
    private static function authorization_header_from_request(): string {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }
        return '';
    }
}

// Web entrypoint. Tests include this file and call mod_naas_outcome methods directly.
if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
    mod_naas_outcome::process(file_get_contents('php://input') ?: '');
}
