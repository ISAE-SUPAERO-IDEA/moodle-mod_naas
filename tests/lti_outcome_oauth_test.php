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
 * Tests for LTI Basic Outcomes OAuth body-hash signing and verification.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/naas/outcome.php');

use advanced_testcase;

/**
 * Tests for the OAuth body-hash signing and verification of LTI Basic Outcomes.
 *
 * @package   mod_naas
 * @copyright 2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\lti_outcome_oauth
 * @covers \mod_naas_outcome::authenticate
 * @covers \mod_naas_outcome::process
 */
class lti_outcome_oauth_test extends advanced_testcase {
    /** @var string */
    private const KEY = 'structure-test-key';
    /** @var string */
    private const SECRET = 'shared-secret-for-tests';
    /** @var string */
    private const URL = 'https://moodle.example.test/mod/naas/outcome.php?id=42';

    /**
     * A correctly signed body is accepted.
     */
    public function test_verify_accepts_valid_signature(): void {
        $this->resetAfterTest(true);

        $body = $this->sample_body('src-ok', '0.9');
        $header = lti_outcome_oauth::sign(self::KEY, self::SECRET, $body, self::URL);

        lti_outcome_oauth::verify(self::KEY, self::SECRET, $body, $header, self::URL);
        $this->assertTrue(true);
    }

    /**
     * Tampering with the body after signing must fail.
     */
    public function test_verify_rejects_body_tampering(): void {
        $this->resetAfterTest(true);

        $body = $this->sample_body('src-tamper', '0.5');
        $header = lti_outcome_oauth::sign(self::KEY, self::SECRET, $body, self::URL);
        $tampered = str_replace('0.5', '1.0', $body);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/oauth_invalid|authenticated/');
        lti_outcome_oauth::verify(self::KEY, self::SECRET, $tampered, $header, self::URL);
    }

    /**
     * A wrong secret must fail.
     */
    public function test_verify_rejects_wrong_secret(): void {
        $this->resetAfterTest(true);

        $body = $this->sample_body('src-secret', '0.2');
        $header = lti_outcome_oauth::sign(self::KEY, self::SECRET, $body, self::URL);

        $this->expectException(\moodle_exception::class);
        lti_outcome_oauth::verify(self::KEY, 'other-secret', $body, $header, self::URL);
    }

    /**
     * Missing Authorization header must fail.
     */
    public function test_verify_rejects_missing_authorization(): void {
        $this->resetAfterTest(true);

        $this->expectException(\moodle_exception::class);
        lti_outcome_oauth::verify(self::KEY, self::SECRET, $this->sample_body('x', '0'), '', self::URL);
    }

    /**
     * Replaying the same nonce within the window must fail.
     */
    public function test_verify_rejects_replayed_nonce(): void {
        $this->resetAfterTest(true);

        $body = $this->sample_body('src-replay', '0.7');
        $header = lti_outcome_oauth::sign(self::KEY, self::SECRET, $body, self::URL);

        lti_outcome_oauth::verify(self::KEY, self::SECRET, $body, $header, self::URL);

        $this->expectException(\moodle_exception::class);
        lti_outcome_oauth::verify(self::KEY, self::SECRET, $body, $header, self::URL);
    }

    /**
     * process() with injected credentials runs authenticate then handle when OAuth is required.
     */
    public function test_process_with_valid_signature_updates_grade(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('naas_require_outcome_oauth', 1, 'naas');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $sourcedid = 'oauth-process-1';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        $body = $this->sample_body($sourcedid, '0.85');
        $url = 'https://moodle.example.test/mod/naas/outcome.php?id=' . $naas->cmid;
        $header = lti_outcome_oauth::sign(self::KEY, self::SECRET, $body, $url);
        $credentials = (object) ['key' => self::KEY, 'secret' => self::SECRET];

        \mod_naas_outcome::process($body, $header, $url, $credentials);

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(85.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * process() without a signature must not update the grade when OAuth is required.
     */
    public function test_process_without_signature_is_rejected_when_required(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('naas_require_outcome_oauth', 1, 'naas');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $sourcedid = 'oauth-unsigned-1';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        $body = $this->sample_body($sourcedid, '1.0');
        $credentials = (object) ['key' => self::KEY, 'secret' => self::SECRET];

        try {
            \mod_naas_outcome::process($body, '', self::URL, $credentials);
            $this->fail('Unsigned outcomes must be rejected');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:oauth_invalid', $e->errorcode);
        }

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertNull($grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * Default install leaves OAuth optional so unsigned NaaS posts still grade.
     */
    public function test_process_accepts_unsigned_when_oauth_not_required(): void {
        global $DB;
        $this->resetAfterTest(true);
        // Default / explicit off.
        set_config('naas_require_outcome_oauth', 0, 'naas');
        $this->assertFalse(\mod_naas_outcome::is_oauth_required());

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $sourcedid = 'oauth-optional-1';
        $DB->insert_record('naas_activity_outcome', [
            'user_id' => $user->id,
            'activity_id' => $naas->cmid,
            'sourced_id' => $sourcedid,
            'date_added' => time(),
        ]);

        \mod_naas_outcome::process($this->sample_body($sourcedid, '0.6'), '', self::URL);

        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(60.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * Build a replaceResult POX body.
     *
     * @param string $sourcedid
     * @param string $score
     * @return string
     */
    private function sample_body(string $sourcedid, string $score): string {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
  <imsx_POXHeader>
    <imsx_POXRequestHeaderInfo>
      <imsx_version>V1.0</imsx_version>
      <imsx_messageIdentifier>1</imsx_messageIdentifier>
    </imsx_POXRequestHeaderInfo>
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
}
