<?php
// This file is part of Moodle - http://moodle.org/
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
 * Tests for {@see mod_naas_outcome} in outcome.php (LTI replaceResult XML handling, grade paths, completion).
 *
 * Direct grade_update() tests mirror the grade_method branches without XML. {@see mod_naas_outcome::handle()}
 * is exercised with realistic payloads. The HTTP-only tail of outcome.php is excluded from coverage
 * (see outcome.php near the file end).
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/naas/lib.php');

use advanced_testcase;
use gradeitem;
use stdClass;

/**
 * Tests for the outcome / grading logic of mod_naas.
 *
 * @coversNothing
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class outcome_test extends advanced_testcase {
    // NAAS_GRADEHIGHEST strategy.

    /**
     * With NAAS_GRADEHIGHEST, a higher score overwrites the existing grade.
     */
    public function test_grade_highest_updates_when_score_is_higher(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_graded_activity(NAAS_GRADEHIGHEST);

        // First grade: 60 out of 100.
        $this->submit_grade($course->id, $naas->id, $user->id, 60);

        // Second grade: 80 → should replace.
        $this->submit_grade($course->id, $naas->id, $user->id, 80);

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(80.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * With NAAS_GRADEHIGHEST, a lower score must NOT replace the stored grade.
     */
    public function test_grade_highest_does_not_downgrade(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_graded_activity(NAAS_GRADEHIGHEST);

        // First grade: 80.
        $this->submit_grade($course->id, $naas->id, $user->id, 80);

        // Apply the NAAS_GRADEHIGHEST guard (mirrors outcome.php logic).
        $existinggrades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $currenthighest = -1;
        foreach ($existinggrades->items[0]->grades as $data) {
            if ($data->grade > $currenthighest) {
                $currenthighest = $data->grade;
            }
        }

        $newrawgrade = 50;
        if ($newrawgrade > $currenthighest) {
            $this->submit_grade($course->id, $naas->id, $user->id, $newrawgrade);
        }

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(80.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * With NAAS_GRADEHIGHEST, a score of 0.0 (min boundary) is stored.
     */
    public function test_grade_highest_score_boundary_at_zero(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_graded_activity(NAAS_GRADEHIGHEST);

        $this->submit_grade($course->id, $naas->id, $user->id, 0);

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(0.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * With NAAS_GRADEHIGHEST, a score of 100 (max boundary) is stored.
     */
    public function test_grade_highest_score_boundary_at_max(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_graded_activity(NAAS_GRADEHIGHEST);

        $this->submit_grade($course->id, $naas->id, $user->id, 100);

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(100.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    // NAAS_ATTEMPTFIRST strategy.

    /**
     * With NAAS_ATTEMPTFIRST, the first grade submitted is stored.
     */
    public function test_attempt_first_stores_first_grade(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_graded_activity(NAAS_ATTEMPTFIRST);

        $this->submit_grade($course->id, $naas->id, $user->id, 70);

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(70.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * With NAAS_ATTEMPTFIRST, subsequent submissions must NOT overwrite the
     * first grade.
     */
    public function test_attempt_first_ignores_subsequent_submissions(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_graded_activity(NAAS_ATTEMPTFIRST);

        // First submission.
        $this->submit_grade($course->id, $naas->id, $user->id, 70);

        // Apply NAAS_ATTEMPTFIRST guard (mirrors outcome.php logic).
        $existinggrades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $firstgrade = array_values($existinggrades->items[0]->grades)[0]->grade ?? null;

        if ($firstgrade === null) {
            // Grade not yet set → allow update.
            $this->submit_grade($course->id, $naas->id, $user->id, 95);
        }
        // Otherwise do not update (grade already set → ATTEMPTFIRST guard blocks).

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(70.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    // NAAS_ATTEMPTLAST strategy.

    /**
     * With NAAS_ATTEMPTLAST, every submission overwrites the previous grade.
     */
    public function test_attempt_last_always_overwrites(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_graded_activity(NAAS_ATTEMPTLAST);

        $this->submit_grade($course->id, $naas->id, $user->id, 40);
        $this->submit_grade($course->id, $naas->id, $user->id, 20);
        $this->submit_grade($course->id, $naas->id, $user->id, 90);

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(90.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    // Grade_update isolation between users.

    /**
     * Grade updates for one user must not affect another user in the same activity.
     */
    public function test_grade_isolated_per_user(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $usera] = $this->setup_graded_activity(NAAS_ATTEMPTLAST);
        $userb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($userb->id, $course->id);

        $this->submit_grade($course->id, $naas->id, $usera->id, 85);
        $this->submit_grade($course->id, $naas->id, $userb->id, 50);

        $gradesa = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $usera->id);
        $gradesb = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $userb->id);

        $this->assertEquals(85.0, (float) $gradesa->items[0]->grades[$usera->id]->grade);
        $this->assertEquals(50.0, (float) $gradesb->items[0]->grades[$userb->id]->grade);
    }

    // XXE / malformed XML.

    /**
     * An external entity in the outcome body must never be expanded.
     *
     * The fixture points the entity at a temporary file with a known content; the
     * parsed sourcedId must not contain it. The same document without the entity
     * reference parses, so a rejection cannot come from a broken fixture.
     */
    public function test_xxe_payload_rejected(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $secretfile = make_request_directory() . '/secret.txt';
        file_put_contents($secretfile, 'naas-xxe-marker');
        $xml = str_replace(
            'file:///etc/passwd',
            'file://' . $secretfile,
            file_get_contents(__DIR__ . '/fixtures/lti_response_xxe.xml')
        );

        [$plainid] = \mod_naas_outcome::parse_replace_result(str_replace('&xxe;', 'plain-id', $xml));
        $this->assertSame('plain-id', $plainid);

        try {
            [$sourcedid] = \mod_naas_outcome::parse_replace_result($xml);
            $this->assertStringNotContainsString('naas-xxe-marker', $sourcedid);
        } catch (\moodle_exception $e) {
            $this->assertSame('error:malformed_xml', $e->errorcode);
        }
    }

    /**
     * Malformed XML must throw an exception rather than silently failing.
     */
    public function test_malformed_xml_throws_exception(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $this->expectException(\moodle_exception::class);
        try {
            \mod_naas_outcome::handle('<invalid><xml>');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:malformed_xml', $e->errorcode);
            throw $e;
        }
    }

    /**
     * Test successful outcome handling.
     */
    public function test_handle_success(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $sourcedid = 'test-session-123';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
  <imsx_POXHeader>
    <imsx_POXResponseHeaderInfo>
      <imsx_version>V1.0</imsx_version>
      <imsx_messageIdentifier>9999</imsx_messageIdentifier>
    </imsx_POXResponseHeaderInfo>
  </imsx_POXHeader>
  <imsx_POXBody>
    <replaceResultRequest>
      <resultRecord>
        <sourcedGUID>
          <sourcedId>{$sourcedid}</sourcedId>
        </sourcedGUID>
        <result>
          <resultScore>
            <score>0.85</score>
          </resultScore>
        </result>
      </resultRecord>
    </replaceResultRequest>
  </imsx_POXBody>
</imsx_POXEnvelopeRequest>
XML;

        \mod_naas_outcome::handle($xml);

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(85.0, (float) $grades->items[0]->grades[$user->id]->grade);

        // Check completion.
        $completion = new \completion_info($course);
        $cm = get_coursemodule_from_id('naas', $naas->cmid);
        $completiondata = $completion->get_data($cm, true, $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completiondata->completionstate);
    }

    /**
     * An unknown sourcedId must throw a moodle_exception rather than causing
     * a PHP notice from a null $records loop.
     */
    public function test_unknown_sourced_id_throws(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest>
  <imsx_POXBody>
    <replaceResultRequest>
      <resultRecord>
        <sourcedGUID>
          <sourcedId>unknown123</sourcedId>
        </sourcedGUID>
        <result>
          <resultScore>
            <score>0.8</score>
          </resultScore>
        </result>
      </resultRecord>
    </replaceResultRequest>
  </imsx_POXBody>
</imsx_POXEnvelopeRequest>
XML;

        $this->expectException(\moodle_exception::class);
        try {
            \mod_naas_outcome::handle($xml);
        } catch (\moodle_exception $e) {
            $this->assertSame('error:unknown_sourced_id', $e->errorcode);
            throw $e;
        }
    }

    /**
     * Missing score element in XML must use the same malformed guard as invalid markup.
     */
    public function test_handle_malformed_xml_missing_score(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest>
  <imsx_POXBody>
    <replaceResultRequest>
      <resultRecord>
        <sourcedGUID>
          <sourcedId>any-id</sourcedId>
        </sourcedGUID>
        <result></result>
      </resultRecord>
    </replaceResultRequest>
  </imsx_POXBody>
</imsx_POXEnvelopeRequest>
XML;

        $this->expectException(\moodle_exception::class);
        try {
            \mod_naas_outcome::handle($xml);
        } catch (\moodle_exception $e) {
            $this->assertSame('error:malformed_xml', $e->errorcode);
            throw $e;
        }
    }

    /**
     * Missing sourcedId must trigger malformed_xml (structure guard).
     */
    public function test_handle_malformed_xml_missing_sourced_id(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest>
  <imsx_POXBody>
    <replaceResultRequest>
      <resultRecord>
        <sourcedGUID/>
        <result>
          <resultScore>
            <score>0.5</score>
          </resultScore>
        </result>
      </resultRecord>
    </replaceResultRequest>
  </imsx_POXBody>
</imsx_POXEnvelopeRequest>
XML;

        $this->expectException(\moodle_exception::class);
        try {
            \mod_naas_outcome::handle($xml);
        } catch (\moodle_exception $e) {
            $this->assertSame('error:malformed_xml', $e->errorcode);
            throw $e;
        }
    }

    /**
     * NAAS_GRADEHIGHEST via handle: first LTI score applies when no prior grade exists.
     */
    public function test_handle_grade_highest_first_score_applies(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_GRADEHIGHEST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $sourcedid = 'lti-gh-first';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.42'));

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(42.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * NAAS_GRADEHIGHEST via handle: lower LTI score must not downgrade an existing higher grade.
     */
    public function test_handle_grade_highest_does_not_downgrade_via_handle(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_GRADEHIGHEST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->submit_grade($course->id, $naas->id, $user->id, 88.0);

        $sourcedid = 'lti-gh-high';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.10'));

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(88.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * ATTEMPTFIRST grade gate: first slot exists with null final grade.
     */
    public function test_should_apply_attempt_first_grade_when_first_is_null(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $grades = [(object)['grade' => null]];
        $this->assertTrue(\mod_naas_outcome::should_apply_attempt_first_grade($grades));
    }

    /**
     * ATTEMPTFIRST grade gate: first slot already has a numeric grade.
     */
    public function test_should_apply_attempt_first_grade_false_when_first_set(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $grades = [(object)['grade' => 50.0]];
        $this->assertFalse(\mod_naas_outcome::should_apply_attempt_first_grade($grades));
    }

    /**
     * ATTEMPTFIRST grade gate: empty grade list.
     */
    public function test_should_apply_attempt_first_grade_false_when_empty(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $this->assertFalse(\mod_naas_outcome::should_apply_attempt_first_grade([]));
    }

    /**
     * NAAS_ATTEMPTFIRST via handle: first LTI score applies when no prior grade exists.
     */
    public function test_handle_attempt_first_first_score_applies(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTFIRST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $sourcedid = 'lti-af-handle';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.73'));

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(73.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * Unknown grade_method in DB must throw unsupported_grade_method.
     */
    public function test_handle_unsupported_grade_method_throws(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $DB->set_field('naas', 'grade_method', 99, ['id' => $naas->id]);

        $sourcedid = 'lti-bad-method';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        $this->expectException(\moodle_exception::class);
        try {
            \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.5'));
        } catch (\moodle_exception $e) {
            $this->assertSame('error:unsupported_grade_method', $e->errorcode);
            throw $e;
        }
    }

    /**
     * Completion disabled on the course must surface completionnotenabled from handle().
     */
    public function test_handle_throws_when_course_completion_disabled(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $sourcedid = 'lti-no-completion';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        $this->expectException(\moodle_exception::class);
        try {
            \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.5'));
        } catch (\moodle_exception $e) {
            $this->assertSame('completionnotenabled', $e->errorcode);
            throw $e;
        }
    }

    /**
     * Two outcome rows with the same sourced_id exercise the foreach in handle().
     */
    public function test_handle_with_duplicate_outcome_rows_same_sourced_id(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $sourcedid = 'lti-dup';
        $now = time();
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => $now,
        ]);
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => $now + 1,
        ]);

        \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.33'));

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(33.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    // Attempt closure: the outcome stores score and timecompleted on the launch row.

    /**
     * handle() closes the attempt: score and timecompleted are stored on the outcome row.
     * A later outcome for the same session refreshes the score but keeps the completion time.
     */
    public function test_handle_marks_attempt_completed_with_score(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $sourcedid = 'lti-close-attempt';
        $gen->create_activity_outcome($user->id, $naas->cmid, $sourcedid);

        $before = $DB->get_record('naas_activity_outcome', ['sourced_id' => $sourcedid], '*', MUST_EXIST);
        $this->assertNull($before->score);
        $this->assertNull($before->timecompleted);

        $start = time();
        \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.85'));

        $row = $DB->get_record('naas_activity_outcome', ['sourced_id' => $sourcedid], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(0.85, (float) $row->score, 0.00001);
        $this->assertGreaterThanOrEqual($start, (int) $row->timecompleted);

        // Pin the completion time in the past, then post a second result for the same session.
        $DB->set_field('naas_activity_outcome', 'timecompleted', 1000, ['sourced_id' => $sourcedid]);
        \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.5'));

        $row = $DB->get_record('naas_activity_outcome', ['sourced_id' => $sourcedid], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(0.5, (float) $row->score, 0.00001);
        $this->assertSame(1000, (int) $row->timecompleted);
        $this->assertEquals(1, $DB->count_records('naas_activity_outcome', ['sourced_id' => $sourcedid]));
    }

    /**
     * An unsupported grade method rejects the outcome before the attempt is closed.
     */
    public function test_handle_unsupported_grade_method_leaves_attempt_open(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $DB->set_field('naas', 'grade_method', 99, ['id' => $naas->id]);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $sourcedid = 'lti-bad-method-open';
        $gen->create_activity_outcome($user->id, $naas->cmid, $sourcedid);

        try {
            \mod_naas_outcome::handle($this->replace_result_xml($sourcedid, '0.5'));
            $this->fail('An unsupported grade method must throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:unsupported_grade_method', $e->errorcode);
        }

        $row = $DB->get_record('naas_activity_outcome', ['sourced_id' => $sourcedid], '*', MUST_EXIST);
        $this->assertNull($row->score);
        $this->assertNull($row->timecompleted);
    }

    // Attempt limit enforced when the outcome arrives.

    /**
     * Two launches opened before the first outcome cannot both complete when one attempt is allowed.
     * A new result for the attempt that already completed is still accepted.
     */
    public function test_handle_rejects_outcome_beyond_attempts_allowed(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        [$course, $naas, $user] = $this->setup_limited_activity(1);
        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas->cmid, 'lti-limit-first');
        $gen->create_activity_outcome($user->id, $naas->cmid, 'lti-limit-second');

        \mod_naas_outcome::handle($this->replace_result_xml('lti-limit-first', '0.4'));

        try {
            \mod_naas_outcome::handle($this->replace_result_xml('lti-limit-second', '0.9'));
            $this->fail('An outcome beyond the attempts allowed must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nomoreattempts', $e->errorcode);
        }
        $second = $DB->get_record('naas_activity_outcome', ['sourced_id' => 'lti-limit-second'], '*', MUST_EXIST);
        $this->assertNull($second->timecompleted);
        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(40.0, (float) $grades->items[0]->grades[$user->id]->grade);

        // The completed attempt may still send an updated result.
        \mod_naas_outcome::handle($this->replace_result_xml('lti-limit-first', '0.6'));
        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(60.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * Users who can edit the activity are not limited, as at launch.
     */
    public function test_handle_does_not_limit_editing_teachers(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/outcome.php');

        [$course, $naas] = $this->setup_limited_activity(1);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($teacher->id, $naas->cmid, 'lti-teacher-first');
        $gen->create_activity_outcome($teacher->id, $naas->cmid, 'lti-teacher-second');

        \mod_naas_outcome::handle($this->replace_result_xml('lti-teacher-first', '0.4'));
        \mod_naas_outcome::handle($this->replace_result_xml('lti-teacher-second', '0.9'));

        $this->assertEquals(2, \mod_naas\attempts::count_completed((int) $naas->cmid, (int) $teacher->id));
    }

    // Helpers.

    /**
     * Graded activity limited to $attempts attempts, with one enrolled learner.
     *
     * @param int $attempts
     * @return array [course, naas module, learner]
     */
    private function setup_limited_activity(int $attempts): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
        ]);
        $DB->set_field('naas', 'attempts', $attempts, ['id' => $naas->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        return [$course, $naas, $user];
    }

    /**
     * Minimal IMS LTI replaceResult payload used by handle() tests.
     *
     * @param string $sourcedid
     * @param string $score     Normalised score 0–1 as string (e.g. "0.85")
     * @return string
     */
    private function replace_result_xml(string $sourcedid, string $score): string {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
  <imsx_POXHeader>
    <imsx_POXResponseHeaderInfo>
      <imsx_version>V1.0</imsx_version>
      <imsx_messageIdentifier>1</imsx_messageIdentifier>
    </imsx_POXResponseHeaderInfo>
  </imsx_POXHeader>
  <imsx_POXBody>
    <replaceResultRequest>
      <resultRecord>
        <sourcedGUID>
          <sourcedId>{$sourcedid}</sourcedId>
        </sourcedGUID>
        <result>
          <resultScore>
            <score>{$score}</score>
          </resultScore>
        </result>
      </resultRecord>
    </replaceResultRequest>
  </imsx_POXBody>
</imsx_POXEnvelopeRequest>
XML;
    }

    /**
     * Create a course, one naas activity (with grade item), and one enrolled user.
     *
     * @param string $grademethod  One of NAAS_GRADEHIGHEST / NAAS_ATTEMPTFIRST / NAAS_ATTEMPTLAST
     * @return array  [$course, $naas, $user, $cm]
     */
    private function setup_graded_activity(string $grademethod): array {
        $course = $this->getDataGenerator()->create_course();
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'       => $course->id,
            'grade_method' => $grademethod,
        ]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $cm     = get_coursemodule_from_instance('naas', $naas->id, $course->id, false, MUST_EXIST);

        return [$course, $naas, $user, $cm];
    }

    /**
     * Write a raw grade for a user on a naas activity.
     *
     * @param int   $courseid
     * @param int   $instanceid   naas table id
     * @param int   $userid
     * @param float $rawgrade     0–100
     */
    private function submit_grade(int $courseid, int $instanceid, int $userid, float $rawgrade): void {
        $grade           = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = $rawgrade;
        grade_update('mod/naas', $courseid, 'mod', 'naas', $instanceid, 0, $grade);
    }
}
