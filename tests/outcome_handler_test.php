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
 * Tests for the outcome handler class of mod/naas/outcome.php.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/naas/outcome.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas_outcome::class)]
/**
 * Grade passback: authentication, parsing, attempt limits, grading methods and completion.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_naas_outcome
 */
final class outcome_handler_test extends \advanced_testcase {
    /** @var string LTI consumer key used to sign outcomes. */
    private const KEY = 'outcome-key';
    /** @var string LTI shared secret used to sign outcomes. */
    private const SECRET = 'outcome-secret';
    /** @var string Outcome service URL used to sign outcomes. */
    private const URL = 'https://moodle.example.test/mod/naas/outcome.php?id=7';

    /** @var array Copy of $_SERVER taken in setUp(). */
    private $server = [];

    /**
     * Keep the request superglobal clean: the handler reads the Authorization header from it.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->server = $_SERVER;
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['QUERY_STRING']);
    }

    /**
     * Restore $_SERVER and drop curl responses a failing test did not consume.
     */
    protected function tearDown(): void {
        $_SERVER = $this->server;
        $mocks = new \ReflectionProperty(\curl::class, 'mockresponses');
        $mocks->setAccessible(true);
        $mocks->setValue(null, []);
        parent::tearDown();
    }

    /**
     * Activity in a course with completion, one learner and one launch waiting for its outcome.
     *
     * @param string $grademethod
     * @param int $attempts
     * @param bool $completion Whether completion is enabled on the course.
     * @return array [course, naas module, learner]
     */
    private function setup_launch(string $grademethod = NAAS_ATTEMPTLAST, int $attempts = 0, bool $completion = true): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => $completion ? 1 : 0]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => $grademethod,
            'nugget_id' => 'nugget-outcome',
        ]);
        $DB->set_field('naas', 'attempts', $attempts, ['id' => $naas->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        return [$course, $naas, $user];
    }

    /**
     * Insert a launch row for a sourced id.
     *
     * @param int $userid
     * @param int $cmid
     * @param string $sourcedid
     * @return void
     */
    private function launch(int $userid, int $cmid, string $sourcedid): void {
        /** @var \mod_naas_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $generator->create_activity_outcome($userid, $cmid, $sourcedid);
    }

    /**
     * Minimal replaceResult request.
     *
     * @param string $sourcedid
     * @param string $score
     * @return string
     */
    private function xml(string $sourcedid, string $score): string {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">'
            . '<imsx_POXBody><replaceResultRequest><resultRecord>'
            . '<sourcedGUID><sourcedId>' . $sourcedid . '</sourcedId></sourcedGUID>'
            . '<result><resultScore><score>' . $score . '</score></resultScore></result>'
            . '</resultRecord></replaceResultRequest></imsx_POXBody></imsx_POXEnvelopeRequest>';
    }

    /**
     * Current gradebook grade of a learner.
     *
     * @param \stdClass $course
     * @param \stdClass $naas
     * @param int $userid
     * @return float|null
     */
    private function grade(\stdClass $course, \stdClass $naas, int $userid): ?float {
        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $userid);
        $grade = $grades->items[0]->grades[$userid]->grade;
        return $grade === null ? null : (float) $grade;
    }

    /**
     * Run a call that must fail with a given moodle_exception error code.
     *
     * @param string $errorcode
     * @param callable $call
     * @return void
     */
    private function assert_moodle_exception(string $errorcode, callable $call): void {
        try {
            $call();
            $this->fail("Expected moodle_exception {$errorcode}");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    /**
     * Queue NaaS answers for naas_client (curl serves the last queued first).
     *
     * @param string ...$bodies Response bodies in request order.
     * @return void
     */
    private function queue_naas_responses(string ...$bodies): void {
        foreach (array_reverse($bodies) as $body) {
            \curl::mock_response($body);
        }
    }

    /**
     * With OAuth optional and no Authorization header at all, the grade is stored and nothing is remembered.
     */
    public function test_process_without_signature_stores_grade_when_oauth_optional(): void {
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-unsigned');

        $this->assertFalse(\mod_naas_outcome::is_oauth_required());
        \mod_naas_outcome::process($this->xml('src-unsigned', '0.4'));

        $this->assertEquals(40.0, $this->grade($course, $naas, $user->id));
        $this->assertFalse(get_config('naas', 'naas_outcome_signed_seen'));
    }

    /**
     * With OAuth optional, a correctly signed post records that NaaS already signs outcomes.
     */
    public function test_process_records_signed_outcome_when_oauth_optional(): void {
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-signed');
        $xml = $this->xml('src-signed', '0.6');
        $header = lti_outcome_oauth::sign(self::KEY, self::SECRET, $xml, self::URL);

        $before = time();
        \mod_naas_outcome::process($xml, $header, self::URL, (object) ['key' => self::KEY, 'secret' => self::SECRET]);

        $this->assertEquals(60.0, $this->grade($course, $naas, $user->id));
        $this->assertGreaterThanOrEqual($before, (int) get_config('naas', 'naas_outcome_signed_seen'));
    }

    /**
     * With OAuth optional, a badly signed post is still graded but not remembered as signed.
     */
    public function test_process_accepts_bad_signature_when_oauth_optional(): void {
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-badsig');
        $xml = $this->xml('src-badsig', '0.3');
        $header = lti_outcome_oauth::sign(self::KEY, 'wrong-secret', $xml, self::URL);

        \mod_naas_outcome::process($xml, $header, self::URL, (object) ['key' => self::KEY, 'secret' => self::SECRET]);

        $this->assertEquals(30.0, $this->grade($course, $naas, $user->id));
        $this->assertFalse(get_config('naas', 'naas_outcome_signed_seen'));
    }

    /**
     * With OAuth required, the header, URL and credentials all come from the request and NaaS.
     */
    public function test_process_authenticates_from_request_and_naas_credentials_when_required(): void {
        global $CFG;
        set_config('naas_require_outcome_oauth', 1, 'naas');
        set_config('naas_endpoint', 'https://naas.example.test/api', 'naas');
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-required');
        $xml = $this->xml('src-required', '0.9');
        $url = $CFG->wwwroot . '/mod/naas/outcome.php?id=' . $naas->cmid;
        $_SERVER['QUERY_STRING'] = 'id=' . $naas->cmid;
        $_SERVER['HTTP_AUTHORIZATION'] = trim(substr(lti_outcome_oauth::sign(self::KEY, self::SECRET, $xml, $url), 14));
        $this->queue_naas_responses(json_encode(['payload' => ['key' => self::KEY, 'secret' => self::SECRET]]));

        $this->assertTrue(\mod_naas_outcome::is_oauth_required());
        \mod_naas_outcome::process($xml);

        $this->assertEquals(90.0, $this->grade($course, $naas, $user->id));
    }

    /**
     * With OAuth required, an unsigned post is refused before any grade is written.
     */
    public function test_process_refuses_unsigned_post_when_oauth_required(): void {
        set_config('naas_require_outcome_oauth', 1, 'naas');
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-refused');

        $this->assert_moodle_exception('error:oauth_invalid', function () {
            \mod_naas_outcome::process($this->xml('src-refused', '0.9'), '', self::URL, (object) [
                'key' => self::KEY,
                'secret' => self::SECRET,
            ]);
        });
        $this->assertNull($this->grade($course, $naas, $user->id));
    }

    /**
     * The header may come from REDIRECT_HTTP_AUTHORIZATION, and the URL defaults to outcome.php.
     */
    public function test_authenticate_reads_redirect_header_and_default_url(): void {
        global $CFG;
        $xml = $this->xml('src-redirect', '0.5');
        $url = $CFG->wwwroot . '/mod/naas/outcome.php';
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = lti_outcome_oauth::sign(self::KEY, self::SECRET, $xml, $url);

        \mod_naas_outcome::authenticate($xml, null, null, (object) ['key' => self::KEY, 'secret' => self::SECRET]);

        // The same nonce is now spent: a replay of that request is refused.
        $this->assert_moodle_exception('error:oauth_invalid', function () use ($xml) {
            \mod_naas_outcome::authenticate($xml, null, null, (object) ['key' => self::KEY, 'secret' => self::SECRET]);
        });
    }

    /**
     * Credentials without a key or a secret are refused.
     */
    public function test_authenticate_refuses_incomplete_credentials(): void {
        $xml = $this->xml('src-creds', '0.5');
        $header = lti_outcome_oauth::sign(self::KEY, self::SECRET, $xml, self::URL);

        $this->assert_moodle_exception('error:oauth_invalid', function () use ($xml, $header) {
            \mod_naas_outcome::authenticate($xml, $header, self::URL, (object) ['key' => self::KEY, 'secret' => '']);
        });
        $this->assert_moodle_exception('error:oauth_invalid', function () use ($xml, $header) {
            \mod_naas_outcome::authenticate($xml, $header, self::URL, (object) ['key' => '', 'secret' => self::SECRET]);
        });
    }

    /**
     * NaaS credentials are looked up for the nugget behind the sourced id.
     */
    public function test_resolve_lti_credentials_returns_naas_key_and_secret(): void {
        set_config('naas_endpoint', 'https://naas.example.test/api', 'naas');
        [, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-resolve');
        $this->queue_naas_responses(json_encode(['payload' => ['key' => 123, 'secret' => 'abc', 'url' => 'https://x']]));

        $credentials = \mod_naas_outcome::resolve_lti_credentials('src-resolve');

        $this->assertSame('123', $credentials->key);
        $this->assertSame('abc', $credentials->secret);
        $this->assertSame(['key', 'secret'], array_keys((array) $credentials));
    }

    /**
     * An unknown sourced id, or a NaaS answer without secret, cannot be authenticated.
     */
    public function test_resolve_lti_credentials_refuses_unknown_session_and_incomplete_config(): void {
        set_config('naas_endpoint', 'https://naas.example.test/api', 'naas');
        [, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-nosecret');

        $this->assert_moodle_exception('error:unknown_sourced_id', function () {
            \mod_naas_outcome::resolve_lti_credentials('src-missing');
        });

        $this->queue_naas_responses(json_encode(['payload' => ['key' => 'only-key']]));
        $this->assert_moodle_exception('error:oauth_invalid', function () {
            \mod_naas_outcome::resolve_lti_credentials('src-nosecret');
        });

        $this->queue_naas_responses(json_encode(['error' => 'nugget not found']));
        $this->assert_moodle_exception('error:oauth_invalid', function () {
            \mod_naas_outcome::resolve_lti_credentials('src-nosecret');
        });
    }

    /**
     * Scores outside 0..1 and unknown sessions are refused.
     */
    public function test_handle_refuses_out_of_range_score_and_unknown_session(): void {
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-range');

        $this->assert_moodle_exception('error:invalid_score', function () {
            \mod_naas_outcome::handle($this->xml('src-range', '1.5'));
        });
        $this->assert_moodle_exception('error:invalid_score', function () {
            \mod_naas_outcome::handle($this->xml('src-range', '-0.1'));
        });
        $this->assert_moodle_exception('error:unknown_sourced_id', function () {
            \mod_naas_outcome::handle($this->xml('src-unknown', '0.5'));
        });
        $this->assertNull($this->grade($course, $naas, $user->id));
    }

    /**
     * An activity whose grade item was removed cannot receive a grade.
     */
    public function test_handle_requires_the_grade_item(): void {
        global $DB;
        [, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-noitem');
        naas_grade_item_delete($DB->get_record('naas', ['id' => $naas->id], '*', MUST_EXIST));

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Missing grade item for NaaS outcome');
        \mod_naas_outcome::handle($this->xml('src-noitem', '0.5'));
    }

    /**
     * Highest grade: a better score replaces the grade, a worse one does not.
     */
    public function test_handle_highest_grade_keeps_the_best_score(): void {
        [$course, $naas, $user] = $this->setup_launch(NAAS_GRADEHIGHEST);
        $this->launch($user->id, $naas->cmid, 'src-high-1');
        $this->launch($user->id, $naas->cmid, 'src-high-2');
        $this->launch($user->id, $naas->cmid, 'src-high-3');

        \mod_naas_outcome::handle($this->xml('src-high-1', '0.5'));
        $this->assertEquals(50.0, $this->grade($course, $naas, $user->id));
        \mod_naas_outcome::handle($this->xml('src-high-2', '0.2'));
        $this->assertEquals(50.0, $this->grade($course, $naas, $user->id));
        \mod_naas_outcome::handle($this->xml('src-high-3', '0.85'));
        $this->assertEquals(85.0, $this->grade($course, $naas, $user->id));
        $this->assertSame(3, attempts::count_completed((int) $naas->cmid, (int) $user->id));
    }

    /**
     * First attempt: only the first outcome sets the grade.
     */
    public function test_handle_first_attempt_keeps_the_first_score(): void {
        [$course, $naas, $user] = $this->setup_launch(NAAS_ATTEMPTFIRST);
        $this->launch($user->id, $naas->cmid, 'src-first-1');
        $this->launch($user->id, $naas->cmid, 'src-first-2');

        \mod_naas_outcome::handle($this->xml('src-first-1', '0.3'));
        \mod_naas_outcome::handle($this->xml('src-first-2', '0.95'));

        $this->assertEquals(30.0, $this->grade($course, $naas, $user->id));
        $this->assertSame(2, attempts::count_completed((int) $naas->cmid, (int) $user->id));
    }

    /**
     * Last attempt: every outcome replaces the grade, and completion is updated.
     */
    public function test_handle_last_attempt_overwrites_and_updates_completion(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'grade_method' => NAAS_ATTEMPTLAST,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->launch($user->id, $naas->cmid, 'src-last-1');
        $this->launch($user->id, $naas->cmid, 'src-last-2');

        \mod_naas_outcome::handle($this->xml('src-last-1', '0.9'));
        \mod_naas_outcome::handle($this->xml('src-last-2', '0.1'));

        $this->assertEquals(10.0, $this->grade($course, $naas, $user->id));
        $cm = get_coursemodule_from_id('naas', $naas->cmid, 0, false, MUST_EXIST);
        $data = (new \completion_info($course))->get_data($cm, false, $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, (int) $data->completionstate);
    }

    /**
     * An unknown grading method is refused and leaves the attempt open.
     */
    public function test_handle_refuses_unknown_grade_method(): void {
        global $DB;
        [, $naas, $user] = $this->setup_launch();
        $DB->set_field('naas', 'grade_method', '99', ['id' => $naas->id]);
        $this->launch($user->id, $naas->cmid, 'src-method');

        $this->assert_moodle_exception('error:unsupported_grade_method', function () {
            \mod_naas_outcome::handle($this->xml('src-method', '0.5'));
        });
        $this->assertSame(0, attempts::count_completed((int) $naas->cmid, (int) $user->id));
    }

    /**
     * A course without completion tracking still takes the outcome: grade and attempt are kept.
     */
    public function test_handle_accepts_outcome_when_course_completion_is_disabled(): void {
        [$course, $naas, $user] = $this->setup_launch(NAAS_ATTEMPTLAST, 0, false);
        $this->launch($user->id, $naas->cmid, 'src-nocompletion');

        \mod_naas_outcome::handle($this->xml('src-nocompletion', '0.5'));

        $this->assertEquals(50.0, $this->grade($course, $naas, (int) $user->id));
        $this->assertSame(1, attempts::count_completed((int) $naas->cmid, (int) $user->id));
    }

    /**
     * An accepted outcome answers HTTP 200 with an LTI success envelope echoing the request id.
     */
    public function test_respond_accepts_outcome_with_lti_success(): void {
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-respond-ok');
        $xml = str_replace('<imsx_POXBody>', '<imsx_POXHeader><imsx_POXRequestHeaderInfo><imsx_version>V1.0</imsx_version>'
            . '<imsx_messageIdentifier>msg-42</imsx_messageIdentifier></imsx_POXRequestHeaderInfo></imsx_POXHeader>'
            . '<imsx_POXBody>', $this->xml('src-respond-ok', '0.6'));

        $response = \mod_naas_outcome::respond($xml);

        $this->assertSame(200, $response['status']);
        $body = simplexml_load_string(str_replace(' xmlns=', ' xmlns:ns=', $response['body']));
        $info = $body->imsx_POXHeader->imsx_POXResponseHeaderInfo->imsx_statusInfo;
        $this->assertSame('success', (string) $info->imsx_codeMajor);
        $this->assertSame('msg-42', (string) $info->imsx_messageRefIdentifier);
        $this->assertSame('replaceResult', (string) $info->imsx_operationRefIdentifier);
        $this->assertTrue(isset($body->imsx_POXBody->replaceResultResponse));
        $this->assertEquals(60.0, $this->grade($course, $naas, (int) $user->id));
    }

    /**
     * Refused outcomes answer HTTP 400 with an LTI failure envelope and the reason, and change nothing.
     */
    public function test_respond_refuses_bad_outcomes_with_lti_failure(): void {
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-respond-bad');

        $cases = [
            'score out of range' => [$this->xml('src-respond-bad', '1.5'), 'error:invalid_score'],
            'unknown sourcedId' => [$this->xml('src-never-launched', '0.5'), 'error:unknown_sourced_id'],
            'malformed body' => ['<not-xml', 'error:malformed_xml'],
        ];
        foreach ($cases as $label => [$xml, $stringid]) {
            $response = \mod_naas_outcome::respond($xml);
            $this->assertSame(400, $response['status'], $label);
            $this->assertStringContainsString('<imsx_codeMajor>failure</imsx_codeMajor>', $response['body'], $label);
            $this->assertStringContainsString(
                htmlspecialchars(get_string($stringid, 'naas'), ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                $response['body'],
                $label
            );
            $this->assertStringNotContainsString('replaceResultResponse', $response['body'], $label);
        }
        $this->assertNull($this->grade($course, $naas, (int) $user->id));
    }

    /**
     * With OAuth required, an unsigned outcome answers HTTP 401.
     */
    public function test_respond_refuses_unsigned_outcome_with_401_when_oauth_required(): void {
        [$course, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-respond-unsigned');
        set_config('naas_require_outcome_oauth', 1, 'naas');

        $response = \mod_naas_outcome::respond(
            $this->xml('src-respond-unsigned', '0.5'),
            '',
            self::URL,
            (object) ['key' => self::KEY, 'secret' => self::SECRET]
        );

        $this->assertSame(401, $response['status']);
        $this->assertStringContainsString('<imsx_codeMajor>failure</imsx_codeMajor>', $response['body']);
        $this->assertNull($this->grade($course, $naas, (int) $user->id));
    }

    /**
     * An internal error answers HTTP 500 with a generic reason, never the internal message.
     */
    public function test_respond_hides_internal_errors_behind_500(): void {
        global $DB;
        [, $naas, $user] = $this->setup_launch();
        $this->launch($user->id, $naas->cmid, 'src-respond-internal');
        $DB->delete_records('grade_items', ['itemmodule' => 'naas', 'iteminstance' => $naas->id]);

        $response = \mod_naas_outcome::respond($this->xml('src-respond-internal', '0.5'));
        $this->assertDebuggingCalled();

        $this->assertSame(500, $response['status']);
        $this->assertStringContainsString(
            '<imsx_description>The score could not be recorded.</imsx_description>',
            $response['body']
        );
        $this->assertStringNotContainsString('Missing grade item', $response['body']);
    }

    /**
     * The request id is echoed only when it is a plain token; anything else is dropped.
     */
    public function test_response_xml_echoes_only_plain_message_identifiers(): void {
        $plain = \mod_naas_outcome::response_xml('<imsx_messageIdentifier>abc.123</imsx_messageIdentifier>', true, 'ok');
        $this->assertStringContainsString('<imsx_messageRefIdentifier>abc.123</imsx_messageRefIdentifier>', $plain);

        $hostile = \mod_naas_outcome::response_xml(
            '<imsx_messageIdentifier><![CDATA[x]]></imsx_messageIdentifier>',
            false,
            'a < b & "c"'
        );
        $this->assertStringContainsString('<imsx_messageRefIdentifier></imsx_messageRefIdentifier>', $hostile);
        $this->assertStringContainsString('<imsx_description>a &lt; b &amp; &quot;c&quot;</imsx_description>', $hostile);
    }

    /**
     * Learners are limited to the attempts allowed, a completed attempt may resend, teachers are not limited.
     */
    public function test_handle_enforces_attempts_allowed(): void {
        global $DB;
        [$course, $naas, $user] = $this->setup_launch(NAAS_ATTEMPTLAST, 1);
        $this->launch($user->id, $naas->cmid, 'src-limit-1');
        $this->launch($user->id, $naas->cmid, 'src-limit-2');

        \mod_naas_outcome::handle($this->xml('src-limit-1', '0.4'));
        $this->assert_moodle_exception('nomoreattempts', function () {
            \mod_naas_outcome::handle($this->xml('src-limit-2', '0.8'));
        });
        $this->assertNull($DB->get_field('naas_activity_outcome', 'timecompleted', ['sourced_id' => 'src-limit-2']));

        \mod_naas_outcome::handle($this->xml('src-limit-1', '0.7'));
        $this->assertEquals(70.0, $this->grade($course, $naas, $user->id));

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->launch($teacher->id, $naas->cmid, 'src-teacher-1');
        $this->launch($teacher->id, $naas->cmid, 'src-teacher-2');
        \mod_naas_outcome::handle($this->xml('src-teacher-1', '0.4'));
        \mod_naas_outcome::handle($this->xml('src-teacher-2', '0.6'));
        $this->assertSame(2, attempts::count_completed((int) $naas->cmid, (int) $teacher->id));
    }

    /**
     * The first-attempt rule applies only to an existing, still empty first grade.
     */
    public function test_should_apply_attempt_first_grade(): void {
        $this->assertTrue(\mod_naas_outcome::should_apply_attempt_first_grade([(object) ['grade' => null]]));
        $this->assertFalse(\mod_naas_outcome::should_apply_attempt_first_grade([(object) ['grade' => 12.0]]));
        $this->assertFalse(\mod_naas_outcome::should_apply_attempt_first_grade([]));
        $this->assertTrue(\mod_naas_outcome::should_apply_attempt_first_grade([5 => (object) ['grade' => null]]));
    }

    /**
     * Prefixed and default namespaces are both readable, values are trimmed.
     */
    public function test_parse_replace_result_reads_namespaced_documents(): void {
        $prefixed = '<?xml version="1.0"?>'
            . '<ims:imsx_POXEnvelopeRequest xmlns:ims="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">'
            . '<ims:imsx_POXBody><ims:replaceResultRequest><ims:resultRecord>'
            . '<ims:sourcedGUID><ims:sourcedId> src-prefixed </ims:sourcedId></ims:sourcedGUID>'
            . '<ims:result><ims:resultScore><ims:score> 0.25 </ims:score></ims:resultScore></ims:result>'
            . '</ims:resultRecord></ims:replaceResultRequest></ims:imsx_POXBody></ims:imsx_POXEnvelopeRequest>';

        $this->assertSame(['src-prefixed', '0.25'], \mod_naas_outcome::parse_replace_result($prefixed));
        $this->assertSame(['src-default', '1'], \mod_naas_outcome::parse_replace_result($this->xml('src-default', '1')));
    }

    /**
     * Broken XML and documents missing the score or the sourced id are malformed.
     */
    public function test_parse_replace_result_refuses_malformed_documents(): void {
        $this->assert_moodle_exception('error:malformed_xml', function () {
            \mod_naas_outcome::parse_replace_result('<imsx_POXEnvelopeRequest><unclosed>');
        });
        $this->assert_moodle_exception('error:malformed_xml', function () {
            \mod_naas_outcome::parse_replace_result($this->xml('src-noscore', ''));
        });
        $this->assert_moodle_exception('error:malformed_xml', function () {
            \mod_naas_outcome::parse_replace_result($this->xml('', '0.5'));
        });
    }
}
