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
     * Parse a replaceResult body and store the grade.
     *
     * @param string $xml IMS LTI outcomes XML
     * @return void
     * @throws moodle_exception
     */
    public static function handle(string $xml): void {
        global $DB;

        [$sourcedid, $score] = self::parse_replace_result($xml);

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
        $existinggradesdata = $existinggrades->items[$itemnumber]->grades;
        $grademax = $existinggrades->items[$itemnumber]->grademax;

        $grade = new stdClass();
        $grade->userid = $userid;
        // Score is normalised between 0 and 1. Round so binary fractions such as 0.85 land on the scale.
        $grade->rawgrade = round((float) $score * (float) $grademax, 5);

        $grademethod = $naasinstance->grade_method;

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
        } else if ($grademethod == NAAS_ATTEMPTLAST) {
            grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade);
        } else {
            throw new moodle_exception(
                'error:unsupported_grade_method',
                'naas',
                '',
                $grademethod
            );
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
     * Read sourcedId and score from a replaceResult document.
     *
     * External entities are not loaded (LIBXML_NONET).
     *
     * @param string $xml
     * @return array{0: string, 1: string} sourcedId and normalised score
     * @throws moodle_exception
     */
    private static function parse_replace_result(string $xml): array {
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
}

// Web entrypoint. Tests include this file and call mod_naas_outcome::handle() directly.
if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
    mod_naas_outcome::handle(file_get_contents('php://input') ?: '');
}
